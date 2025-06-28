<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\IncomeStatementRequest;
use App\Models\Financial\IncomeStatementAccount;
use App\Models\Financial\FinancialReport;
use App\Services\Financial\IncomeStatementService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class IncomeStatementController extends Controller
{
    public function __construct(
        private IncomeStatementService $incomeStatementService,
        private NotificationService $notificationService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_koperasi|admin_dinas');
        $this->middleware('can:view_income_statement')->only(['index', 'show']);
        $this->middleware('can:create_income_statement')->only(['create', 'store']);
        $this->middleware('can:edit_income_statement')->only(['edit', 'update']);
        $this->middleware('can:delete_income_statement')->only(['destroy']);
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

                $accounts = IncomeStatementAccount::where('cooperative_id', $cooperativeId)
                    ->where('reporting_year', $year)
                    ->orderBy('sort_order')
                    ->orderBy('account_code')
                    ->get()
                    ->groupBy('account_category');

                $report = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', 'income_statement')
                    ->where('reporting_year', $year)
                    ->first();
            } else {
                $cooperativeId = auth()->user()->cooperative_id;

                $accounts = IncomeStatementAccount::forCurrentUser()
                    ->where('reporting_year', $year)
                    ->orderBy('sort_order')
                    ->orderBy('account_code')
                    ->get()
                    ->groupBy('account_category');

                $report = FinancialReport::forCurrentUser()
                    ->where('report_type', 'income_statement')
                    ->where('reporting_year', $year)
                    ->first();
            }

            $previousYearData = $this->incomeStatementService->getPreviousYearData($cooperativeId, $year - 1);

            return view('financial.income-statement.index', compact(
                'accounts',
                'report',
                'cooperativeId',
                'year',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading income statement index', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Terjadi kesalahan saat memuat data laporan perhitungan hasil usaha.');
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
                    ->where('report_type', 'income_statement')
                    ->where('reporting_year', $year)
                    ->first();
            } else {
                $cooperativeId = auth()->user()->cooperative_id;

                $existingReport = FinancialReport::forCurrentUser()
                    ->where('report_type', 'income_statement')
                    ->where('reporting_year', $year)
                    ->first();
            }

            if ($existingReport && !$existingReport->canBeEdited()) {
                return redirect()->route('financial.income-statement.index')
                    ->with('error', 'Laporan sudah ada dan tidak dapat diedit.');
            }

            $defaultAccounts = $this->incomeStatementService->getDefaultAccountStructure();
            $previousYearData = $this->incomeStatementService->getPreviousYearData($cooperativeId, $year - 1);

            return view('financial.income-statement.create', compact(
                'cooperativeId',
                'year',
                'defaultAccounts',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading income statement create form', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.income-statement.index')
                ->with('error', 'Terjadi kesalahan saat memuat form laporan perhitungan hasil usaha.');
        }
    }

    public function store(IncomeStatementRequest $request)
    {
        try {
            $report = $this->incomeStatementService->createIncomeStatement(
                $request->validated(),
                auth()->id()
            );

            return redirect()->route('financial.income-statement.index', [
                'cooperative_id' => $report->cooperative_id,
                'year' => $report->reporting_year
            ])->with('success', 'Laporan Perhitungan Hasil Usaha berhasil disimpan.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in income statement creation', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'data' => $request->validated()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in income statement creation', [
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

            $accounts = IncomeStatementAccount::where('cooperative_id', $report->cooperative_id)
                ->where('reporting_year', $report->reporting_year)
                ->orderBy('sort_order')
                ->orderBy('account_code')
                ->get()
                ->groupBy('account_category');

            $previousYearData = $this->incomeStatementService->getPreviousYearData(
                $report->cooperative_id,
                $report->reporting_year - 1
            );

            $totals = $this->incomeStatementService->calculateTotals($accounts);

            return view('financial.income-statement.show', compact(
                'report',
                'accounts',
                'previousYearData',
                'totals'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading income statement show', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.income-statement.index')
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
                return redirect()->route('financial.income-statement.show', $report)
                    ->with('error', 'Laporan tidak dapat diedit.');
            }

            $accounts = IncomeStatementAccount::where('cooperative_id', $report->cooperative_id)
                ->where('reporting_year', $report->reporting_year)
                ->orderBy('sort_order')
                ->orderBy('account_code')
                ->get()
                ->groupBy('account_category');

            $previousYearData = $this->incomeStatementService->getPreviousYearData(
                $report->cooperative_id,
                $report->reporting_year - 1
            );

            return view('financial.income-statement.edit', compact(
                'report',
                'accounts',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading income statement edit form', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.income-statement.show', $report)
                ->with('error', 'Terjadi kesalahan saat memuat form edit laporan.');
        }
    }

    public function update(IncomeStatementRequest $request, FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            if (!$report->canBeEdited()) {
                return redirect()->route('financial.income-statement.show', $report)
                    ->with('error', 'Laporan tidak dapat diedit.');
            }

            $this->incomeStatementService->updateIncomeStatement(
                $report,
                $request->validated()
            );

            return redirect()->route('financial.income-statement.show', $report)
                ->with('success', 'Laporan Perhitungan Hasil Usaha berhasil diperbarui.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in income statement update', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'sql' => $e->getSql()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in income statement update', [
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
            Log::error('Error submitting income statement', [
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
            Log::error('Error approving income statement', [
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
            Log::error('Error rejecting income statement', [
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

            IncomeStatementAccount::where('cooperative_id', $report->cooperative_id)
                ->where('reporting_year', $report->reporting_year)
                ->delete();

            $report->delete();

            return redirect()->route('financial.income-statement.index')
                ->with('success', 'Laporan berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting income statement', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menghapus laporan. Silakan coba lagi.');
        }
    }
}
