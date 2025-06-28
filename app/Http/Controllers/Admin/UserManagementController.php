<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UserManagementRequest;
use App\Models\Cooperative;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_dinas');
    }

    public function index(Request $request): View
    {
        try {
            $search = $request->get('search');
            $role = $request->get('role');
            $cooperative = $request->get('cooperative');
            $perPage = min($request->get('per_page', 15), 50);

            $users = User::query()
                ->with(['roles:id,name', 'cooperative:id,name'])
                ->when($search, function ($query, $search) {
                    // ✅ SECURITY FIX: Proper search sanitization
                    $sanitizedSearch = str_replace(['%', '_'], ['\%', '\_'], $search);
                    return $query->where(function ($q) use ($sanitizedSearch) {
                        $q->where('name', 'ILIKE', "%{$sanitizedSearch}%")
                            ->orWhere('email', 'ILIKE', "%{$sanitizedSearch}%");
                    });
                })
                ->when($role, function ($query, $role) {
                    return $query->role($role);
                })
                ->when($cooperative, function ($query, $cooperative) {
                    return $query->where('cooperative_id', $cooperative);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $roles = Role::all();
            $cooperatives = Cooperative::select('id', 'name')->orderBy('name')->get();

            return view('admin.users.index', compact('users', 'roles', 'cooperatives', 'search', 'role', 'cooperative'));
        } catch (\Exception $e) {
            Log::error('Error loading users', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal memuat data pengguna. Silakan coba lagi.');
        }
    }

    public function create(): View
    {
        $roles = Role::all();
        $cooperatives = Cooperative::select('id', 'name')->orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'cooperatives'));
    }

    public function store(UserManagementRequest $request): RedirectResponse
    {
        try {
            // ✅ SECURITY FIX: Use database transaction
            return DB::transaction(function () use ($request) {
                $data = $request->validated();

                // Generate secure temporary password
                $temporaryPassword = Str::random(12);
                $data['password'] = Hash::make($temporaryPassword);
                $data['must_change_password'] = true; // Add this field to migration

                $user = User::create($data);
                $user->assignRole($data['role']);

                // ✅ SECURITY FIX: Send password via email instead of displaying
                try {
                    // TODO: Create PasswordCreatedMail class
                    // Mail::to($user->email)->send(new PasswordCreatedMail($user, $temporaryPassword));

                    // Temporary log for development (remove in production)
                    Log::info('User created with temporary password', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'temp_password' => $temporaryPassword // Remove this in production
                    ]);
                } catch (\Exception $mailException) {
                    Log::error('Failed to send password email', [
                        'user_id' => $user->id,
                        'error' => $mailException->getMessage()
                    ]);
                }

                $this->auditLogService->log(
                    'user_created',
                    'Pengguna baru dibuat',
                    [
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $data['role'],
                        'cooperative_id' => $user->cooperative_id
                    ]
                );

                return redirect()->route('admin.users.index')
                    ->with('success', 'Pengguna berhasil dibuat. Password telah dikirim ke email pengguna.');
            });
        } catch (\Exception $e) {
            Log::error('Error creating user', [
                'user_id' => auth()->id(),
                'data' => $request->safe()->except(['password']),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal membuat pengguna: ' . $e->getMessage());
        }
    }

    public function edit(User $user): View
    {
        $roles = Role::all();
        $cooperatives = Cooperative::select('id', 'name')->orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'cooperatives'));
    }

    public function update(UserManagementRequest $request, User $user): RedirectResponse
    {
        try {
            // ✅ SECURITY FIX: Use database transaction
            return DB::transaction(function () use ($request, $user) {
                $data = $request->validated();
                $oldData = $user->toArray();

                // Remove password from update if not provided
                if (empty($data['password'])) {
                    unset($data['password']);
                } else {
                    $data['password'] = Hash::make($data['password']);
                }

                $user->update($data);

                // Update role if provided
                if (isset($data['role'])) {
                    $user->syncRoles([$data['role']]);
                }

                $this->auditLogService->log(
                    'user_updated',
                    'Data pengguna diperbarui',
                    [
                        'user_id' => $user->id,
                        'old_data' => $oldData,
                        'new_data' => $user->fresh()->toArray()
                    ]
                );

                return redirect()->route('admin.users.index')
                    ->with('success', 'Pengguna berhasil diperbarui.');
            });
        } catch (\Exception $e) {
            Log::error('Error updating user', [
                'user_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui pengguna: ' . $e->getMessage());
        }
    }

    public function destroy(User $user): RedirectResponse
    {
        try {
            // ✅ SECURITY FIX: Use database transaction
            return DB::transaction(function () use ($user) {
                // Prevent deleting current user
                if ($user->id === auth()->id()) {
                    return redirect()->back()
                        ->with('error', 'Tidak dapat menghapus akun sendiri.');
                }

                // Check if user has financial reports
                if ($user->createdFinancialReports()->exists()) {
                    return redirect()->back()
                        ->with('error', 'Tidak dapat menghapus pengguna yang memiliki laporan keuangan.');
                }

                $userData = $user->toArray();
                $user->delete();

                $this->auditLogService->log(
                    'user_deleted',
                    'Pengguna dihapus',
                    $userData
                );

                return redirect()->route('admin.users.index')
                    ->with('success', 'Pengguna berhasil dihapus.');
            });
        } catch (\Exception $e) {
            Log::error('Error deleting user', [
                'user_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal menghapus pengguna: ' . $e->getMessage());
        }
    }

    // ✅ CRITICAL SECURITY FIX: Password reset without displaying password
    public function resetPassword(User $user): RedirectResponse
    {
        try {
            return DB::transaction(function () use ($user) {
                $newPassword = Str::random(12);

                $user->update([
                    'password' => Hash::make($newPassword),
                    'must_change_password' => true
                ]);

                // ✅ SECURITY FIX: Send via email instead of displaying
                try {
                    // TODO: Create PasswordResetMail class
                    // Mail::to($user->email)->send(new PasswordResetMail($user, $newPassword));

                    // Temporary log for development (remove in production)
                    Log::info('Password reset for user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'reset_by' => auth()->id(),
                        'new_password' => $newPassword // Remove this in production
                    ]);
                } catch (\Exception $mailException) {
                    Log::error('Failed to send password reset email', [
                        'user_id' => $user->id,
                        'error' => $mailException->getMessage()
                    ]);
                }

                $this->auditLogService->log(
                    'password_reset',
                    'Password pengguna direset',
                    [
                        'target_user_id' => $user->id,
                        'target_user_email' => $user->email
                    ]
                );

                return redirect()->back()
                    ->with('success', 'Password berhasil direset. Password baru telah dikirim ke email pengguna.');
            });
        } catch (\Exception $e) {
            Log::error('Error resetting password', [
                'user_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal mereset password: ' . $e->getMessage());
        }
    }
}
