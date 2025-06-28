<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserManagementRequest;
use App\Models\User;
use App\Models\Cooperative;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_dinas');
        $this->middleware('can:manage_users');
    }

    public function index(Request $request)
    {
        try {
            $search = $request->get('search');
            $role = $request->get('role');
            $cooperative = $request->get('cooperative');
            $perPage = min($request->get('per_page', 15), 50);

            $users = User::query()
                ->with(['cooperative:id,name', 'roles:id,name'])
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'ILIKE', "%{$search}%")
                            ->orWhere('email', 'ILIKE', "%{$search}%");
                    });
                })
                ->when($role, function ($query, $role) {
                    $query->whereHas('roles', function ($q) use ($role) {
                        $q->where('name', $role);
                    });
                })
                ->when($cooperative, function ($query, $cooperative) {
                    $query->where('cooperative_id', $cooperative);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $cooperatives = Cooperative::select('id', 'name')->orderBy('name')->get();
            $roles = Role::select('id', 'name')->orderBy('name')->get();

            return view('admin.users.index', compact('users', 'cooperatives', 'roles', 'search', 'role', 'cooperative'));
        } catch (\Exception $e) {
            Log::error('Error loading users index', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.dashboard')
                ->with('error', 'Terjadi kesalahan saat memuat data pengguna.');
        }
    }

    public function create()
    {
        try {
            $cooperatives = Cooperative::where('status', 'active')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            $roles = Role::select('id', 'name')->orderBy('name')->get();

            return view('admin.users.create', compact('cooperatives', 'roles'));
        } catch (\Exception $e) {
            Log::error('Error loading user create form', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.users.index')
                ->with('error', 'Terjadi kesalahan saat memuat form tambah pengguna.');
        }
    }

    public function store(UserManagementRequest $request)
    {
        try {
            $data = $request->validated();
            $data['password'] = Hash::make($data['password']);
            $data['email_verified_at'] = now();

            $user = User::create($data);

            // Assign role
            if (!empty($data['role'])) {
                $user->assignRole($data['role']);
            }

            // Log user creation
            $this->auditLogService->log(
                'user_created',
                'User',
                $user->id,
                array_merge($user->toArray(), ['role' => $data['role'] ?? null]),
                auth()->id()
            );

            return redirect()->route('admin.users.show', $user)
                ->with('success', 'Pengguna berhasil ditambahkan.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in user creation', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'data' => $request->validated()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in user creation', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan sistem. Tim teknis telah diberitahu.');
        }
    }

    public function show(User $user)
    {
        try {
            $user->load(['cooperative:id,name', 'roles:id,name']);

            // Get user statistics
            $stats = [
                'total_logins' => $user->auditLogs()->where('action', 'user_login')->count(),
                'last_login' => $user->auditLogs()->where('action', 'user_login')->latest()->first()?->created_at,
                'reports_created' => $user->createdReports()->count(),
                'reports_approved' => $user->approvedReports()->count(),
            ];

            return view('admin.users.show', compact('user', 'stats'));
        } catch (\Exception $e) {
            Log::error('Error loading user details', [
                'user_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.users.index')
                ->with('error', 'Terjadi kesalahan saat memuat detail pengguna.');
        }
    }

    public function edit(User $user)
    {
        try {
            $cooperatives = Cooperative::where('status', 'active')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            $roles = Role::select('id', 'name')->orderBy('name')->get();
            $userRole = $user->roles->first()?->name;

            return view('admin.users.edit', compact('user', 'cooperatives', 'roles', 'userRole'));
        } catch (\Exception $e) {
            Log::error('Error loading user edit form', [
                'user_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.users.show', $user)
                ->with('error', 'Terjadi kesalahan saat memuat form edit pengguna.');
        }
    }

    public function update(UserManagementRequest $request, User $user)
    {
        try {
            $oldData = $user->toArray();
            $data = $request->validated();

            // Handle password update
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);

            // Update role if changed
            if (!empty($data['role'])) {
                $oldRole = $user->roles->first()?->name;
                if ($oldRole !== $data['role']) {
                    $user->syncRoles([$data['role']]);
                }
            }

            // Log user update
            $this->auditLogService->log(
                'user_updated',
                'User',
                $user->id,
                ['old' => $oldData, 'new' => $data],
                auth()->id()
            );

            return redirect()->route('admin.users.show', $user)
                ->with('success', 'Data pengguna berhasil diperbarui.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in user update', [
                'user_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage(),
                'sql' => $e->getSql()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in user update', [
                'user_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan sistem. Tim teknis telah diberitahu.');
        }
    }

    public function destroy(User $user)
    {
        try {
            // Prevent self-deletion
            if ($user->id === auth()->id()) {
                return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
            }

            // Check if user has created reports
            if ($user->createdReports()->exists()) {
                return back()->with('error', 'Tidak dapat menghapus pengguna yang telah membuat laporan keuangan.');
            }

            $userData = $user->toArray();
            $user->delete();

            // Log user deletion
            $this->auditLogService->log(
                'user_deleted',
                'User',
                $user->id,
                $userData,
                auth()->id()
            );

            return redirect()->route('admin.users.index')
                ->with('success', 'Pengguna berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting user', [
                'user_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menghapus pengguna. Silakan coba lagi.');
        }
    }

    public function resetPassword(User $user)
    {
        try {
            $newPassword = \Str::random(12);
            $user->update([
                'password' => Hash::make($newPassword)
            ]);

            // Log password reset
            $this->auditLogService->log(
                'user_password_reset',
                'User',
                $user->id,
                ['reset_by' => auth()->id()],
                auth()->id()
            );

            return back()->with('success', "Password berhasil direset. Password baru: {$newPassword}");
        } catch (\Exception $e) {
            Log::error('Error resetting user password', [
                'user_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal mereset password. Silakan coba lagi.');
        }
    }
}
