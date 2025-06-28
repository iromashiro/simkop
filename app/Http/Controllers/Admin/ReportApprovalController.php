<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialReport;
use App\Services\NotificationService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReportApprovalController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private AuditLogService $auditLogService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_dinas');
        $this->middleware('can:approve_reports');
    }

    public function index(Request $request)
    {
        try {
            $status = $request->get('status', 'submitted');
            $type = $request->get('type');
            $year = $request->get('year');
            $cooperative = $request->get('cooperative');
            $perPage = min($request->get('per_page', 15), 50);

            $reports = FinancialReport::with(['cooperative:id,name', 'creator:id,name'])
                ->when($status, function ($query, $status) {
                    $query->where('status', $status);
                })
                ->when($type, function ($query, $type) {
                    $query->where('report_type', $type);
                })
                ->when($year, function ($query, $year) {
                    $query->where('reporting_year', $year);
                })
                ->when($cooperative, function ($query, $cooperative) {
                    $query->where('cooperative_id', $cooperative);
                })
                ->orderBy('submitted_at', 'asc')
                ->paginate($perPage);

            $cooperatives = \App\Models\Cooperative::select('id', 'name')->orderBy('name')->get();
            $reportTypes = FinancialReport::select('report_type')->distinct()->pluck('report_type');
            $years = FinancialReport::selectRaw('DISTINCT reporting_year')->orderBy('reporting_year', 'desc')->pluck('reporting_year');

            return view('admin.report-approval.index', compact(
                'reports',
                'cooperatives',
                'reportTypes',
                'years',
                'status',
                'type',
                'year',
                'cooperative'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading report approval index', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.dashboard')
                ->with('error', 'Terjadi kesalahan saat memuat data persetujuan laporan.');
        }
    }

    public function show(FinancialReport $report)
    {
        try {
            $report->load(['cooperative:id,name,code', 'creator:id,name', 'approver:id,name']);

            // Get related data based on report type
            $relatedData = $this->getRelatedData($report);

            return view('admin.report-approval.show', compact('report', 'relatedData'));
        } catch (\Exception $e) {
            Log::error('Error loading report approval details', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.report-approval.index')
                ->with('error', 'Terjadi kesalahan saat memuat detail laporan.');
        }
    }

    public function approve(FinancialReport $report)
    {
        try {
            if (!$report->canBeApproved()) {
                return back()->with('error', 'Laporan tidak dapat disetujui.');
            }

            $report->approve(auth()->id());

            // Send notification
            $this->notificationService->reportApproved(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year
            );

            // Log approval
            $this->auditLogService->log(
                'report_approved',
                'FinancialReport',
                $report->id,
                [
                    'report_type' => $report->report_type,
                    'reporting_year' => $report->reporting_year,
                    'cooperative_id' => $report->cooperative_id,
                ],
                auth()->id()
            );

            return back()->with('success', 'Laporan berhasil disetujui.');
        } catch (\Exception $e) {
            Log::error('Error approving report', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menyetujui laporan. Silakan coba lagi.');
        }
    }

    public function reject(Request $request, FinancialReport $report)
    {
        try {
            if (!$report->canBeApproved()) {
                return back()->with('error', 'Laporan tidak dapat ditolak.');
            }

            $request->validate([
                'rejection_reason' => 'required|string|max:1000'
            ]);

            $report->reject(auth()->id(), $request->rejection_reason);

            // Send notification
            $this->notificationService->reportRejected(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year,
                $request->rejection_reason
            );

            // Log rejection
            $this->auditLogService->log(
                'report_rejected',
                'FinancialReport',
                $report->id,
                [
                    'report_type' => $report->report_type,
                    'reporting_year' => $report->reporting_year,
                    'cooperative_id' => $report->cooperative_id,
                    'rejection_reason' => $request->rejection_reason,
                ],
                auth()->id()
            );

            return back()->with('success', 'Laporan berhasil ditolak.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Error rejecting report', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menolak laporan. Silakan coba lagi.');
        }
    }

    public function bulkApprove(Request $request)
    {
        try {
            $request->validate([
                'report_ids' => 'required|array|min:1',
                'report_ids.*' => 'exists:financial_reports,id'
            ]);

            $reports = FinancialReport::whereIn('id', $request->report_ids)
                ->where('status', 'submitted')
                ->get();

            $approvedCount = 0;
            foreach ($reports as $report) {
                if ($report->canBeApproved()) {
                    $report->approve(auth()->id());

                    // Send notification
                    $this->notificationService->reportApproved(
                        $report->cooperative_id,
                        $report->report_type,
                        $report->reporting_year
                    );

                    $approvedCount++;
                }
            }

            // Log bulk approval
            $this->auditLogService->log(
                'reports_bulk_approved',
                'FinancialReport',
                null,
                [
                    'report_ids' => $request->report_ids,
                    'approved_count' => $approvedCount,
                ],
                auth()->id()
            );

            return back()->with('success', "{$approvedCount} laporan berhasil disetujui.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Error in bulk approval', [
                'user_id' => auth()->id(),
                'report_ids' => $request->report_ids ?? [],
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal melakukan persetujuan massal. Silakan coba lagi.');
        }
    }

    private function getRelatedData(FinancialReport $report): array
    {
        $data = [];

        switch ($report->report_type) {
            case 'balance_sheet':
                $data['accounts'] = \App\Models\Financial\BalanceSheetAccount::where('cooperative_id', $report->cooperative_id)
                    ->where('reporting_year', $report->reporting_year)
                    ->orderBy('sort_order')
                    ->get()
                    ->groupBy('account_category');
                break;

            case 'income_statement':
                $data['accounts'] = \App\Models\Financial\IncomeStatementAccount::where('cooperative_id', $report->cooperative_id)
                    ->where('reporting_year', $report->reporting_year)
                    ->orderBy('sort_order')
                    ->get()
                    ->groupBy('account_category');
                break;

            case 'equity_changes':
                $data['changes'] = \App\Models\Financial\EquityChange::where('cooperative_id', $report->cooperative_id)
                    ->where('reporting_year', $report->reporting_year)
                    ->orderBy('sort_order')
                    ->get();
                break;

            case 'member_savings':
                $data['savings'] = \App\Models\Financial\MemberSaving::where('cooperative_id', $report->cooperative_id)
                    ->where('reporting_year', $report->reporting_year)
                    ->orderBy('member_name')
                    ->get();
                break;

                // Add other report types as needed
        }

        return $data;
    }
}
