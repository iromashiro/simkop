<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\BalanceSheetRequest;
use App\Models\Financial\BalanceSheetAccount;
use App\Models\Financial\FinancialReport;
use App\Services\Financial\BalanceSheetService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

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
        $cooperativeId = auth()->user()->isAdminDinas()
            ? $request->get('cooperative_id', auth()->user()->cooperative_id)
            : auth()->user()->cooperative_id;

        $year = $request->get('year', date('Y'));

        $accounts = BalanceSheetAccount::byCooperative($cooperativeId)
            ->byYear($year)
            ->ordered()
            ->get()
            ->groupBy('account_category');

        $report = FinancialReport::byCooperative($cooperativeId)
            ->byType('balance_sheet')
            ->byYear($year)
            ->first();

        $previousYearData = $this->balanceSheetService->getPreviousYearData($cooperativeId, $year - 1);

        return view('financial.balance-sheet.index', compact(
            'accounts',
            'report',
            'cooperativeId',
            'year',
            'previousYearData'
        ));
    }

    public function create(Request $request)
    {
        $cooperativeId = auth()->user()->isAdminDinas()
            ? $request->get('cooperative_id', auth()->user()->cooperative_id)
            : auth()->user()->cooperative_id;

        $year = $request->get('year', date('Y'));

        // Check if report already exists
        $existingReport = FinancialReport::byCooperative($cooperativeId)
            ->byType('balance_sheet')
            ->byYear($year)
            ->first();

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
    }

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
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Gagal menyimpan laporan: ' . $e->getMessage());
        }
    }

    public function show(FinancialReport $report)
    {
        if (!auth()->user()->canAccessCooperative($report->cooperative_id)) {
            abort(403);
        }

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
    }

    public function edit(FinancialReport $report)
    {
        if (!auth()->user()->canAccessCooperative($report->cooperative_id)) {
            abort(403);
        }

        if (!$report->canBeEdited()) {
            return redirect()->route('financial.balance-sheet.show', $report)
                ->with('error', 'Laporan tidak dapat diedit.');
        }

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
    }

    public function update(BalanceSheetRequest $request, FinancialReport $report)
    {
        if (!auth()->user()->canAccessCooperative($report->cooperative_id)) {
            abort(403);
        }

        if (!$report->canBeEdited()) {
            return redirect()->route('financial.balance-sheet.show', $report)
                ->with('error', 'Laporan tidak dapat diedit.');
        }

        try {
            $this->balanceSheetService->updateBalanceSheet(
                $report,
                $request->validated()
            );

            return redirect()->route('financial.balance-sheet.show', $report)
                ->with('success', 'Laporan Posisi Keuangan berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Gagal memperbarui laporan: ' . $e->getMessage());
        }
    }

    public function submit(FinancialReport $report)
    {
        if (!auth()->user()->canAccessCooperative($report->cooperative_id)) {
            abort(403);
        }

        if (!$report->canBeSubmitted()) {
            return back()->with('error', 'Laporan tidak dapat diajukan.');
        }

        try {
            $report->submit();

            $this->notificationService->reportSubmitted(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year
            );

            return back()->with('success', 'Laporan berhasil diajukan untuk persetujuan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengajukan laporan: ' . $e->getMessage());
        }
    }

    public function approve(Request $request, FinancialReport $report)
    {
        if (!auth()->user()->isAdminDinas()) {
            abort(403);
        }

        if (!$report->canBeApproved()) {
            return back()->with('error', 'Laporan tidak dapat disetujui.');
        }

        try {
            $report->approve(auth()->id());

            $this->notificationService->reportApproved(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year
            );

            return back()->with('success', 'Laporan berhasil disetujui.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menyetujui laporan: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, FinancialReport $report)
    {
        if (!auth()->user()->isAdminDinas()) {
            abort(403);
        }

        if (!$report->canBeApproved()) {
            return back()->with('error', 'Laporan tidak dapat ditolak.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:1000'
        ]);

        try {
            $report->reject(auth()->id(), $request->rejection_reason);

            $this->notificationService->reportRejected(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year,
                $request->rejection_reason
            );

            return back()->with('success', 'Laporan berhasil ditolak.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menolak laporan: ' . $e->getMessage());
        }
    }

    public function destroy(FinancialReport $report)
    {
        if (!auth()->user()->canAccessCooperative($report->cooperative_id)) {
            abort(403);
        }

        if (!$report->isDraft()) {
            return back()->with('error', 'Hanya laporan draft yang dapat dihapus.');
        }

        try {
            // Delete related accounts
            BalanceSheetAccount::byCooperative($report->cooperative_id)
                ->byYear($report->reporting_year)
                ->delete();

            $report->delete();

            return redirect()->route('financial.balance-sheet.index')
                ->with('success', 'Laporan berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus laporan: ' . $e->getMessage());
        }
    }
}
