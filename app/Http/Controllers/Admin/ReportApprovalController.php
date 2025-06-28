<?php

namespace App\Http\Controllers\Admin;

use App\Models\Financial\FinancialReport;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ReportApprovalController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private NotificationService $notificationService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_dinas');
    }

    public function index(Request $request): View
    {
        try {
            $status = $request->get('status', 'submitted');
            $year = $request->get('year');
            $cooperative = $request->get('cooperative');
            $reportType = $request->get('report_type');
            $search = $request->get('search');
            $perPage = min($request->get('per_page', 15), 50);

            $reports = FinancialReport::query()
                ->with(['cooperative:id,name,code'])
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->when($year, function ($query, $year) {
                    return $query->where('reporting_year', $year);
                })
                ->when($cooperative, function ($query, $cooperative) {
                    return $query->where('cooperative_id', $cooperative);
                })
                ->when($reportType, function ($query, $reportType) {
                    return $query->where('report_type', $reportType);
                })
                ->when($search, function ($query, $search) {
                    // ✅ SECURITY FIX: Proper search sanitization
                    $sanitizedSearch = str_replace(['%', '_'], ['\%', '\_'], $search);
                    return $query->whereHas('cooperative', function ($q) use ($sanitizedSearch) {
                        $q->where('name', 'ILIKE', "%{$sanitizedSearch}%")
                            ->orWhere('code', 'ILIKE', "%{$sanitizedSearch}%");
                    });
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $cooperatives = \App\Models\Cooperative::select('id', 'name')->orderBy('name')->get();
            $years = FinancialReport::distinct()->pluck('reporting_year')->sort()->values();
            $reportTypes = FinancialReport::distinct()->pluck('report_type')->sort()->values();

            return view('admin.reports.approval', compact(
                'reports',
                'cooperatives',
                'years',
                'reportTypes',
                'status',
                'year',
                'cooperative',
                'reportType',
                'search'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading reports for approval', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal memuat data laporan. Silakan coba lagi.');
        }
    }

    public function show(FinancialReport $report): View
    {
        try {
            $report->load(['cooperative:id,name,code,address']);

            return view('admin.reports.show', compact('report'));
        } catch (\Exception $e) {
            Log::error('Error loading report details', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.reports.approval')
                ->with('error', 'Gagal memuat detail laporan.');
        }
    }

    public function approve(FinancialReport $report): RedirectResponse
    {
        try {
            // ✅ CRITICAL FIX: Use database transaction
            return DB::transaction(function () use ($report) {
                if ($report->status !== 'submitted') {
                    return redirect()->back()
                        ->with('error', 'Hanya laporan dengan status "submitted" yang dapat disetujui.');
                }

                $oldStatus = $report->status;
                $report->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now()
                ]);

                // Create notification for cooperative admin
                $this->notificationService->createNotification(
                    $report->created_by,
                    $report->cooperative_id,
                    'report_approved',
                    'Laporan Disetujui',
                    "Laporan {$report->report_type} tahun {$report->reporting_year} telah disetujui."
                );

                $this->auditLogService->log(
                    'report_approved',
                    'Laporan keuangan disetujui',
                    [
                        'report_id' => $report->id,
                        'report_type' => $report->report_type,
                        'reporting_year' => $report->reporting_year,
                        'cooperative_id' => $report->cooperative_id,
                        'old_status' => $oldStatus,
                        'new_status' => 'approved'
                    ]
                );

                return redirect()->back()
                    ->with('success', 'Laporan berhasil disetujui.');
            });
        } catch (\Exception $e) {
            Log::error('Error approving report', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal menyetujui laporan: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, FinancialReport $report): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000'
        ]);

        try {
            // ✅ CRITICAL FIX: Use database transaction
            return DB::transaction(function () use ($request, $report) {
                if ($report->status !== 'submitted') {
                    return redirect()->back()
                        ->with('error', 'Hanya laporan dengan status "submitted" yang dapat ditolak.');
                }

                $oldStatus = $report->status;
                $rejectionReason = $request->input('rejection_reason');

                $report->update([
                    'status' => 'rejected',
                    'rejected_by' => auth()->id(),
                    'rejected_at' => now(),
                    'rejection_reason' => $rejectionReason
                ]);

                // Create notification for cooperative admin
                $this->notificationService->createNotification(
                    $report->created_by,
                    $report->cooperative_id,
                    'report_rejected',
                    'Laporan Ditolak',
                    "Laporan {$report->report_type} tahun {$report->reporting_year} ditolak. Alasan: {$rejectionReason}"
                );

                $this->auditLogService->log(
                    'report_rejected',
                    'Laporan keuangan ditolak',
                    [
                        'report_id' => $report->id,
                        'report_type' => $report->report_type,
                        'reporting_year' => $report->reporting_year,
                        'cooperative_id' => $report->cooperative_id,
                        'old_status' => $oldStatus,
                        'new_status' => 'rejected',
                        'rejection_reason' => $rejectionReason
                    ]
                );

                return redirect()->back()
                    ->with('success', 'Laporan berhasil ditolak.');
            });
        } catch (\Exception $e) {
            Log::error('Error rejecting report', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal menolak laporan: ' . $e->getMessage());
        }
    }

    // ✅ CRITICAL FIX: Bulk operations with proper transactions
    public function bulkApprove(Request $request): RedirectResponse
    {
        $request->validate([
            'report_ids' => 'required|array|min:1',
            'report_ids.*' => 'exists:financial_reports,id'
        ]);

        try {
            // ✅ CRITICAL FIX: Use database transaction for bulk operations
            return DB::transaction(function () use ($request) {
                $reportIds = $request->input('report_ids');
                $reports = FinancialReport::whereIn('id', $reportIds)
                    ->where('status', 'submitted')
                    ->get();

                if ($reports->isEmpty()) {
                    return redirect()->back()
                        ->with('error', 'Tidak ada laporan yang dapat disetujui.');
                }

                $approvedCount = 0;
                foreach ($reports as $report) {
                    $report->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now()
                    ]);

                    // Create notification for each cooperative admin
                    $this->notificationService->createNotification(
                        $report->created_by,
                        $report->cooperative_id,
                        'report_approved',
                        'Laporan Disetujui',
                        "Laporan {$report->report_type} tahun {$report->reporting_year} telah disetujui."
                    );

                    $this->auditLogService->log(
                        'report_approved',
                        'Laporan keuangan disetujui (bulk)',
                        [
                            'report_id' => $report->id,
                            'report_type' => $report->report_type,
                            'reporting_year' => $report->reporting_year,
                            'cooperative_id' => $report->cooperative_id
                        ]
                    );

                    $approvedCount++;
                }

                return redirect()->back()
                    ->with('success', "{$approvedCount} laporan berhasil disetujui.");
            });
        } catch (\Exception $e) {
            Log::error('Error bulk approving reports', [
                'user_id' => auth()->id(),
                'report_ids' => $request->input('report_ids'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal menyetujui laporan: ' . $e->getMessage());
        }
    }

    public function bulkReject(Request $request): RedirectResponse
    {
        $request->validate([
            'report_ids' => 'required|array|min:1',
            'report_ids.*' => 'exists:financial_reports,id',
            'rejection_reason' => 'required|string|max:1000'
        ]);

        try {
            // ✅ CRITICAL FIX: Use database transaction for bulk operations
            return DB::transaction(function () use ($request) {
                $reportIds = $request->input('report_ids');
                $rejectionReason = $request->input('rejection_reason');

                $reports = FinancialReport::whereIn('id', $reportIds)
                    ->where('status', 'submitted')
                    ->get();

                if ($reports->isEmpty()) {
                    return redirect()->back()
                        ->with('error', 'Tidak ada laporan yang dapat ditolak.');
                }

                $rejectedCount = 0;
                foreach ($reports as $report) {
                    $report->update([
                        'status' => 'rejected',
                        'rejected_by' => auth()->id(),
                        'rejected_at' => now(),
                        'rejection_reason' => $rejectionReason
                    ]);

                    // Create notification for each cooperative admin
                    $this->notificationService->createNotification(
                        $report->created_by,
                        $report->cooperative_id,
                        'report_rejected',
                        'Laporan Ditolak',
                        "Laporan {$report->report_type} tahun {$report->reporting_year} ditolak. Alasan: {$rejectionReason}"
                    );

                    $this->auditLogService->log(
                        'report_rejected',
                        'Laporan keuangan ditolak (bulk)',
                        [
                            'report_id' => $report->id,
                            'report_type' => $report->report_type,
                            'reporting_year' => $report->reporting_year,
                            'cooperative_id' => $report->cooperative_id,
                            'rejection_reason' => $rejectionReason
                        ]
                    );

                    $rejectedCount++;
                }

                return redirect()->back()
                    ->with('success', "{$rejectedCount} laporan berhasil ditolak.");
            });
        } catch (\Exception $e) {
            Log::error('Error bulk rejecting reports', [
                'user_id' => auth()->id(),
                'report_ids' => $request->input('report_ids'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal menolak laporan: ' . $e->getMessage());
        }
    }
}
