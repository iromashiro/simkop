<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\BudgetPlanRequest;
use App\Models\Financial\BudgetPlan;
use App\Models\Financial\FinancialReport;
use App\Services\Financial\BudgetPlanService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class BudgetPlanController extends Controller
{
    public function __construct(
        private BudgetPlanService $budgetPlanService,
        private NotificationService $notificationService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_koperasi|admin_dinas');
        $this->middleware('can:view_budget_plan')->only(['index', 'show']);
        $this->middleware('can:create_budget_plan')->only(['create', 'store']);
        $this->middleware('can:edit_budget_plan')->only(['edit', 'update']);
        $this->middleware('can:delete_budget_plan')->only(['destroy']);
        $this->middleware('throttle:financial-reports')->only(['store', 'update', 'submit']);
    }

    public function index(Request $request)
    {
        try {
            $year = $request->get('year', date('Y') + 1); // Budget is usually for next year

            if (auth()->user()->isAdminDinas()) {
                $cooperativeId = $request->get('cooperative_id');
                if (!$cooperativeId) {
                    return redirect()->route('admin.cooperatives.index')
                        ->with('info', 'Pilih koperasi untuk melihat laporan.');
                }

                $budgetItems = BudgetPlan::where('cooperative_id', $cooperativeId)
                    ->where('budget_year', $year)
                    ->orderBy('item_category')
                    ->orderBy('sort_order')
                    ->get()
                    ->groupBy('item_category');

                $report = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', 'budget_plan')
                    ->where('reporting_year', $year)
                    ->first();
            } else {
                $cooperativeId = auth()->user()->cooperative_id;

                $budgetItems = BudgetPlan::forCurrentUser()
                    ->where('budget_year', $year)
                    ->orderBy('item_category')
                    ->orderBy('sort_order')
                    ->get()
                    ->groupBy('item_category');

                $report = FinancialReport::forCurrentUser()
                    ->where('report_type', 'budget_plan')
                    ->where('reporting_year', $year)
                    ->first();
            }

            $previousYearData = $this->budgetPlanService->getPreviousYearData($cooperativeId, $year - 1);

            return view('financial.budget-plan.index', compact(
                'budgetItems',
                'report',
                'cooperativeId',
                'year',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading budget plan index', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Terjadi kesalahan saat memuat data rencana anggaran.');
        }
    }

    public function create(Request $request)
    {
        try {
            $year = $request->get('year', date('Y') + 1);

            if (auth()->user()->isAdminDinas()) {
                $cooperativeId = $request->get('cooperative_id');
                if (!$cooperativeId) {
                    return redirect()->route('admin.cooperatives.index')
                        ->with('info', 'Pilih koperasi untuk membuat laporan.');
                }

                $existingReport = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', 'budget_plan')
                    ->where('reporting_year', $year)
                    ->first();
            } else {
                $cooperativeId = auth()->user()->cooperative_id;

                $existingReport = FinancialReport::forCurrentUser()
                    ->where('report_type', 'budget_plan')
                    ->where('reporting_year', $year)
                    ->first();
            }

            if ($existingReport && !$existingReport->canBeEdited()) {
                return redirect()->route('financial.budget-plan.index')
                    ->with('error', 'Laporan sudah ada dan tidak dapat diedit.');
            }

            $defaultStructure = $this->budgetPlanService->getDefaultStructure();
            $previousYearData = $this->budgetPlanService->getPreviousYearData($cooperativeId, $year - 1);

            return view('financial.budget-plan.create', compact(
                'cooperativeId',
                'year',
                'defaultStructure',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading budget plan create form', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.budget-plan.index')
                ->with('error', 'Terjadi kesalahan saat memuat form rencana anggaran.');
        }
    }

    public function store(BudgetPlanRequest $request)
    {
        try {
            $report = $this->budgetPlanService->createBudgetPlan(
                $request->validated(),
                auth()->id()
            );

            return redirect()->route('financial.budget-plan.index', [
                'cooperative_id' => $report->cooperative_id,
                'year' => $report->reporting_year
            ])->with('success', 'Rencana Anggaran Pendapatan & Belanja berhasil disimpan.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in budget plan creation', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'data' => $request->validated()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in budget plan creation', [
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

            $budgetItems = BudgetPlan::where('cooperative_id', $report->cooperative_id)
                ->where('budget_year', $report->reporting_year)
                ->orderBy('item_category')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('item_category');

            $previousYearData = $this->budgetPlanService->getPreviousYearData(
                $report->cooperative_id,
                $report->reporting_year - 1
            );

            $totals = $this->budgetPlanService->calculateTotals($budgetItems);

            return view('financial.budget-plan.show', compact(
                'report',
                'budgetItems',
                'previousYearData',
                'totals'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading budget plan show', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.budget-plan.index')
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
                return redirect()->route('financial.budget-plan.show', $report)
                    ->with('error', 'Laporan tidak dapat diedit.');
            }

            $budgetItems = BudgetPlan::where('cooperative_id', $report->cooperative_id)
                ->where('budget_year', $report->reporting_year)
                ->orderBy('item_category')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('item_category');

            $previousYearData = $this->budgetPlanService->getPreviousYearData(
                $report->cooperative_id,
                $report->reporting_year - 1
            );

            return view('financial.budget-plan.edit', compact(
                'report',
                'budgetItems',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading budget plan edit form', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.budget-plan.show', $report)
                ->with('error', 'Terjadi kesalahan saat memuat form edit laporan.');
        }
    }

    public function update(BudgetPlanRequest $request, FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            if (!$report->canBeEdited()) {
                return redirect()->route('financial.budget-plan.show', $report)
                    ->with('error', 'Laporan tidak dapat diedit.');
            }

            $this->budgetPlanService->updateBudgetPlan(
                $report,
                $request->validated()
            );

            return redirect()->route('financial.budget-plan.show', $report)
                ->with('success', 'Rencana Anggaran Pendapatan & Belanja berhasil diperbarui.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in budget plan update', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'sql' => $e->getSql()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in budget plan update', [
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
            Log::error('Error submitting budget plan', [
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
            Log::error('Error approving budget plan', [
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
            Log::error('Error rejecting budget plan', [
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

            BudgetPlan::where('cooperative_id', $report->cooperative_id)
                ->where('budget_year', $report->reporting_year)
                ->delete();

            $report->delete();

            return redirect()->route('financial.budget-plan.index')
                ->with('success', 'Laporan berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting budget plan', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menghapus laporan. Silakan coba lagi.');
        }
    }
}
