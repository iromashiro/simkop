<?php
// app/Http/Controllers/Financial/BalanceSheetController.php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialReport;
use App\Models\Financial\BalanceSheetAccount;
use App\Models\Cooperative;
use App\Services\Financial\BalanceSheetService;
use App\Services\NotificationService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class BalanceSheetController extends Controller
{
    public function __construct(
        protected BalanceSheetService $balanceSheetService,
        protected NotificationService $notificationService,
        protected AuditLogService $auditLogService
    ) {
        $this->middleware('auth');
        $this->middleware('verified');
    }

    /**
     * Display a listing of balance sheet reports
     */
    public function index(Request $request): View
    {
        // Simple role check - no policy
        if (!auth()->user()->hasAnyRole(['admin_dinas', 'admin_koperasi', 'staff_koperasi'])) {
            abort(403);
        }

        $query = FinancialReport::with(['cooperative', 'createdBy'])
            ->where('report_type', 'balance_sheet');

        // Multi-tenant filtering
        if (auth()->user()->hasRole('admin_koperasi')) {
            $query->where('cooperative_id', auth()->user()->cooperative_id);
        }

        // Apply filters
        if ($request->filled('cooperative_id')) {
            $query->where('cooperative_id', $request->cooperative_id);
        }

        if ($request->filled('reporting_year')) {
            $query->where('reporting_year', $request->reporting_year);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reports = $query->orderBy('reporting_year', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Get filter options
        $cooperatives = auth()->user()->hasRole('admin_dinas')
            ? Cooperative::orderBy('name')->get()
            : collect([auth()->user()->cooperative]);

        $years = range(date('Y'), 2020);
        $statuses = ['draft', 'submitted', 'approved', 'rejected'];

        return view('financial.balance-sheet.index', compact(
            'reports',
            'cooperatives',
            'years',
            'statuses'
        ));
    }

    /**
     * Show the form for creating a new balance sheet
     */
    public function create(): View
    {
        // Simple role check
        if (!auth()->user()->hasAnyRole(['admin_koperasi', 'staff_koperasi'])) {
            abort(403);
        }

        $cooperative = auth()->user()->hasRole('admin_koperasi')
            ? auth()->user()->cooperative
            : null;

        $cooperatives = auth()->user()->hasRole('admin_dinas')
            ? Cooperative::orderBy('name')->get()
            : collect([$cooperative]);

        $defaultAccounts = $this->balanceSheetService->getDefaultAccountStructure();
        $currentYear = date('Y');
        $years = range($currentYear, 2020);

        return view('financial.balance-sheet.create', compact(
            'cooperatives',
            'defaultAccounts',
            'currentYear',
            'years'
        ));
    }

    /**
     * Store a newly created balance sheet
     */
    public function store(Request $request): RedirectResponse
    {
        // Simple role check
        if (!auth()->user()->hasAnyRole(['admin_koperasi', 'staff_koperasi'])) {
            abort(403);
        }

        // Simple validation - no FormRequest
        $validated = $request->validate([
            'cooperative_id' => 'required|exists:cooperatives,id',
            'reporting_year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'notes' => 'nullable|string|max:1000',
            'accounts' => 'required|array',
            'accounts.assets' => 'required|array|min:1',
            'accounts.liabilities' => 'required|array|min:1',
            'accounts.equity' => 'required|array|min:1',
        ]);

        // Check cooperative access
        if (
            auth()->user()->hasRole('admin_koperasi') &&
            $validated['cooperative_id'] != auth()->user()->cooperative_id
        ) {
            abort(403);
        }

        try {
            $report = $this->balanceSheetService->createBalanceSheet(
                $validated,
                auth()->id()
            );

            $this->auditLogService->log(
                'balance_sheet_created',
                'Laporan Posisi Keuangan dibuat',
                $report,
                $validated
            );

            return redirect()
                ->route('balance-sheet.show', $report)
                ->with('success', 'Laporan Posisi Keuangan berhasil dibuat.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Gagal membuat laporan: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified balance sheet
     */
    public function show(FinancialReport $report): View
    {
        // Simple access check
        if (auth()->user()->hasRole('admin_dinas')) {
            // Admin dinas can see all
        } elseif (auth()->user()->cooperative_id === $report->cooperative_id) {
            // Cooperative users can see their own
        } else {
            abort(403);
        }

        if ($report->report_type !== 'balance_sheet') {
            abort(404);
        }

        $report->load(['cooperative', 'createdBy', 'approvedBy']);

        $accounts = $this->balanceSheetService->getBalanceSheetData(
            $report->cooperative_id,
            $report->reporting_year
        );

        $totals = $this->balanceSheetService->calculateTotals($accounts);

        $ratios = $this->balanceSheetService->getFinancialRatios(
            $report->cooperative_id,
            $report->reporting_year
        );

        $yearOverYearAnalysis = null;
        if ($report->reporting_year > 2020) {
            $yearOverYearAnalysis = $this->balanceSheetService->getYearOverYearAnalysis(
                $report->cooperative_id,
                $report->reporting_year
            );
        }

        return view('financial.balance-sheet.show', compact(
            'report',
            'accounts',
            'totals',
            'ratios',
            'yearOverYearAnalysis'
        ));
    }

    /**
     * Show the form for editing the specified balance sheet
     */
    public function edit(FinancialReport $report): View
    {
        // Simple access check
        if ($report->report_type !== 'balance_sheet' || $report->status !== 'draft') {
            abort(403, 'Laporan tidak dapat diedit.');
        }

        if (
            !auth()->user()->hasAnyRole(['admin_koperasi', 'staff_koperasi']) ||
            auth()->user()->cooperative_id !== $report->cooperative_id
        ) {
            abort(403);
        }

        $report->load(['cooperative']);

        $accounts = $this->balanceSheetService->getBalanceSheetData(
            $report->cooperative_id,
            $report->reporting_year
        );

        $years = range(date('Y'), 2020);

        return view('financial.balance-sheet.edit', compact(
            'report',
            'accounts',
            'years'
        ));
    }

    /**
     * Update the specified balance sheet
     */
    public function update(Request $request, FinancialReport $report): RedirectResponse
    {
        // Simple access check
        if ($report->report_type !== 'balance_sheet' || $report->status !== 'draft') {
            abort(403, 'Laporan tidak dapat diedit.');
        }

        if (
            !auth()->user()->hasAnyRole(['admin_koperasi', 'staff_koperasi']) ||
            auth()->user()->cooperative_id !== $report->cooperative_id
        ) {
            abort(403);
        }

        // Simple validation
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'accounts' => 'required|array',
            'accounts.assets' => 'required|array|min:1',
            'accounts.liabilities' => 'required|array|min:1',
            'accounts.equity' => 'required|array|min:1',
        ]);

        try {
            $updatedReport = $this->balanceSheetService->updateBalanceSheet(
                $report,
                $validated
            );

            $this->auditLogService->log(
                'balance_sheet_updated',
                'Laporan Posisi Keuangan diperbarui',
                $updatedReport,
                $validated
            );

            return redirect()
                ->route('balance-sheet.show', $updatedReport)
                ->with('success', 'Laporan Posisi Keuangan berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Gagal memperbarui laporan: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified balance sheet from storage
     */
    public function destroy(FinancialReport $report): RedirectResponse
    {
        // Simple access check
        if ($report->report_type !== 'balance_sheet' || $report->status !== 'draft') {
            abort(403, 'Laporan tidak dapat dihapus.');
        }

        if (
            !auth()->user()->hasRole('admin_koperasi') ||
            auth()->user()->cooperative_id !== $report->cooperative_id
        ) {
            abort(403);
        }

        try {
            // Delete related accounts first
            BalanceSheetAccount::where('financial_report_id', $report->id)->delete();

            // Delete the report
            $report->delete();

            $this->auditLogService->log(
                'balance_sheet_deleted',
                'Laporan Posisi Keuangan dihapus',
                null,
                ['report_id' => $report->id, 'cooperative_id' => $report->cooperative_id]
            );

            return redirect()
                ->route('balance-sheet.index')
                ->with('success', 'Laporan Posisi Keuangan berhasil dihapus.');
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Gagal menghapus laporan: ' . $e->getMessage());
        }
    }

    /**
     * Submit balance sheet for approval
     */
    public function submit(FinancialReport $report): RedirectResponse
    {
        // Simple access check
        if ($report->report_type !== 'balance_sheet' || $report->status !== 'draft') {
            abort(403, 'Laporan tidak dapat diajukan.');
        }

        if (
            !auth()->user()->hasRole('admin_koperasi') ||
            auth()->user()->cooperative_id !== $report->cooperative_id
        ) {
            abort(403);
        }

        try {
            $report->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => auth()->id()
            ]);

            // Send notification to admin dinas
            $this->notificationService->reportSubmitted(
                $report->cooperative_id,
                'balance_sheet',
                $report->reporting_year
            );

            $this->auditLogService->log(
                'balance_sheet_submitted',
                'Laporan Posisi Keuangan diajukan untuk persetujuan',
                $report
            );

            return redirect()
                ->route('balance-sheet.show', $report)
                ->with('success', 'Laporan berhasil diajukan untuk persetujuan.');
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Gagal mengajukan laporan: ' . $e->getMessage());
        }
    }

    /**
     * Approve balance sheet
     */
    public function approve(FinancialReport $report): RedirectResponse
    {
        // Simple role check
        if (!auth()->user()->hasRole('admin_dinas') || $report->status !== 'submitted') {
            abort(403);
        }

        if ($report->report_type !== 'balance_sheet') {
            abort(403, 'Laporan tidak dapat disetujui.');
        }

        try {
            $report->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id()
            ]);

            // Send notification to cooperative admins
            $this->notificationService->reportApproved(
                $report->cooperative_id,
                'balance_sheet',
                $report->reporting_year
            );

            $this->auditLogService->log(
                'balance_sheet_approved',
                'Laporan Posisi Keuangan disetujui',
                $report
            );

            return redirect()
                ->route('balance-sheet.show', $report)
                ->with('success', 'Laporan berhasil disetujui.');
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Gagal menyetujui laporan: ' . $e->getMessage());
        }
    }

    /**
     * Reject balance sheet
     */
    public function reject(Request $request, FinancialReport $report): RedirectResponse
    {
        // Simple role check
        if (!auth()->user()->hasRole('admin_dinas') || $report->status !== 'submitted') {
            abort(403);
        }

        // Simple validation
        $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);

        if ($report->report_type !== 'balance_sheet') {
            abort(403, 'Laporan tidak dapat ditolak.');
        }

        try {
            $report->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => auth()->id(),
                'rejection_reason' => $request->rejection_reason
            ]);

            // Send notification to cooperative admins
            $this->notificationService->reportRejected(
                $report->cooperative_id,
                'balance_sheet',
                $report->reporting_year,
                $request->rejection_reason
            );

            $this->auditLogService->log(
                'balance_sheet_rejected',
                'Laporan Posisi Keuangan ditolak',
                $report,
                ['reason' => $request->rejection_reason]
            );

            return redirect()
                ->route('balance-sheet.show', $report)
                ->with('success', 'Laporan berhasil ditolak.');
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Gagal menolak laporan: ' . $e->getMessage());
        }
    }

    /**
     * Get previous year data for AJAX
     */
    public function getPreviousYearData(Request $request): JsonResponse
    {
        $request->validate([
            'cooperative_id' => 'required|integer|exists:cooperatives,id',
            'year' => 'required|integer|min:2020'
        ]);

        // Simple access check
        if (
            auth()->user()->hasRole('admin_koperasi') &&
            $request->cooperative_id != auth()->user()->cooperative_id
        ) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $data = $this->balanceSheetService->getPreviousYearData(
                $request->cooperative_id,
                $request->year
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate balance equation for AJAX
     */
    public function validateBalance(Request $request): JsonResponse
    {
        $request->validate([
            'accounts' => 'required|array'
        ]);

        try {
            $validation = $this->balanceSheetService->validateBalanceEquation(
                $request->accounts
            );

            return response()->json([
                'success' => true,
                'validation' => $validation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
