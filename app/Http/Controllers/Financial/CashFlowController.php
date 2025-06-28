<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\CashFlowRequest;
use App\Models\Financial\CashFlowActivity;
use App\Models\Financial\FinancialReport;
use App\Services\Financial\CashFlowService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class CashFlowController extends Controller
{
    public function __construct(
        private CashFlowService $cashFlowService,
        private NotificationService $notificationService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_koperasi|admin_dinas');
        $this->middleware('can:view_cash_flow')->only(['index', 'show']);
        $this->middleware('can:create_cash_flow')->only(['create', 'store']);
        $this->middleware('can:edit_cash_flow')->only(['edit', 'update']);
        $this->middleware('can:delete_cash_flow')->only(['destroy']);
        $this->middleware('throttle:financial-reports')->only(['store', 'update', 'submit']);
    }

    public function index(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));

            if (auth()->user()->isAdminDinas()) {
                $cooperativeId = $request->get('cooperative_id');
                if (!$cooperativeId) {
                    return redirect()->route('admin.cooperatives.index')
                        ->with('info', 'Pilih koperasi untuk melihat laporan.');
                }

                $activities = CashFlowActivity::where('cooperative_id', $cooperativeId)
                    ->where('reporting_year', $year)
                    ->orderBy('activity_category')
                    ->orderBy('sort_order')
                    ->get()
                    ->groupBy('activity_category');

                $report = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', 'cash_flow')
                    ->where('reporting_year', $year)
                    ->first();
            } else {
                $cooperativeId = auth()->user()->cooperative_id;

                $activities = CashFlowActivity::forCurrentUser()
                    ->where('reporting_year', $year)
                    ->orderBy('activity_category')
                    ->orderBy('sort_order')
                    ->get()
                    ->groupBy('activity_category');

                $report = FinancialReport::forCurrentUser()
                    ->where('report_type', 'cash_flow')
                    ->where('reporting_year', $year)
                    ->first();
            }

            $previousYearData = $this->cashFlowService->getPreviousYearData($cooperativeId, $year - 1);

            return view('financial.cash-flow.index', compact(
                'activities',
                'report',
                'cooperativeId',
                'year',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading cash flow index', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Terjadi kesalahan saat memuat data laporan arus kas.');
        }
    }

    public function create(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));

            if (auth()->user()->isAdminDinas()) {
                $cooperativeId = $request->get('cooperative_id');
                if (!$cooperativeId) {
                    return redirect()->route('admin.cooperatives.index')
                        ->with('info', 'Pilih koperasi untuk membuat laporan.');
                }

                $existingReport = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', 'cash_flow')
                    ->where('reporting_year', $year)
                    ->first();
            } else {
                $cooperativeId = auth()->user()->cooperative_id;

                $existingReport = FinancialReport::forCurrentUser()
                    ->where('report_type', 'cash_flow')
                    ->where('reporting_year', $year)
                    ->first();
            }

            if ($existingReport && !$existingReport->canBeEdited()) {
                return redirect()->route('financial.cash-flow.index')
                    ->with('error', 'Laporan sudah ada dan tidak dapat diedit.');
            }

            $defaultStructure = $this->cashFlowService->getDefaultStructure();
            $previousYearData = $this->cashFlowService->getPreviousYearData($cooperativeId, $year - 1);

            return view('financial.cash-flow.create', compact(
                'cooperativeId',
                'year',
                'defaultStructure',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading cash flow create form', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.cash-flow.index')
                ->with('error', 'Terjadi kesalahan saat memuat form laporan arus kas.');
        }
    }

    public function store(CashFlowRequest $request)
    {
        try {
            $report = $this->cashFlowService->createCashFlow(
                $request->validated(),
                auth()->id()
            );

            return redirect()->route('financial.cash-flow.index', [
                'cooperative_id' => $report->cooperative_id,
                'year' => $report->reporting_year
            ])->with('success', 'Laporan Arus Kas berhasil disimpan.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in cash flow creation', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'data' => $request->validated()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in cash flow creation', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan sistem. Tim teknis telah diberitahu.');
        }
    }

    public function show(FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            $activities = CashFlowActivity::where('cooperative_id', $report->cooperative_id)
                ->where('reporting_year', $report->reporting_year)
                ->orderBy('activity_category')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('activity_category');

            $previousYearData = $this->cashFlowService->getPreviousYearData(
                $report->cooperative_id,
                $report->reporting_year - 1
            );

            $totals = $this->cashFlowService->calculateTotals($activities);

            return view('financial.cash-flow.show', compact(
                'report',
                'activities',
                'previousYearData',
                'totals'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading cash flow show', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.cash-flow.index')
                ->with('error', 'Terjadi kesalahan saat memuat detail laporan.');
        }
    }

    public function edit(FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            if (!$report->canBeEdited()) {
                return redirect()->route('financial.cash-flow.show', $report)
                    ->with('error', 'Laporan tidak dapat diedit.');
            }

            $activities = CashFlowActivity::where('cooperative_id', $report->cooperative_id)
                ->where('reporting_year', $report->reporting_year)
                ->orderBy('activity_category')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('activity_category');

            $previousYearData = $this->cashFlowService->getPreviousYearData(
                $report->cooperative_id,
                $report->reporting_year - 1
            );

            return view('financial.cash-flow.edit', compact(
                'report',
                'activities',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading cash flow edit form', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.cash-flow.show', $report)
                ->with('error', 'Terjadi kesalahan saat memuat form edit laporan.');
        }
    }

    public function update(CashFlowRequest $request, FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            if (!$report->canBeEdited()) {
                return redirect()->route('financial.cash-flow.show', $report)
                    ->with('error', 'Laporan tidak dapat diedit.');
            }

            $this->cashFlowService->updateCashFlow(
                $report,
                $request->validated()
            );

            return redirect()->route('financial.cash-flow.show', $report)
                ->with('success', 'Laporan Arus Kas berhasil diperbarui.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in cash flow update', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'sql' => $e->getSql()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in cash flow update', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan sistem. Tim teknis telah diberitahu.');
        }
    }

    public function submit(FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            if (!$report->canBeSubmitted()) {
                return back()->with('error', 'Laporan tidak dapat diajukan.');
            }

            $report->submit();

            Event::dispatch('financial.report.submitted', (object) [
                'cooperativeId' => $report->cooperative_id,
                'reportType' => $report->report_type,
                'reportingYear' => $report->reporting_year,
                'submittedBy' => auth()->id(),
                'submittedAt' => now(),
            ]);

            $this->notificationService->reportSubmitted(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year
            );

            return back()->with('success', 'Laporan berhasil diajukan untuk persetujuan.');
        } catch (\Exception $e) {
            Log::error('Error submitting cash flow', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal mengajukan laporan. Silakan coba lagi.');
        }
    }

    public function approve(Request $request, FinancialReport $report)
    {
        try {
            if (!auth()->user()->isAdminDinas()) {
                abort(403, 'Hanya Admin Dinas yang dapat menyetujui laporan.');
            }

            if (!$report->canBeApproved()) {
                return back()->with('error', 'Laporan tidak dapat disetujui.');
            }

            $report->approve(auth()->id());

            Event::dispatch('financial.report.approved', (object) [
                'cooperativeId' => $report->cooperative_id,
                'reportType' => $report->report_type,
                'reportingYear' => $report->reporting_year,
                'approvedBy' => auth()->id(),
                'approvedAt' => now(),
            ]);

            $this->notificationService->reportApproved(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year
            );

            return back()->with('success', 'Laporan berhasil disetujui.');
        } catch (\Exception $e) {
            Log::error('Error approving cash flow', [
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
            if (!auth()->user()->isAdminDinas()) {
                abort(403, 'Hanya Admin Dinas yang dapat menolak laporan.');
            }

            if (!$report->canBeApproved()) {
                return back()->with('error', 'Laporan tidak dapat ditolak.');
            }

            $request->validate([
                'rejection_reason' => 'required|string|max:1000'
            ]);

            $report->reject(auth()->id(), $request->rejection_reason);

            Event::dispatch('financial.report.rejected', (object) [
                'cooperativeId' => $report->cooperative_id,
                'reportType' => $report->report_type,
                'reportingYear' => $report->reporting_year,
                'rejectionReason' => $request->rejection_reason,
                'rejectedBy' => auth()->id(),
                'rejectedAt' => now(),
            ]);

            $this->notificationService->reportRejected(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year,
                $request->rejection_reason
            );

            return back()->with('success', 'Laporan berhasil ditolak.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Error rejecting cash flow', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menolak laporan. Silakan coba lagi.');
        }
    }

    public function destroy(FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            if (!$report->isDraft()) {
                return back()->with('error', 'Hanya laporan draft yang dapat dihapus.');
            }

            CashFlowActivity::where('cooperative_id', $report->cooperative_id)
                ->where('reporting_year', $report->reporting_year)
                ->delete();

            $report->delete();

            return redirect()->route('financial.cash-flow.index')
                ->with('success', 'Laporan berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting cash flow', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menghapus laporan. Silakan coba lagi.');
        }
    }
}
