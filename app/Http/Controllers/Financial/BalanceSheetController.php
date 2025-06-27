<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\BalanceSheetRequest;
use App\Models\Financial\BalanceSheetAccount;
use App\Models\Financial\FinancialReport;
use App\Services\Financial\BalanceSheetService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class BalanceSheetController extends Controller
{
    public function __construct(
        private BalanceSheetService $balanceSheetService,
        private NotificationService $notificationService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_koperasi|admin_dinas');
        $this->middleware('can:view_balance_sheet')->only(['index', 'show']);
        $this->middleware('can:create_balance_sheet')->only(['create', 'store']);
        $this->middleware('can:edit_balance_sheet')->only(['edit', 'update']);
        $this->middleware('can:delete_balance_sheet')->only(['destroy']);
    }

    public function index(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));

            // ✅ CRITICAL FIX: Use existing scope methods correctly
            if (auth()->user()->isAdminDinas()) {
                $cooperativeId = $request->get('cooperative_id');
                if (!$cooperativeId) {
                    return redirect()->route('admin.cooperatives.index')
                        ->with('info', 'Pilih koperasi untuk melihat laporan.');
                }

                $accounts = BalanceSheetAccount::byCooperative($cooperativeId)
                    ->byYear($year)
                    ->scopeOrdered()
                    ->get()
                    ->groupBy('account_category');

                $report = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', 'balance_sheet')
                    ->where('reporting_year', $year)
                    ->first();
            } else {
                $cooperativeId = auth()->user()->cooperative_id;

                $accounts = BalanceSheetAccount::forCurrentUser()
                    ->byYear($year)
                    ->scopeOrdered()
                    ->get()
                    ->groupBy('account_category');

                $report = FinancialReport::forCurrentUser()
                    ->where('report_type', 'balance_sheet')
                    ->where('reporting_year', $year)
                    ->first();
            }

            $previousYearData = $this->balanceSheetService->getPreviousYearData($cooperativeId, $year - 1);

            return view('financial.balance-sheet.index', compact(
                'accounts',
                'report',
                'cooperativeId',
                'year',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading balance sheet index', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Terjadi kesalahan saat memuat data laporan posisi keuangan.');
        }
    }

    public function create(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));

            // ✅ CRITICAL FIX: Use existing scope methods correctly
            if (auth()->user()->isAdminDinas()) {
                $cooperativeId = $request->get('cooperative_id');
                if (!$cooperativeId) {
                    return redirect()->route('admin.cooperatives.index')
                        ->with('info', 'Pilih koperasi untuk membuat laporan.');
                }

                $existingReport = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', 'balance_sheet')
                    ->where('reporting_year', $year)
                    ->first();
            } else {
                $cooperativeId = auth()->user()->cooperative_id;

                $existingReport = FinancialReport::forCurrentUser()
                    ->where('report_type', 'balance_sheet')
                    ->where('reporting_year', $year)
                    ->first();
            }

            if ($existingReport && !$existingReport->canBeEdited()) {
                return redirect()->route('financial.balance-sheet.index')
                    ->with('error', 'Laporan sudah ada dan tidak dapat diedit.');
            }

            $defaultAccounts = $this->balanceSheetService->getDefaultAccountStructure();
            $previousYearData = $this->balanceSheetService->getPreviousYearData($cooperativeId, $year - 1);

            return view('financial.balance-sheet.create', compact(
                'cooperativeId',
                'year',
                'defaultAccounts',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading balance sheet create form', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.balance-sheet.index')
                ->with('error', 'Terjadi kesalahan saat memuat form laporan posisi keuangan.');
        }
    }

    // Rest of the methods remain the same...
    public function store(BalanceSheetRequest $request)
    {
        try {
            $report = $this->balanceSheetService->createBalanceSheet(
                $request->validated(),
                auth()->id()
            );

            return redirect()->route('financial.balance-sheet.index', [
                'cooperative_id' => $report->cooperative_id,
                'year' => $report->reporting_year
            ])->with('success', 'Laporan Posisi Keuangan berhasil disimpan.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in balance sheet creation', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'data' => $request->validated()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in balance sheet creation', [
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

            // ✅ CRITICAL FIX: Use explicit cooperative filter for show method
            $accounts = BalanceSheetAccount::byCooperative($report->cooperative_id)
                ->byYear($report->reporting_year)
                ->ordered()
                ->get()
                ->groupBy('account_category');

            $previousYearData = $this->balanceSheetService->getPreviousYearData(
                $report->cooperative_id,
                $report->reporting_year - 1
            );

            $totals = $this->balanceSheetService->calculateTotals($accounts);

            return view('financial.balance-sheet.show', compact(
                'report',
                'accounts',
                'previousYearData',
                'totals'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading balance sheet show', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.balance-sheet.index')
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
                return redirect()->route('financial.balance-sheet.show', $report)
                    ->with('error', 'Laporan tidak dapat diedit.');
            }

            // ✅ CRITICAL FIX: Use explicit cooperative filter for edit method
            $accounts = BalanceSheetAccount::byCooperative($report->cooperative_id)
                ->byYear($report->reporting_year)
                ->ordered()
                ->get()
                ->groupBy('account_category');

            $previousYearData = $this->balanceSheetService->getPreviousYearData(
                $report->cooperative_id,
                $report->reporting_year - 1
            );

            return view('financial.balance-sheet.edit', compact(
                'report',
                'accounts',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading balance sheet edit form', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.balance-sheet.show', $report)
                ->with('error', 'Terjadi kesalahan saat memuat form edit laporan.');
        }
    }

    public function update(BalanceSheetRequest $request, FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            if (!$report->canBeEdited()) {
                return redirect()->route('financial.balance-sheet.show', $report)
                    ->with('error', 'Laporan tidak dapat diedit.');
            }

            $this->balanceSheetService->updateBalanceSheet(
                $report,
                $request->validated()
            );

            return redirect()->route('financial.balance-sheet.show', $report)
                ->with('success', 'Laporan Posisi Keuangan berhasil diperbarui.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in balance sheet update', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'sql' => $e->getSql()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in balance sheet update', [
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

            $this->notificationService->reportSubmitted(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year
            );

            return back()->with('success', 'Laporan berhasil diajukan untuk persetujuan.');
        } catch (\Exception $e) {
            Log::error('Error submitting balance sheet', [
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

            $this->notificationService->reportApproved(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year
            );

            return back()->with('success', 'Laporan berhasil disetujui.');
        } catch (\Exception $e) {
            Log::error('Error approving balance sheet', [
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
            Log::error('Error rejecting balance sheet', [
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

            // Delete related accounts
            BalanceSheetAccount::byCooperative($report->cooperative_id)
                ->byYear($report->reporting_year)
                ->delete();

            $report->delete();

            return redirect()->route('financial.balance-sheet.index')
                ->with('success', 'Laporan berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting balance sheet', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menghapus laporan. Silakan coba lagi.');
        }
    }
}
