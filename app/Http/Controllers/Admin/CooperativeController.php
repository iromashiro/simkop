<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CooperativeRequest;
use App\Models\Cooperative;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class CooperativeController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_dinas');
        $this->middleware('can:manage_cooperatives');
    }

    public function index(Request $request)
    {
        try {
            $search = $request->get('search');
            $status = $request->get('status');
            $perPage = min($request->get('per_page', 15), 50);

            $cooperatives = Cooperative::query()
                ->with(['users' => function ($query) {
                    $query->where('role', 'admin_koperasi')->select('id', 'name', 'email', 'cooperative_id');
                }])
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'ILIKE', "%{$search}%")
                            ->orWhere('code', 'ILIKE', "%{$search}%")
                            ->orWhere('address', 'ILIKE', "%{$search}%");
                    });
                })
                ->when($status, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return view('admin.cooperatives.index', compact('cooperatives', 'search', 'status'));
        } catch (\Exception $e) {
            Log::error('Error loading cooperatives index', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.dashboard')
                ->with('error', 'Terjadi kesalahan saat memuat data koperasi.');
        }
    }

    public function create()
    {
        try {
            return view('admin.cooperatives.create');
        } catch (\Exception $e) {
            Log::error('Error loading cooperative create form', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.cooperatives.index')
                ->with('error', 'Terjadi kesalahan saat memuat form tambah koperasi.');
        }
    }

    public function store(CooperativeRequest $request)
    {
        try {
            $data = $request->validated();

            // Generate unique code if not provided
            if (empty($data['code'])) {
                $data['code'] = $this->generateCooperativeCode($data['name']);
            }

            $cooperative = Cooperative::create($data);

            // Create admin user for cooperative
            if (!empty($data['admin_name']) && !empty($data['admin_email'])) {
                $adminUser = User::create([
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    'password' => Hash::make($data['admin_password'] ?? Str::random(12)),
                    'cooperative_id' => $cooperative->id,
                    'email_verified_at' => now(),
                ]);

                $adminUser->assignRole('admin_koperasi');

                // Log admin creation
                $this->auditLogService->log(
                    'cooperative_admin_created',
                    'User',
                    $adminUser->id,
                    ['cooperative_id' => $cooperative->id],
                    auth()->id()
                );
            }

            // Log cooperative creation
            $this->auditLogService->log(
                'cooperative_created',
                'Cooperative',
                $cooperative->id,
                $cooperative->toArray(),
                auth()->id()
            );

            return redirect()->route('admin.cooperatives.show', $cooperative)
                ->with('success', 'Koperasi berhasil ditambahkan.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in cooperative creation', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'data' => $request->validated()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in cooperative creation', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan sistem. Tim teknis telah diberitahu.');
        }
    }

    public function show(Cooperative $cooperative)
    {
        try {
            $cooperative->load([
                'users' => function ($query) {
                    $query->select('id', 'name', 'email', 'role', 'cooperative_id', 'created_at');
                },
                'financialReports' => function ($query) {
                    $query->select('id', 'cooperative_id', 'report_type', 'reporting_year', 'status', 'created_at')
                        ->orderBy('reporting_year', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->limit(10);
                }
            ]);

            // Get statistics
            $stats = [
                'total_users' => $cooperative->users()->count(),
                'total_reports' => $cooperative->financialReports()->count(),
                'pending_reports' => $cooperative->financialReports()->where('status', 'submitted')->count(),
                'approved_reports' => $cooperative->financialReports()->where('status', 'approved')->count(),
            ];

            return view('admin.cooperatives.show', compact('cooperative', 'stats'));
        } catch (\Exception $e) {
            Log::error('Error loading cooperative details', [
                'user_id' => auth()->id(),
                'cooperative_id' => $cooperative->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.cooperatives.index')
                ->with('error', 'Terjadi kesalahan saat memuat detail koperasi.');
        }
    }

    public function edit(Cooperative $cooperative)
    {
        try {
            return view('admin.cooperatives.edit', compact('cooperative'));
        } catch (\Exception $e) {
            Log::error('Error loading cooperative edit form', [
                'user_id' => auth()->id(),
                'cooperative_id' => $cooperative->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.cooperatives.show', $cooperative)
                ->with('error', 'Terjadi kesalahan saat memuat form edit koperasi.');
        }
    }

    public function update(CooperativeRequest $request, Cooperative $cooperative)
    {
        try {
            $oldData = $cooperative->toArray();
            $newData = $request->validated();

            $cooperative->update($newData);

            // Log cooperative update
            $this->auditLogService->log(
                'cooperative_updated',
                'Cooperative',
                $cooperative->id,
                ['old' => $oldData, 'new' => $newData],
                auth()->id()
            );

            return redirect()->route('admin.cooperatives.show', $cooperative)
                ->with('success', 'Data koperasi berhasil diperbarui.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in cooperative update', [
                'user_id' => auth()->id(),
                'cooperative_id' => $cooperative->id,
                'error' => $e->getMessage(),
                'sql' => $e->getSql()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in cooperative update', [
                'user_id' => auth()->id(),
                'cooperative_id' => $cooperative->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan sistem. Tim teknis telah diberitahu.');
        }
    }

    public function destroy(Cooperative $cooperative)
    {
        try {
            // Check if cooperative has users or reports
            if ($cooperative->users()->exists()) {
                return back()->with('error', 'Tidak dapat menghapus koperasi yang masih memiliki pengguna.');
            }

            if ($cooperative->financialReports()->exists()) {
                return back()->with('error', 'Tidak dapat menghapus koperasi yang masih memiliki laporan keuangan.');
            }

            $cooperativeData = $cooperative->toArray();
            $cooperative->delete();

            // Log cooperative deletion
            $this->auditLogService->log(
                'cooperative_deleted',
                'Cooperative',
                $cooperative->id,
                $cooperativeData,
                auth()->id()
            );

            return redirect()->route('admin.cooperatives.index')
                ->with('success', 'Koperasi berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting cooperative', [
                'user_id' => auth()->id(),
                'cooperative_id' => $cooperative->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menghapus koperasi. Silakan coba lagi.');
        }
    }

    public function activate(Cooperative $cooperative)
    {
        try {
            if ($cooperative->status === 'active') {
                return back()->with('info', 'Koperasi sudah dalam status aktif.');
            }

            $cooperative->update(['status' => 'active']);

            // Log status change
            $this->auditLogService->log(
                'cooperative_activated',
                'Cooperative',
                $cooperative->id,
                ['status' => 'active'],
                auth()->id()
            );

            return back()->with('success', 'Koperasi berhasil diaktifkan.');
        } catch (\Exception $e) {
            Log::error('Error activating cooperative', [
                'user_id' => auth()->id(),
                'cooperative_id' => $cooperative->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal mengaktifkan koperasi. Silakan coba lagi.');
        }
    }

    public function deactivate(Cooperative $cooperative)
    {
        try {
            if ($cooperative->status === 'inactive') {
                return back()->with('info', 'Koperasi sudah dalam status tidak aktif.');
            }

            $cooperative->update(['status' => 'inactive']);

            // Log status change
            $this->auditLogService->log(
                'cooperative_deactivated',
                'Cooperative',
                $cooperative->id,
                ['status' => 'inactive'],
                auth()->id()
            );

            return back()->with('success', 'Koperasi berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            Log::error('Error deactivating cooperative', [
                'user_id' => auth()->id(),
                'cooperative_id' => $cooperative->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menonaktifkan koperasi. Silakan coba lagi.');
        }
    }

    private function generateCooperativeCode(string $name): string
    {
        // Generate code from name (first 3 letters + random number)
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));
        $suffix = str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT);

        $code = $prefix . $suffix;

        // Ensure uniqueness
        $counter = 1;
        while (Cooperative::where('code', $code)->exists()) {
            $code = $prefix . str_pad($suffix + $counter, 3, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $code;
    }
}
