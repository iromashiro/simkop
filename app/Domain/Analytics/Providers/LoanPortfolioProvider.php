<?php
// app/Domain/Analytics/Providers/LoanPortfolioProvider.php
namespace App\Domain\Analytics\Providers;

use App\Domain\Analytics\Contracts\AnalyticsProviderInterface;
use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;
use App\Domain\Analytics\DTOs\WidgetDataDTO;
use App\Domain\Loan\Models\LoanAccount;
use App\Domain\Loan\Models\LoanPayment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Loan Portfolio Analytics Provider
 * SRS Reference: Section 3.6.6 - Loan Portfolio Analytics
 */
class LoanPortfolioProvider implements AnalyticsProviderInterface
{
    public function generate(AnalyticsRequestDTO $request): WidgetDataDTO
    {
        $dateRange = $request->getDateRange();

        $loanData = [
            'total_loans_disbursed' => $this->getTotalLoansAmount($request->cooperativeId),
            'outstanding_balance' => $this->getOutstandingBalance($request->cooperativeId),
            'total_loan_accounts' => $this->getTotalLoanAccounts($request->cooperativeId),
            'average_loan_size' => $this->getAverageLoanSize($request->cooperativeId),
            'collection_rate' => $this->calculateCollectionRate($request->cooperativeId),
            'default_rate' => $this->calculateDefaultRate($request->cooperativeId),
            'portfolio_quality' => $this->analyzePortfolioQuality($request->cooperativeId),
            'loan_distribution' => $this->getLoanDistribution($request->cooperativeId),
            'payment_trends' => $this->getPaymentTrends($request->cooperativeId, $dateRange),
            'aging_analysis' => $this->getAgingAnalysis($request->cooperativeId),
        ];

        return WidgetDataDTO::loan(
            title: 'Loan Portfolio',
            data: $loanData,
            chartConfig: $this->getDefaultChartConfig(),
            description: 'Comprehensive loan portfolio analysis and risk metrics'
        );
    }

    public function getName(): string
    {
        return 'Loan Portfolio';
    }

    public function getDescription(): string
    {
        return 'Loan portfolio analysis including disbursements, collections, defaults, and risk assessment';
    }

    public function getRequiredPermissions(): array
    {
        return ['view_loan_accounts', 'view_loan_reports'];
    }

    public function getCacheKey(AnalyticsRequestDTO $request): string
    {
        return "loan_portfolio:{$request->cooperativeId}:{$request->period}:" . md5(serialize($request->filters));
    }

    public function getCacheTTL(): int
    {
        return 1800; // 30 minutes
    }

    public function validate(AnalyticsRequestDTO $request): bool
    {
        return $request->cooperativeId > 0;
    }

    public function getSupportedMetrics(): array
    {
        return [
            'total_loans',
            'outstanding_balance',
            'collection_rate',
            'default_rate',
            'portfolio_at_risk',
            'average_loan_size'
        ];
    }

    public function supportsRealTime(): bool
    {
        return true;
    }

    public function getConfiguration(): array
    {
        return [
            'cache_enabled' => true,
            'cache_ttl' => 1800,
            'real_time' => true,
            'supported_periods' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']
        ];
    }

    public function getWidgetType(): string
    {
        return 'loan';
    }

    public function getDefaultChartConfig(): array
    {
        return [
            'type' => 'doughnut',
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'bottom'
                    ],
                    'tooltip' => [
                        'callbacks' => [
                            'label' => 'function(context) { return context.label + ": Rp " + context.parsed.toLocaleString(); }'
                        ]
                    ]
                ]
            ]
        ];
    }

    public function supportsPeriod(string $period): bool
    {
        return in_array($period, ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']);
    }

    /**
     * Get total loans amount
     */
    private function getTotalLoansAmount(int $cooperativeId): float
    {
        return LoanAccount::where('cooperative_id', $cooperativeId)
            ->sum('principal_amount') ?? 0;
    }

    /**
     * Get outstanding balance
     */
    private function getOutstandingBalance(int $cooperativeId): float
    {
        return LoanAccount::where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->sum('outstanding_balance') ?? 0;
    }

    /**
     * Get total loan accounts
     */
    private function getTotalLoanAccounts(int $cooperativeId): int
    {
        return LoanAccount::where('cooperative_id', $cooperativeId)->count();
    }

    /**
     * Get average loan size
     */
    private function getAverageLoanSize(int $cooperativeId): float
    {
        $totalAccounts = $this->getTotalLoanAccounts($cooperativeId);
        $totalAmount = $this->getTotalLoansAmount($cooperativeId);

        return $totalAccounts > 0 ? $totalAmount / $totalAccounts : 0;
    }

    /**
     * Calculate collection rate
     */
    private function calculateCollectionRate(int $cooperativeId): float
    {
        $totalDue = LoanAccount::where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->sum('monthly_payment');

        $totalCollected = LoanPayment::whereHas('loanAccount', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })
            ->where('payment_date', '>=', now()->startOfMonth())
            ->sum('amount');

        return $totalDue > 0 ? ($totalCollected / $totalDue) * 100 : 0;
    }

    /**
     * Calculate default rate
     */
    private function calculateDefaultRate(int $cooperativeId): float
    {
        $totalLoans = $this->getTotalLoanAccounts($cooperativeId);

        $defaultedLoans = LoanAccount::where('cooperative_id', $cooperativeId)
            ->where('status', 'defaulted')
            ->count();

        return $totalLoans > 0 ? ($defaultedLoans / $totalLoans) * 100 : 0;
    }

    /**
     * Analyze portfolio quality
     */
    private function analyzePortfolioQuality(int $cooperativeId): array
    {
        $totalOutstanding = $this->getOutstandingBalance($cooperativeId);

        $par30 = LoanAccount::where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->where('days_past_due', '>=', 30)
            ->sum('outstanding_balance');

        $par60 = LoanAccount::where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->where('days_past_due', '>=', 60)
            ->sum('outstanding_balance');

        $par90 = LoanAccount::where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->where('days_past_due', '>=', 90)
            ->sum('outstanding_balance');

        return [
            'par_30' => $totalOutstanding > 0 ? ($par30 / $totalOutstanding) * 100 : 0,
            'par_60' => $totalOutstanding > 0 ? ($par60 / $totalOutstanding) * 100 : 0,
            'par_90' => $totalOutstanding > 0 ? ($par90 / $totalOutstanding) * 100 : 0,
            'portfolio_quality_score' => $this->calculatePortfolioQualityScore($par30, $par60, $par90, $totalOutstanding),
        ];
    }

    /**
     * Get loan distribution by type
     */
    private function getLoanDistribution(int $cooperativeId): array
    {
        return LoanAccount::where('cooperative_id', $cooperativeId)
            ->selectRaw('loan_type, COUNT(*) as account_count, SUM(principal_amount) as total_amount, SUM(outstanding_balance) as outstanding_amount')
            ->groupBy('loan_type')
            ->get()
            ->toArray();
    }

    /**
     * Get payment trends
     */
    private function getPaymentTrends(int $cooperativeId, array $dateRange): array
    {
        return LoanPayment::whereHas('loanAccount', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })
            ->whereBetween('payment_date', [$dateRange['from'], $dateRange['to']])
            ->selectRaw('DATE(payment_date) as date, SUM(amount) as total_payments, COUNT(*) as payment_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Get aging analysis
     */
    private function getAgingAnalysis(int $cooperativeId): array
    {
        return [
            'current' => LoanAccount::where('cooperative_id', $cooperativeId)
                ->where('status', 'active')
                ->where('days_past_due', 0)
                ->count(),
            '1_30_days' => LoanAccount::where('cooperative_id', $cooperativeId)
                ->where('status', 'active')
                ->whereBetween('days_past_due', [1, 30])
                ->count(),
            '31_60_days' => LoanAccount::where('cooperative_id', $cooperativeId)
                ->where('status', 'active')
                ->whereBetween('days_past_due', [31, 60])
                ->count(),
            '61_90_days' => LoanAccount::where('cooperative_id', $cooperativeId)
                ->where('status', 'active')
                ->whereBetween('days_past_due', [61, 90])
                ->count(),
            'over_90_days' => LoanAccount::where('cooperative_id', $cooperativeId)
                ->where('status', 'active')
                ->where('days_past_due', '>', 90)
                ->count(),
        ];
    }

    /**
     * Calculate portfolio quality score
     */
    private function calculatePortfolioQualityScore(float $par30, float $par60, float $par90, float $totalOutstanding): float
    {
        if ($totalOutstanding == 0) return 100;

        $par30Rate = ($par30 / $totalOutstanding) * 100;
        $par60Rate = ($par60 / $totalOutstanding) * 100;
        $par90Rate = ($par90 / $totalOutstanding) * 100;

        // Weighted scoring: PAR30 (weight 1), PAR60 (weight 2), PAR90 (weight 3)
        $weightedPAR = ($par30Rate * 1) + ($par60Rate * 2) + ($par90Rate * 3);

        return max(0, 100 - $weightedPAR);
    }
}
