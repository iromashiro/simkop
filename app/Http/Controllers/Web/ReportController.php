<?php
// app/Http/Controllers/Web/ReportController.php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Reporting\Services\ReportService;
use App\Domain\Accounting\Models\FiscalPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {
        $this->middleware('auth');
        $this->middleware('tenant.scope');
        $this->middleware('permission:view_reports');
    }

    public function index(): View
    {
        return view('reports.index');
    }

    public function balanceSheet(Request $request): View
    {
        $request->validate([
            'as_of_date' => 'date',
            'fiscal_period_id' => 'integer|exists:fiscal_periods,id',
        ]);

        $user = Auth::user();
        $asOfDate = $request->as_of_date ? \Carbon\Carbon::parse($request->as_of_date) : now();

        $balanceSheet = $this->reportService->generateBalanceSheet(
            $user->cooperative_id,
            $asOfDate,
            $request->fiscal_period_id
        );

        $fiscalPeriods = FiscalPeriod::where('cooperative_id', $user->cooperative_id)
            ->orderBy('start_date', 'desc')
            ->get();

        return view('reports.balance-sheet', compact('balanceSheet', 'asOfDate', 'fiscalPeriods'));
    }

    public function incomeStatement(Request $request): View
    {
        $request->validate([
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'fiscal_period_id' => 'integer|exists:fiscal_periods,id',
        ]);

        $user = Auth::user();
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : now()->startOfYear();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date) : now();

        $incomeStatement = $this->reportService->generateIncomeStatement(
            $user->cooperative_id,
            $startDate,
            $endDate,
            $request->fiscal_period_id
        );

        $fiscalPeriods = FiscalPeriod::where('cooperative_id', $user->cooperative_id)
            ->orderBy('start_date', 'desc')
            ->get();

        return view('reports.income-statement', compact('incomeStatement', 'startDate', 'endDate', 'fiscalPeriods'));
    }

    public function cashFlow(Request $request): View
    {
        $request->validate([
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'fiscal_period_id' => 'integer|exists:fiscal_periods,id',
        ]);

        $user = Auth::user();
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : now()->startOfYear();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date) : now();

        $cashFlow = $this->reportService->generateCashFlowStatement(
            $user->cooperative_id,
            $startDate,
            $endDate,
            $request->fiscal_period_id
        );

        $fiscalPeriods = FiscalPeriod::where('cooperative_id', $user->cooperative_id)
            ->orderBy('start_date', 'desc')
            ->get();

        return view('reports.cash-flow', compact('cashFlow', 'startDate', 'endDate', 'fiscalPeriods'));
    }

    public function memberReport(Request $request): View
    {
        $user = Auth::user();

        $memberReport = $this->reportService->generateMemberReport(
            $user->cooperative_id,
            $request->all()
        );

        return view('reports.member-report', compact('memberReport'));
    }

    public function savingsReport(Request $request): View
    {
        $user = Auth::user();

        $savingsReport = $this->reportService->generateSavingsReport(
            $user->cooperative_id,
            $request->all()
        );

        return view('reports.savings-report', compact('savingsReport'));
    }

    public function loanReport(Request $request): View
    {
        $user = Auth::user();

        $loanReport = $this->reportService->generateLoanReport(
            $user->cooperative_id,
            $request->all()
        );

        return view('reports.loan-report', compact('loanReport'));
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $request->validate([
            'report_type' => 'required|in:balance_sheet,income_statement,cash_flow,member,savings,loan',
            'format' => 'required|in:pdf,excel',
        ]);

        $user = Auth::user();

        return $this->reportService->exportReport(
            $user->cooperative_id,
            $request->report_type,
            $request->format,
            $request->all()
        );
    }
}
