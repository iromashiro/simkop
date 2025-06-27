<?php
// app/Domain/Analytics/Providers/RiskMetricsProvider.php
namespace App\Domain\Analytics\Providers;

use App\Domain\Analytics\Contracts\AnalyticsProviderInterface;
use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;
use App\Domain\Analytics\DTOs\WidgetDataDTO;
use App\Domain\Loan\Models\LoanAccount;
use App\Domain\Savings\Models\SavingsAccount;
use App\Domain\Accounting\Models\Account;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Risk Metrics Analytics Provider
 * SRS Reference: Section 3.6.8 - Risk Assessment Analytics
 */
class RiskMetricsProvider implements AnalyticsProviderInterface
{
    public function generate(AnalyticsRequestDTO $request): WidgetDataDTO
    {
        $dateRange = $request->getDateRange();

        $riskData = [
            'credit_risk' => $this->assessCreditRisk($request->cooperativeId),
            'liquidity_risk' => $this->assessLiquidityRisk($request->cooperativeId),
            'operational_risk' => $this->assessOperationalRisk($request->cooperativeId),
            'concentration_risk' => $this->assessConcentrationRisk($request->cooperativeId),
            'portfolio_at_risk' => $this->calculatePortfolioAtRisk($request->cooperativeId),
            'risk_score' => $this->calculateOverallRiskScore($request->cooperativeId),
            'risk_trends' => $this->getRiskTrends($request->cooperativeId, $dateRange),
            'mitigation_recommendations' => $this->getRiskMitigationRecommendations($request->cooperativeId),
        ];

        return WidgetDataDTO::financial(
            title: 'Risk Metrics',
            data: $riskData,
            chartConfig: $this->getDefaultChartConfig(),
            description: 'Comprehensive risk assessment and monitoring metrics'
        );
    }

    public function getName(): string
    {
        return 'Risk Metrics';
    }

    public function getDescription(): string
    {
        return 'Risk assessment including credit, liquidity, operational, and concentration risk analysis';
    }

    public function getRequiredPermissions(): array
    {
        return ['view_risk_reports', 'view_financial_reports'];
    }

    public function getCacheKey(AnalyticsRequestDTO $request): string
    {
        return "risk_metrics:{$request->cooperativeId}:{$request->period}:" . md5(serialize($request->filters));
    }

    public function getCacheTTL(): int
    {
        return 3600; // 1 hour
    }

    public function validate(AnalyticsRequestDTO $request): bool
    {
        return $request->cooperativeId > 0;
    }

    public function getSupportedMetrics(): array
    {
        return [
            'credit_risk_score',
            'liquidity_ratio',
            'portfolio_at_risk',
            'concentration_ratio',
            'overall_risk_score'
        ];
    }

    public function supportsRealTime(): bool
    {
        return false;
    }

    public function getConfiguration(): array
    {
        return [
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            'real_time' => false,
            'supported_periods' => ['monthly', 'quarterly', 'yearly']
        ];
    }

    public function getWidgetType(): string
    {
        return 'financial';
    }

    public function getDefaultChartConfig(): array
    {
        return [
            'type' => 'radar',
            'options' => [
                'responsive' => true,
                'scales' => [
                    'r' => [
                        'beginAtZero' => true,
                        'max' => 100,
                        'ticks' => [
                            'stepSize' => 20
                        ]
                    ]
                ],
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'top'
                    ]
                ]
            ]
        ];
    }

    public function supportsPeriod(string $period): bool
    {
        return in_array($period, ['monthly', 'quarterly', 'yearly']);
    }

    /**
     * Assess credit risk
     */
    private function assessCreditRisk(int $cooperativeId): array
    {
        $totalLoans = LoanAccount::where('cooperative_id', $cooperativeId)->sum('outstanding_balance');
        $defaultedLoans = LoanAccount::where('cooperative_id', $cooperativeId)
            ->where('status', 'defaulted')
            ->sum('outstanding_balance');

        $pastDueLoans = LoanAccount::where('cooperative_id', $cooperativeId)
            ->where('days_past_due', '>', 0)
            ->sum('outstanding_balance');

        $defaultRate = $totalLoans > 0 ? ($defaultedLoans / $totalLoans) * 100 : 0;
        $pastDueRate = $totalLoans > 0 ? ($pastDueLoans / $totalLoans) * 100 : 0;

        return [
            'default_rate' => $defaultRate,
            'past_due_rate' => $pastDueRate,
            'credit_risk_score' => $this->calculateCreditRiskScore($defaultRate, $pastDueRate),
            'risk_level' => $this->getCreditRiskLevel($defaultRate, $pastDueRate),
        ];
    }

    /**
     * Assess liquidity risk
     */
    private function assessLiquidityRisk(int $cooperativeId): array
    {
        $cashAccounts = Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'asset')
            ->where('account_subtype', 'cash')
            ->sum('balance');

        $totalSavings = SavingsAccount::where('cooperative_id', $cooperativeId)
            ->sum('balance');

        $currentLiabilities = Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'liability')
            ->where('account_subtype', 'current')
            ->sum('balance');

        $liquidityRatio = $currentLiabilities > 0 ? $cashAccounts / $currentLiabilities : 0;
        $savingsToLiabilitiesRatio = $currentLiabilities > 0 ? $totalSavings / $currentLiabilities : 0;

        return [
            'liquidity_ratio' => $liquidityRatio,
            'cash_ratio' => $savingsToLiabilitiesRatio,
            'liquidity_risk_score' => $this->calculateLiquidityRiskScore($liquidityRatio),
            'risk_level' => $this->getLiquidityRiskLevel($liquidityRatio),
        ];
    }

    /**
     * Assess operational risk
     */
    private function assessOperationalRisk(int $cooperativeId): array
    {
        // Simplified operational risk assessment
        $memberCount = \App\Domain\Member\Models\Member::where('cooperative_id', $cooperativeId)->count();
        $staffCount = \App\Domain\User\Models\User::where('cooperative_id', $cooperativeId)->count();

        $memberToStaffRatio = $staffCount > 0 ? $memberCount / $staffCount : 0;

        return [
            'member_to_staff_ratio' => $memberToStaffRatio,
            'operational_efficiency' => $this->calculateOperationalEfficiency($cooperativeId),
            'operational_risk_score' => $this->calculateOperationalRiskScore($memberToStaffRatio),
            'risk_level' => $this->getOperationalRiskLevel($memberToStaffRatio),
        ];
    }

    /**
     * Assess concentration risk
     */
    private function assessConcentrationRisk(int $cooperativeId): array
    {
        // Top 10 borrowers concentration
        $totalLoans = LoanAccount::where('cooperative_id', $cooperativeId)->sum('outstanding_balance');
        $top10Loans = LoanAccount::where('cooperative_id', $cooperativeId)
            ->orderBy('outstanding_balance', 'desc')
            ->limit(10)
            ->sum('outstanding_balance');

        $concentrationRatio = $totalLoans > 0 ? ($top10Loans / $totalLoans) * 100 : 0;

        return [
            'borrower_concentration' => $concentrationRatio,
            'sector_concentration' => $this->calculateSectorConcentration($cooperativeId),
            'concentration_risk_score' => $this->calculateConcentrationRiskScore($concentrationRatio),
            'risk_level' => $this->getConcentrationRiskLevel($concentrationRatio),
        ];
    }

    /**
     * Calculate portfolio at risk
     */
    private function calculatePortfolioAtRisk(int $cooperativeId): array
    {
        $totalOutstanding = LoanAccount::where('cooperative_id', $cooperativeId)
            ->sum('outstanding_balance');

        $par30 = LoanAccount::where('cooperative_id', $cooperativeId)
            ->where('days_past_due', '>=', 30)
            ->sum('outstanding_balance');

        $par60 = LoanAccount::where('cooperative_id', $cooperativeId)
            ->where('days_past_due', '>=', 60)
            ->sum('outstanding_balance');

        $par90 = LoanAccount::where('cooperative_id', $cooperativeId)
            ->where('days_past_due', '>=', 90)
            ->sum('outstanding_balance');

        return [
            'par_30' => $totalOutstanding > 0 ? ($par30 / $totalOutstanding) * 100 : 0,
            'par_60' => $totalOutstanding > 0 ? ($par60 / $totalOutstanding) * 100 : 0,
            'par_90' => $totalOutstanding > 0 ? ($par90 / $totalOutstanding) * 100 : 0,
        ];
    }

    /**
     * Calculate overall risk score
     */
    private function calculateOverallRiskScore(int $cooperativeId): float
    {
        $creditRisk = $this->assessCreditRisk($cooperativeId);
        $liquidityRisk = $this->assessLiquidityRisk($cooperativeId);
        $operationalRisk = $this->assessOperationalRisk($cooperativeId);
        $concentrationRisk = $this->assessConcentrationRisk($cooperativeId);

        // Weighted average of risk scores
        $weights = [
            'credit' => 0.4,
            'liquidity' => 0.3,
            'operational' => 0.2,
            'concentration' => 0.1,
        ];

        return ($creditRisk['credit_risk_score'] * $weights['credit']) +
            ($liquidityRisk['liquidity_risk_score'] * $weights['liquidity']) +
            ($operationalRisk['operational_risk_score'] * $weights['operational']) +
            ($concentrationRisk['concentration_risk_score'] * $weights['concentration']);
    }

    /**
     * Get risk trends
     */
    private function getRiskTrends(int $cooperativeId, array $dateRange): array
    {
        // Implementation for risk trends over time
        return [
            'credit_risk_trend' => [],
            'liquidity_risk_trend' => [],
            'operational_risk_trend' => [],
        ];
    }

    /**
     * Get risk mitigation recommendations
     */
    private function getRiskMitigationRecommendations(int $cooperativeId): array
    {
        $recommendations = [];

        $creditRisk = $this->assessCreditRisk($cooperativeId);
        $liquidityRisk = $this->assessLiquidityRisk($cooperativeId);

        if ($creditRisk['default_rate'] > 5) {
            $recommendations[] = [
                'type' => 'credit',
                'priority' => 'high',
                'recommendation' => 'Implement stricter credit assessment procedures',
                'action' => 'Review and update credit scoring model'
            ];
        }

        if ($liquidityRisk['liquidity_ratio'] < 1.2) {
            $recommendations[] = [
                'type' => 'liquidity',
                'priority' => 'medium',
                'recommendation' => 'Increase cash reserves',
                'action' => 'Implement cash flow forecasting'
            ];
        }

        return $recommendations;
    }

    // Helper methods for risk calculations
    private function calculateCreditRiskScore(float $defaultRate, float $pastDueRate): float
    {
        return max(0, 100 - ($defaultRate * 10) - ($pastDueRate * 5));
    }

    private function getCreditRiskLevel(float $defaultRate, float $pastDueRate): string
    {
        $score = $this->calculateCreditRiskScore($defaultRate, $pastDueRate);

        if ($score >= 80) return 'Low';
        if ($score >= 60) return 'Medium';
        if ($score >= 40) return 'High';
        return 'Very High';
    }

    private function calculateLiquidityRiskScore(float $liquidityRatio): float
    {
        if ($liquidityRatio >= 2.0) return 100;
        if ($liquidityRatio >= 1.5) return 80;
        if ($liquidityRatio >= 1.0) return 60;
        if ($liquidityRatio >= 0.5) return 40;
        return 20;
    }

    private function getLiquidityRiskLevel(float $liquidityRatio): string
    {
        if ($liquidityRatio >= 2.0) return 'Low';
        if ($liquidityRatio >= 1.5) return 'Medium';
        if ($liquidityRatio >= 1.0) return 'High';
        return 'Very High';
    }

    private function calculateOperationalEfficiency(int $cooperativeId): float
    {
        // Simplified calculation
        return 75.0; // Placeholder
    }

    private function calculateOperationalRiskScore(float $memberToStaffRatio): float
    {
        // Optimal ratio is around 50-100 members per staff
        if ($memberToStaffRatio >= 50 && $memberToStaffRatio <= 100) return 100;
        if ($memberToStaffRatio >= 30 && $memberToStaffRatio <= 150) return 80;
        if ($memberToStaffRatio >= 20 && $memberToStaffRatio <= 200) return 60;
        return 40;
    }

    private function getOperationalRiskLevel(float $memberToStaffRatio): string
    {
        $score = $this->calculateOperationalRiskScore($memberToStaffRatio);

        if ($score >= 80) return 'Low';
        if ($score >= 60) return 'Medium';
        return 'High';
    }

    private function calculateSectorConcentration(int $cooperativeId): float
    {
        // Placeholder for sector concentration calculation
        return 25.0;
    }

    private function calculateConcentrationRiskScore(float $concentrationRatio): float
    {
        return max(0, 100 - ($concentrationRatio * 2));
    }

    private function getConcentrationRiskLevel(float $concentrationRatio): string
    {
        if ($concentrationRatio <= 20) return 'Low';
        if ($concentrationRatio <= 40) return 'Medium';
        if ($concentrationRatio <= 60) return 'High';
        return 'Very High';
    }
}
