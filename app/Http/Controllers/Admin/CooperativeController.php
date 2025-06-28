<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\CooperativeRequest;
use App\Models\Cooperative;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CooperativeController extends Controller
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
            $perPage = min($request->get('per_page', 15), 50);

            $cooperatives = Cooperative::query()
                ->with(['users:id,name,email,cooperative_id'])
                ->when($search, function ($query, $search) {
                    // ✅ SECURITY FIX: Proper search sanitization
                    $sanitizedSearch = str_replace(['%', '_'], ['\%', '\_'], $search);
                    return $query->where(function ($q) use ($sanitizedSearch) {
                        $q->where('name', 'ILIKE', "%{$sanitizedSearch}%")
                            ->orWhere('code', 'ILIKE', "%{$sanitizedSearch}%")
                            ->orWhere('address', 'ILIKE', "%{$sanitizedSearch}%");
                    });
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return view('admin.cooperatives.index', compact('cooperatives', 'search'));
        } catch (\Exception $e) {
            Log::error('Error loading cooperatives', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal memuat data koperasi. Silakan coba lagi.');
        }
    }

    public function create(): View
    {
        return view('admin.cooperatives.create');
    }

    public function store(CooperativeRequest $request): RedirectResponse
    {
        try {
            // ✅ SECURITY FIX: Use database transaction
            return DB::transaction(function () use ($request) {
                $data = $request->validated();

                // ✅ SECURITY FIX: Improved code generation
                $data['code'] = $this->generateCooperativeCode($data['name']);

                $cooperative = Cooperative::create($data);

                $this->auditLogService->log(
                    'cooperative_created',
                    'Koperasi baru dibuat',
                    $cooperative->toArray(),
                    $cooperative->id
                );

                return redirect()->route('admin.cooperatives.index')
                    ->with('success', 'Koperasi berhasil dibuat.');
            });
        } catch (\Exception $e) {
            Log::error('Error creating cooperative', [
                'user_id' => auth()->id(),
                'data' => $request->validated(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal membuat koperasi: ' . $e->getMessage());
        }
    }

    public function show(Cooperative $cooperative): View
    {
        try {
            $cooperative->load([
                'users:id,name,email,cooperative_id,created_at',
                'financialReports:id,cooperative_id,report_type,reporting_year,status,created_at'
            ]);

            return view('admin.cooperatives.show', compact('cooperative'));
        } catch (\Exception $e) {
            Log::error('Error loading cooperative details', [
                'user_id' => auth()->id(),
                'cooperative_id' => $cooperative->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.cooperatives.index')
                ->with('error', 'Gagal memuat detail koperasi.');
        }
    }

    public function edit(Cooperative $cooperative): View
    {
        return view('admin.cooperatives.edit', compact('cooperative'));
    }

    public function update(CooperativeRequest $request, Cooperative $cooperative): RedirectResponse
    {
        try {
            // ✅ SECURITY FIX: Use database transaction
            return DB::transaction(function () use ($request, $cooperative) {
                $oldData = $cooperative->toArray();
                $cooperative->update($request->validated());

                $this->auditLogService->log(
                    'cooperative_updated',
                    'Data koperasi diperbarui',
                    [
                        'old_data' => $oldData,
                        'new_data' => $cooperative->fresh()->toArray()
                    ],
                    $cooperative->id
                );

                return redirect()->route('admin.cooperatives.index')
                    ->with('success', 'Koperasi berhasil diperbarui.');
            });
        } catch (\Exception $e) {
            Log::error('Error updating cooperative', [
                'user_id' => auth()->id(),
                'cooperative_id' => $cooperative->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui koperasi: ' . $e->getMessage());
        }
    }

    public function destroy(Cooperative $cooperative): RedirectResponse
    {
        try {
            // ✅ SECURITY FIX: Use database transaction
            return DB::transaction(function () use ($cooperative) {
                // Check if cooperative has users
                if ($cooperative->users()->exists()) {
                    return redirect()->back()
                        ->with('error', 'Tidak dapat menghapus koperasi yang masih memiliki pengguna.');
                }

                // Check if cooperative has financial reports
                if ($cooperative->financialReports()->exists()) {
                    return redirect()->back()
                        ->with('error', 'Tidak dapat menghapus koperasi yang memiliki laporan keuangan.');
                }

                $cooperativeData = $cooperative->toArray();
                $cooperative->delete();

                $this->auditLogService->log(
                    'cooperative_deleted',
                    'Koperasi dihapus',
                    $cooperativeData,
                    $cooperative->id
                );

                return redirect()->route('admin.cooperatives.index')
                    ->with('success', 'Koperasi berhasil dihapus.');
            });
        } catch (\Exception $e) {
            Log::error('Error deleting cooperative', [
                'user_id' => auth()->id(),
                'cooperative_id' => $cooperative->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal menghapus koperasi: ' . $e->getMessage());
        }
    }

    // ✅ SECURITY FIX: Improved code generation with proper sanitization
    private function generateCooperativeCode(string $name): string
    {
        // Sanitize and extract first 3 characters
        $cleanName = preg_replace('/[^a-zA-Z0-9\s]/', '', $name);
        $prefix = mb_strtoupper(substr(str_replace(' ', '', $cleanName), 0, 3));

        // Fallback if name doesn't have valid characters
        if (empty($prefix) || strlen($prefix) < 2) {
            $prefix = 'KOP';
        }

        // Generate unique suffix
        do {
            $suffix = str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT);
            $code = $prefix . $suffix;

            // Check uniqueness in database
            $exists = Cooperative::where('code', $code)->exists();
        } while ($exists);

        return $code;
    }
}
