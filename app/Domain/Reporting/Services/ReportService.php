<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Accounting\Services\AccountService;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalEntryLine;
use App\Domain\Member\Services\MemberService;
use App\Domain\Member\Models\Member;
use App\Domain\Financial\Models\Savings;
use App\Domain\Loan\Models\Loan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportService
{
    public function __construct(
        private AccountService $accountService,
        private MemberService $memberService
    ) {}

    /**
     * Generate Balance Sheet report
     */
    public function generateBalanceSheet(
        int $cooperativeId,
        Carbon $asOfDate,
        ?int $fiscalPeriodId = null
    ): array {
        $cacheKey = "balance_sheet_{$cooperativeId}_{$asOfDate->format('Y-m-d')}_{$fiscalPeriodId}";

        return Cache::tags(['reports', "cooperative_{$cooperativeId}"])
            ->remember($cacheKey, 300, function () use ($cooperativeId, $asOfDate, $fiscalPeriodId) {
                return $this->buildBalanceSheet($cooperativeId, $asOfDate, $fiscalPeriodId);
            });
    }

    /**
     * Generate Income Statement report
     */
    public function generateIncomeStatement(
        int $cooperativeId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $fiscalPeriodId = null
    ): array {
        $cacheKey = "income_statement_{$cooperativeId}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}_{$fiscalPeriodId}";

        return Cache::tags(['reports', "cooperative_{$cooperativeId}"])
            ->remember($cacheKey, 300, function () use ($cooperativeId, $startDate, $endDate, $fiscalPeriodId) {
                return $this->buildIncomeStatement($cooperativeId, $startDate, $endDate, $fiscalPeriodId);
            });
    }

    /**
     * Generate Cash Flow Statement report
     */
    public function generateCashFlowStatement(
        int $cooperativeId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $fiscalPeriodId = null
    ): array {
        $cacheKey = "cash_flow_{$cooperativeId}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}_{$fiscalPeriodId}";

        return Cache::tags(['reports', "cooperative_{$cooperativeId}"])
            ->remember($cacheKey, 300, function () use ($cooperativeId, $startDate, $endDate, $fiscalPeriodId) {
                return $this->buildCashFlowStatement($cooperativeId, $startDate, $endDate, $fiscalPeriodId);
            });
    }

    /**
     * Generate Member Report
     */
    public function generateMemberReport(int $cooperativeId, array $filters = []): array
    {
        $stats = $this->memberService->getMemberStatistics($cooperativeId);
        $memberDetails = $this->getMemberDetails($cooperativeId, $filters);

        return [
            'statistics' => [
                'total_members' => $stats['total_members'],
                'active_members' => $stats['active_members'],
                'inactive_members' => $stats['total_members'] - $stats['active_members'],
                'new_members_this_month' => $stats['new_members_this_month'],
                'member_growth_rate' => $this->calculateMemberGrowthRate($cooperativeId),
            ],
            'member_details' => $memberDetails,
            'age_distribution' => $this->getMemberAgeDistribution($cooperativeId),
            'gender_distribution' => $this->getMemberGenderDistribution($cooperativeId),
        ];
    }

    /**
     * Generate Savings Report
     */
    public function generateSavingsReport(int $cooperativeId, array $filters = []): array
    {
        return [
            'summary' => $this->getSavingsSummary($cooperativeId, $filters),
            'account_details' => $this->getSavingsAccountDetails($cooperativeId, $filters),
            'transaction_summary' => $this->getSavingsTransactionSummary($cooperativeId, $filters),
            'growth_analysis' => $this->getSavingsGrowthAnalysis($cooperativeId, $filters),
        ];
    }

    /**
     * Generate Loan Report
     */
    public function generateLoanReport(int $cooperativeId, array $filters = []): array
    {
        return [
            'summary' => $this->getLoanSummary($cooperativeId, $filters),
            'portfolio_analysis' => $this->getLoanPortfolioAnalysis($cooperativeId, $filters),
            'delinquency_report' => $this->getLoanDelinquencyReport($cooperativeId, $filters),
            'performance_metrics' => $this->getLoanPerformanceMetrics($cooperativeId, $filters),
        ];
    }

    /**
     * Export report to various formats
     */
    public function exportReport(
        int $cooperativeId,
        string $reportType,
        string $format,
        array $parameters = []
    ): BinaryFileResponse {
        $reportData = $this->generateReportData($cooperativeId, $reportType, $parameters);

        return match ($format) {
            'pdf' => $this->exportToPdf($reportData, $reportType),
            'excel' => $this->exportToExcel($reportData, $reportType),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}")
        };
    }

    /**
     * Build Balance Sheet data structure
     */
    private function buildBalanceSheet(int $cooperativeId, Carbon $asOfDate, ?int $fiscalPeriodId): array
    {
        // Get all accounts for the cooperative
        $accounts = Account::where('cooperative_id', $cooperativeId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $assets = [];
        $liabilities = [];
        $equity = [];

        foreach ($accounts as $account) {
            $balance = $this->getAccountBalance($account->id, $asOfDate, $fiscalPeriodId);

            if ($balance != 0) {
                $accountData = [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $balance,
                ];

                switch ($account->type) {
                    case 'asset':
                        $assets[] = $accountData;
                        break;
                    case 'liability':
                        $liabilities[] = $accountData;
                        break;
                    case 'equity':
                        $equity[] = $accountData;
                        break;
                }
            }
        }

        $totalAssets = collect($assets)->sum('balance');
        $totalLiabilities = collect($liabilities)->sum('balance');
        $totalEquity = collect($equity)->sum('balance');

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totals' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
                'balance_check' => $totalAssets - ($totalLiabilities + $totalEquity),
            ],
        ];
    }

    /**
     * Build Income Statement data structure
     */
    private function buildIncomeStatement(
        int $cooperativeId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $fiscalPeriodId
    ): array {
        $accounts = Account::where('cooperative_id', $cooperativeId)
            ->where('is_active', true)
            ->whereIn('type', ['revenue', 'expense'])
            ->orderBy('code')
            ->get();

        $revenue = [];
        $expenses = [];

        foreach ($accounts as $account) {
            $balance = $this->getAccountBalanceForPeriod($account->id, $startDate, $endDate, $fiscalPeriodId);

            if ($balance != 0) {
                $accountData = [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $balance,
                ];

                if ($account->type === 'revenue') {
                    $revenue[] = $accountData;
                } else {
                    $expenses[] = $accountData;
                }
            }
        }

        $totalRevenue = collect($revenue)->sum('balance');
        $totalExpenses = collect($expenses)->sum('balance');
        $netIncome = $totalRevenue - $totalExpenses;

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'revenue' => $revenue,
            'expenses' => $expenses,
            'totals' => [
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'net_income' => $netIncome,
                'profit_margin' => $totalRevenue > 0 ? ($netIncome / $totalRevenue) * 100 : 0,
            ],
        ];
    }

    /**
     * Build Cash Flow Statement data structure
     */
    private function buildCashFlowStatement(
        int $cooperativeId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $fiscalPeriodId
    ): array {
        // Get cash and cash equivalent accounts
        $cashAccounts = Account::where('cooperative_id', $cooperativeId)
            ->where('is_active', true)
            ->where('type', 'asset')
            ->where(function ($query) {
                $query->where('name', 'LIKE', '%cash%')
                    ->orWhere('name', 'LIKE', '%bank%')
                    ->orWhere('code', 'LIKE', '1-1%'); // Assuming cash accounts start with 1-1
            })
            ->get();

        $operatingActivities = $this->getCashFlowOperatingActivities($cooperativeId, $startDate, $endDate);
        $investingActivities = $this->getCashFlowInvestingActivities($cooperativeId, $startDate, $endDate);
        $financingActivities = $this->getCashFlowFinancingActivities($cooperativeId, $startDate, $endDate);

        $netCashFlow = $operatingActivities['total'] + $investingActivities['total'] + $financingActivities['total'];

        $beginningCash = $this->getCashBalanceAtDate($cooperativeId, $startDate->copy()->subDay());
        $endingCash = $beginningCash + $netCashFlow;

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'operating_activities' => $operatingActivities,
            'investing_activities' => $investingActivities,
            'financing_activities' => $financingActivities,
            'summary' => [
                'beginning_cash' => $beginningCash,
                'net_cash_flow' => $netCashFlow,
                'ending_cash' => $endingCash,
            ],
        ];
    }

    /**
     * Get account balance as of a specific date
     */
    private function getAccountBalance(int $accountId, Carbon $asOfDate, ?int $fiscalPeriodId = null): float
    {
        $query = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($asOfDate, $fiscalPeriodId) {
                $q->where('entry_date', '<=', $asOfDate)
                    ->where('status', 'approved');

                if ($fiscalPeriodId) {
                    $q->where('fiscal_period_id', $fiscalPeriodId);
                }
            });

        $debitTotal = $query->sum('debit_amount');
        $creditTotal = $query->sum('credit_amount');

        return $debitTotal - $creditTotal;
    }

    /**
     * Get account balance for a specific period
     */
    private function getAccountBalanceForPeriod(
        int $accountId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $fiscalPeriodId = null
    ): float {
        $query = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate, $fiscalPeriodId) {
                $q->whereBetween('entry_date', [$startDate, $endDate])
                    ->where('status', 'approved');

                if ($fiscalPeriodId) {
                    $q->where('fiscal_period_id', $fiscalPeriodId);
                }
            });

        $debitTotal = $query->sum('debit_amount');
        $creditTotal = $query->sum('credit_amount');

        return $debitTotal - $creditTotal;
    }

    /**
     * Calculate member growth rate
     */
    private function calculateMemberGrowthRate(int $cooperativeId): float
    {
        $currentMonth = now();
        $previousMonth = $currentMonth->copy()->subMonth();

        $currentCount = Member::where('cooperative_id', $cooperativeId)
            ->where('created_at', '<=', $currentMonth->endOfMonth())
            ->count();

        $previousCount = Member::where('cooperative_id', $cooperativeId)
            ->where('created_at', '<=', $previousMonth->endOfMonth())
            ->count();

        if ($previousCount == 0) {
            return 0.0;
        }

        return (($currentCount - $previousCount) / $previousCount) * 100;
    }

    /**
     * Get member details with filters
     */
    private function getMemberDetails(int $cooperativeId, array $filters): array
    {
        $query = Member::where('cooperative_id', $cooperativeId);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->with(['savings', 'loans'])
            ->orderBy('member_number')
            ->get()
            ->map(function ($member) {
                return [
                    'member_number' => $member->member_number,
                    'name' => $member->full_name,
                    'status' => $member->status,
                    'join_date' => $member->created_at->format('Y-m-d'),
                    'total_savings' => $member->savings->sum('balance'),
                    'total_loans' => $member->loans->sum('outstanding_balance'),
                ];
            })
            ->toArray();
    }

    /**
     * Get member age distribution
     */
    private function getMemberAgeDistribution(int $cooperativeId): array
    {
        $members = Member::where('cooperative_id', $cooperativeId)
            ->whereNotNull('date_of_birth')
            ->get();

        $ageGroups = [
            '18-25' => 0,
            '26-35' => 0,
            '36-45' => 0,
            '46-55' => 0,
            '56-65' => 0,
            '65+' => 0,
        ];

        foreach ($members as $member) {
            $age = $member->date_of_birth->age;

            if ($age >= 18 && $age <= 25) {
                $ageGroups['18-25']++;
            } elseif ($age >= 26 && $age <= 35) {
                $ageGroups['26-35']++;
            } elseif ($age >= 36 && $age <= 45) {
                $ageGroups['36-45']++;
            } elseif ($age >= 46 && $age <= 55) {
                $ageGroups['46-55']++;
            } elseif ($age >= 56 && $age <= 65) {
                $ageGroups['56-65']++;
            } else {
                $ageGroups['65+']++;
            }
        }

        return $ageGroups;
    }

    /**
     * Get member gender distribution
     */
    private function getMemberGenderDistribution(int $cooperativeId): array
    {
        return Member::where('cooperative_id', $cooperativeId)
            ->selectRaw('gender, COUNT(*) as count')
            ->groupBy('gender')
            ->pluck('count', 'gender')
            ->toArray();
    }

    /**
     * Get savings summary
     */
    private function getSavingsSummary(int $cooperativeId, array $filters): array
    {
        $query = Savings::whereHas('member', function ($q) use ($cooperativeId) {
            $q->where('cooperative_id', $cooperativeId);
        });

        // Apply filters
        if (isset($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $savings = $query->get();

        return [
            'total_accounts' => $savings->count(),
            'total_balance' => $savings->sum('balance'),
            'average_balance' => $savings->avg('balance'),
            'active_accounts' => $savings->where('status', 'active')->count(),
            'inactive_accounts' => $savings->where('status', 'inactive')->count(),
        ];
    }

    /**
     * Get savings account details
     */
    private function getSavingsAccountDetails(int $cooperativeId, array $filters): array
    {
        $query = Savings::whereHas('member', function ($q) use ($cooperativeId) {
            $q->where('cooperative_id', $cooperativeId);
        })->with('member');

        // Apply filters (same as getSavingsSummary)
        if (isset($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('account_number')
            ->get()
            ->map(function ($savings) {
                return [
                    'account_number' => $savings->account_number,
                    'member_name' => $savings->member->full_name,
                    'account_type' => $savings->account_type,
                    'balance' => $savings->balance,
                    'status' => $savings->status,
                    'opened_date' => $savings->created_at->format('Y-m-d'),
                ];
            })
            ->toArray();
    }

    /**
     * Get savings transaction summary
     */
    private function getSavingsTransactionSummary(int $cooperativeId, array $filters): array
    {
        // This would require a SavingsTransaction model/table
        // For now, return placeholder data
        return [
            'total_deposits' => 0,
            'total_withdrawals' => 0,
            'net_change' => 0,
            'transaction_count' => 0,
        ];
    }

    /**
     * Get savings growth analysis
     */
    private function getSavingsGrowthAnalysis(int $cooperativeId, array $filters): array
    {
        // This would require historical data tracking
        // For now, return placeholder data
        return [
            'monthly_growth' => [],
            'growth_rate' => 0,
            'trend' => 'stable',
        ];
    }

    /**
     * Get loan summary
     */
    private function getLoanSummary(int $cooperativeId, array $filters): array
    {
        $query = Loan::whereHas('member', function ($q) use ($cooperativeId) {
            $q->where('cooperative_id', $cooperativeId);
        });

        // Apply filters
        if (isset($filters['loan_type'])) {
            $query->where('loan_type', $filters['loan_type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $loans = $query->get();

        return [
            'total_loans' => $loans->count(),
            'total_principal' => $loans->sum('principal_amount'),
            'total_outstanding' => $loans->sum('outstanding_balance'),
            'total_paid' => $loans->sum('principal_amount') - $loans->sum('outstanding_balance'),
            'active_loans' => $loans->where('status', 'active')->count(),
            'completed_loans' => $loans->where('status', 'completed')->count(),
            'defaulted_loans' => $loans->where('status', 'defaulted')->count(),
        ];
    }

    /**
     * Get loan portfolio analysis
     */
    private function getLoanPortfolioAnalysis(int $cooperativeId, array $filters): array
    {
        // Implementation for loan portfolio analysis
        return [
            'by_loan_type' => [],
            'by_term' => [],
            'by_interest_rate' => [],
            'risk_distribution' => [],
        ];
    }

    /**
     * Get loan delinquency report
     */
    private function getLoanDelinquencyReport(int $cooperativeId, array $filters): array
    {
        // Implementation for delinquency analysis
        return [
            'current' => 0,
            'days_30' => 0,
            'days_60' => 0,
            'days_90' => 0,
            'days_120_plus' => 0,
        ];
    }

    /**
     * Get loan performance metrics
     */
    private function getLoanPerformanceMetrics(int $cooperativeId, array $filters): array
    {
        // Implementation for performance metrics
        return [
            'default_rate' => 0,
            'recovery_rate' => 0,
            'average_loan_size' => 0,
            'portfolio_yield' => 0,
        ];
    }

    /**
     * Generate report data based on type
     */
    private function generateReportData(int $cooperativeId, string $reportType, array $parameters): array
    {
        return match ($reportType) {
            'balance_sheet' => $this->generateBalanceSheet(
                $cooperativeId,
                Carbon::parse($parameters['as_of_date'] ?? now()),
                $parameters['fiscal_period_id'] ?? null
            ),
            'income_statement' => $this->generateIncomeStatement(
                $cooperativeId,
                Carbon::parse($parameters['start_date'] ?? now()->startOfYear()),
                Carbon::parse($parameters['end_date'] ?? now()),
                $parameters['fiscal_period_id'] ?? null
            ),
            'cash_flow' => $this->generateCashFlowStatement(
                $cooperativeId,
                Carbon::parse($parameters['start_date'] ?? now()->startOfYear()),
                Carbon::parse($parameters['end_date'] ?? now()),
                $parameters['fiscal_period_id'] ?? null
            ),
            'member' => $this->generateMemberReport($cooperativeId, $parameters),
            'savings' => $this->generateSavingsReport($cooperativeId, $parameters),
            'loan' => $this->generateLoanReport($cooperativeId, $parameters),
            default => throw new \InvalidArgumentException("Unsupported report type: {$reportType}")
        };
    }

    /**
     * Export report to PDF
     */
    private function exportToPdf(array $reportData, string $reportType): BinaryFileResponse
    {
        $pdf = Pdf::loadView("reports.exports.{$reportType}", compact('reportData'));

        $filename = "{$reportType}_" . now()->format('Y-m-d_H-i-s') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export report to Excel
     */
    private function exportToExcel(array $reportData, string $reportType): BinaryFileResponse
    {
        $filename = "{$reportType}_" . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(
            new \App\Exports\ReportExport($reportData, $reportType),
            $filename
        );
    }

    /**
     * Get cash flow operating activities
     */
    private function getCashFlowOperatingActivities(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        // Implementation for operating activities
        return [
            'items' => [],
            'total' => 0,
        ];
    }

    /**
     * Get cash flow investing activities
     */
    private function getCashFlowInvestingActivities(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        // Implementation for investing activities
        return [
            'items' => [],
            'total' => 0,
        ];
    }

    /**
     * Get cash flow financing activities
     */
    private function getCashFlowFinancingActivities(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        // Implementation for financing activities
        return [
            'items' => [],
            'total' => 0,
        ];
    }

    /**
     * Get cash balance at specific date
     */
    private function getCashBalanceAtDate(int $cooperativeId, Carbon $date): float
    {
        // Get cash accounts and sum their balances
        $cashAccounts = Account::where('cooperative_id', $cooperativeId)
            ->where('is_active', true)
            ->where('type', 'asset')
            ->where(function ($query) {
                $query->where('name', 'LIKE', '%cash%')
                    ->orWhere('name', 'LIKE', '%bank%')
                    ->orWhere('code', 'LIKE', '1-1%');
            })
            ->get();

        $totalCash = 0;
        foreach ($cashAccounts as $account) {
            $totalCash += $this->getAccountBalance($account->id, $date);
        }

        return $totalCash;
    }
}
