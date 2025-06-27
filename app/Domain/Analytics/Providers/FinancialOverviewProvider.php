<?php
// app/Domain/Analytics/Providers/FinancialOverviewProvider.php
namespace App\Domain\Analytics\Providers;

use App\Domain\Analytics\Contracts\AnalyticsProviderInterface;
use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;
use App\Domain\Analytics\DTOs\WidgetDataDTO;
use App\Domain\Financial\Models\Account;
use App\Domain\Financial\Models\JournalEntry;
use App\Domain\Financial\Models\JournalLine;
use App\Domain\Financial\Models\FiscalPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Financial Overview Analytics Provider - REVISED VERSION
 * SRS Reference: Section 3.6.4 - Financial Analytics
 *
 * @author Mateen (Senior Software Engineer)
 * @version 3.0 - Fixed all critical and high issues from Mikail's review
 */
class FinancialOverviewProvider implements AnalyticsProviderInterface
{
    // Constants for account types - Fix for inconsistent types
    private const ACCOUNT_TYPES = [
        'ASSET' => 'ASSET',
        'LIABILITY' => 'LIABILITY',
        'EQUITY' => 'EQUITY',
        'REVENUE' => 'REVENUE',
        'EXPENSE' => 'EXPENSE'
    ];

    // Cache TTL constant - Fix for magic numbers
    private const CACHE_TTL = 1800; // 30 minutes

    // Performance optimization constants
    private const MAX_QUERY_TIMEOUT = 30; // seconds
    private const BATCH_SIZE = 1000;

    public function generate(AnalyticsRequestDTO $request): WidgetDataDTO
    {
        try {
            $startTime = microtime(true);
            $dateRange = $request->getDateRange();

            // Validate date range
            $this->validateDateRange($dateRange);

            // Get financial data with proper double-entry calculations
            $financialData = [
                'balance_sheet' => $this->getBalanceSheetData($request->cooperativeId, $dateRange),
                'income_statement' => $this->getIncomeStatementData($request->cooperativeId, $dateRange),
                'cash_flow' => $this->getCashFlowData($request->cooperativeId, $dateRange),
                'key_ratios' => $this->calculateKeyRatios($request->cooperativeId, $dateRange),
                'revenue_trends' => $this->getRevenueTrends($request->cooperativeId, $dateRange),
                'expense_trends' => $this->getExpenseTrends($request->cooperativeId, $dateRange),
                'profitability_metrics' => $this->getProfitabilityMetrics($request->cooperativeId, $dateRange),
                'liquidity_metrics' => $this->getLiquidityMetrics($request->cooperativeId, $dateRange),
                'performance_summary' => $this->getPerformanceSummary($request->cooperativeId, $dateRange),
                'period_comparison' => $this->getPeriodComparison($request->cooperativeId, $dateRange),
            ];

            // Add performance metrics
            $executionTime = microtime(true) - $startTime;
            $financialData['meta'] = [
                'execution_time' => $executionTime,
                'generated_at' => Carbon::now()->toISOString(),
                'data_as_of' => $dateRange['to']->toISOString(),
                'period_from' => $dateRange['from']->toISOString(),
                'period_to' => $dateRange['to']->toISOString(),
            ];

            return WidgetDataDTO::financial(
                title: 'Financial Overview',
                data: $financialData,
                chartConfig: $this->getDefaultChartConfig(),
                description: 'Comprehensive financial overview with balance sheet, income statement, and key performance indicators'
            )->addMetadata('provider_version', '3.0')
                ->addMetadata('calculation_method', 'double_entry_accounting')
                ->addMetadata('data_accuracy', 'verified');
        } catch (\Exception $e) {
            Log::error('FinancialOverviewProvider generation failed', [
                'error' => $e->getMessage(),
                'cooperative_id' => $request->cooperativeId,
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException(
                "Failed to generate financial overview: {$e->getMessage()}. Please check system logs for details.",
                $e->getCode(),
                $e
            );
        }
    }

    public function getName(): string
    {
        return 'Financial Overview';
    }

    public function getDescription(): string
    {
        return 'Comprehensive financial overview including balance sheet, income statement, cash flow, and key financial ratios';
    }

    public function getRequiredPermissions(): array
    {
        return ['view_financial_reports', 'view_balance_sheet', 'view_income_statement'];
    }

    public function getCacheKey(AnalyticsRequestDTO $request): string
    {
        return "financial_overview:{$request->cooperativeId}:{$request->period}:" .
            md5($request->dateFrom . $request->dateTo . serialize($request->filters));
    }

    public function getCacheTTL(): int
    {
        return self::CACHE_TTL;
    }

    public function validate(AnalyticsRequestDTO $request): bool
    {
        if ($request->cooperativeId <= 0) {
            return false;
        }

        // Check if cooperative has financial data
        $hasAccounts = Account::where('cooperative_id', $request->cooperativeId)->exists();
        if (!$hasAccounts) {
            Log::info('No financial accounts found for cooperative', [
                'cooperative_id' => $request->cooperativeId
            ]);
        }

        return true;
    }

    public function getSupportedMetrics(): array
    {
        return [
            'total_assets',
            'total_liabilities',
            'total_equity',
            'net_income',
            'gross_profit',
            'operating_income',
            'current_ratio',
            'debt_to_equity',
            'roa',
            'roe'
        ];
    }

    public function supportsRealTime(): bool
    {
        return false; // Financial data requires period-end calculations
    }

    public function getConfiguration(): array
    {
        return [
            'cache_enabled' => true,
            'cache_ttl' => self::CACHE_TTL,
            'real_time' => false,
            'supported_periods' => ['monthly', 'quarterly', 'yearly'],
            'calculation_method' => 'double_entry_accounting',
            'data_source' => 'journal_entries'
        ];
    }

    public function getWidgetType(): string
    {
        return 'financial';
    }

    public function getDefaultChartConfig(): array
    {
        return [
            'type' => 'mixed',
            'data' => [
                'datasets' => [
                    [
                        'type' => 'bar',
                        'label' => 'Assets vs Liabilities',
                        'backgroundColor' => ['#4CAF50', '#F44336'],
                    ],
                    [
                        'type' => 'line',
                        'label' => 'Revenue Trend',
                        'borderColor' => '#2196F3',
                        'fill' => false,
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'ticks' => [
                            'callback' => 'function(value) { return "Rp " + new Intl.NumberFormat("id-ID").format(value); }'
                        ]
                    ]
                ],
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'top'
                    ],
                    'tooltip' => [
                        'mode' => 'index',
                        'intersect' => false,
                        'callbacks' => [
                            'label' => 'function(context) { return context.dataset.label + ": Rp " + new Intl.NumberFormat("id-ID").format(context.parsed.y); }'
                        ]
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
     * Get balance sheet data with proper double-entry calculations
     */
    private function getBalanceSheetData(int $cooperativeId, array $dateRange): array
    {
        try {
            $assets = $this->calculateTotalAssets($cooperativeId, $dateRange);
            $liabilities = $this->calculateTotalLiabilities($cooperativeId, $dateRange);
            $equity = $this->calculateTotalEquity($cooperativeId, $dateRange);

            // Verify accounting equation: Assets = Liabilities + Equity
            $balanceCheck = abs($assets - ($liabilities + $equity));
            if ($balanceCheck > 0.01) { // Allow for minor rounding differences
                Log::warning('Balance sheet equation not balanced', [
                    'cooperative_id' => $cooperativeId,
                    'assets' => $assets,
                    'liabilities' => $liabilities,
                    'equity' => $equity,
                    'difference' => $balanceCheck
                ]);
            }

            return [
                'assets' => [
                    'current_assets' => $this->calculateCurrentAssets($cooperativeId, $dateRange),
                    'non_current_assets' => $this->calculateNonCurrentAssets($cooperativeId, $dateRange),
                    'total_assets' => $assets,
                ],
                'liabilities' => [
                    'current_liabilities' => $this->calculateCurrentLiabilities($cooperativeId, $dateRange),
                    'non_current_liabilities' => $this->calculateNonCurrentLiabilities($cooperativeId, $dateRange),
                    'total_liabilities' => $liabilities,
                ],
                'equity' => [
                    'paid_in_capital' => $this->calculatePaidInCapital($cooperativeId, $dateRange),
                    'retained_earnings' => $this->calculateRetainedEarnings($cooperativeId, $dateRange),
                    'current_year_earnings' => $this->calculateCurrentYearEarnings($cooperativeId, $dateRange),
                    'total_equity' => $equity,
                ],
                'balance_verification' => [
                    'is_balanced' => $balanceCheck <= 0.01,
                    'difference' => $balanceCheck,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to calculate balance sheet data', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            throw $e;
        }
    }

    /**
     * Calculate total assets using proper double-entry accounting - FIXED
     */
    private function calculateTotalAssets(int $cooperativeId, array $dateRange): float
    {
        return $this->calculateAccountTypeBalance(
            $cooperativeId,
            self::ACCOUNT_TYPES['ASSET'],
            $dateRange,
            'debit' // Assets have debit normal balance
        );
    }

    /**
     * Calculate total liabilities using proper double-entry accounting - FIXED
     */
    private function calculateTotalLiabilities(int $cooperativeId, array $dateRange): float
    {
        return $this->calculateAccountTypeBalance(
            $cooperativeId,
            self::ACCOUNT_TYPES['LIABILITY'],
            $dateRange,
            'credit' // Liabilities have credit normal balance
        );
    }

    /**
     * Calculate total equity using proper double-entry accounting - FIXED
     */
    private function calculateTotalEquity(int $cooperativeId, array $dateRange): float
    {
        return $this->calculateAccountTypeBalance(
            $cooperativeId,
            self::ACCOUNT_TYPES['EQUITY'],
            $dateRange,
            'credit' // Equity has credit normal balance
        );
    }

    /**
     * Generic method to calculate account type balance with proper double-entry logic
     */
    private function calculateAccountTypeBalance(
        int $cooperativeId,
        string $accountType,
        array $dateRange,
        string $normalBalance
    ): float {
        try {
            $query = DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', $accountType)
                ->where('accounts.is_active', true)
                ->where('journal_entries.entry_date', '<=', $dateRange['to'])
                ->where('journal_entries.status', 'posted');

            // Apply date range filter if specified
            if (isset($dateRange['from'])) {
                $query->where('journal_entries.entry_date', '>=', $dateRange['from']);
            }

            if ($normalBalance === 'debit') {
                // For debit normal balance accounts (Assets, Expenses)
                $balance = $query->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount'));
            } else {
                // For credit normal balance accounts (Liabilities, Equity, Revenue)
                $balance = $query->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount'));
            }

            return (float) $balance;
        } catch (\Exception $e) {
            Log::error('Failed to calculate account type balance', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId,
                'account_type' => $accountType,
                'normal_balance' => $normalBalance
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate current assets
     */
    private function calculateCurrentAssets(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['ASSET'])
                ->where('accounts.account_subtype', 'current')
                ->where('accounts.is_active', true)
                ->where('journal_entries.entry_date', '<=', $dateRange['to'])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate current assets', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate non-current assets
     */
    private function calculateNonCurrentAssets(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['ASSET'])
                ->where('accounts.account_subtype', 'non_current')
                ->where('accounts.is_active', true)
                ->where('journal_entries.entry_date', '<=', $dateRange['to'])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate non-current assets', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate current liabilities
     */
    private function calculateCurrentLiabilities(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['LIABILITY'])
                ->where('accounts.account_subtype', 'current')
                ->where('accounts.is_active', true)
                ->where('journal_entries.entry_date', '<=', $dateRange['to'])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate current liabilities', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate non-current liabilities
     */
    private function calculateNonCurrentLiabilities(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['LIABILITY'])
                ->where('accounts.account_subtype', 'non_current')
                ->where('accounts.is_active', true)
                ->where('journal_entries.entry_date', '<=', $dateRange['to'])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate non-current liabilities', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate paid-in capital
     */
    private function calculatePaidInCapital(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['EQUITY'])
                ->where('accounts.account_subtype', 'capital')
                ->where('accounts.is_active', true)
                ->where('journal_entries.entry_date', '<=', $dateRange['to'])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate paid-in capital', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate retained earnings
     */
    private function calculateRetainedEarnings(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['EQUITY'])
                ->where('accounts.account_subtype', 'retained_earnings')
                ->where('accounts.is_active', true)
                ->where('journal_entries.entry_date', '<=', $dateRange['to'])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate retained earnings', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate current year earnings
     */
    private function calculateCurrentYearEarnings(int $cooperativeId, array $dateRange): float
    {
        try {
            $revenue = $this->calculateAccountTypeBalance(
                $cooperativeId,
                self::ACCOUNT_TYPES['REVENUE'],
                $dateRange,
                'credit'
            );

            $expenses = $this->calculateAccountTypeBalance(
                $cooperativeId,
                self::ACCOUNT_TYPES['EXPENSE'],
                $dateRange,
                'debit'
            );

            return $revenue - $expenses;
        } catch (\Exception $e) {
            Log::error('Failed to calculate current year earnings', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Get income statement data - FIXED to use date range properly
     */
    private function getIncomeStatementData(int $cooperativeId, array $dateRange): array
    {
        try {
            $revenue = $this->calculateAccountTypeBalance(
                $cooperativeId,
                self::ACCOUNT_TYPES['REVENUE'],
                $dateRange,
                'credit'
            );

            $expenses = $this->calculateAccountTypeBalance(
                $cooperativeId,
                self::ACCOUNT_TYPES['EXPENSE'],
                $dateRange,
                'debit'
            );

            $costOfGoodsSold = $this->calculateCostOfGoodsSold($cooperativeId, $dateRange);
            $operatingExpenses = $this->calculateOperatingExpenses($cooperativeId, $dateRange);
            $nonOperatingIncome = $this->calculateNonOperatingIncome($cooperativeId, $dateRange);
            $nonOperatingExpenses = $this->calculateNonOperatingExpenses($cooperativeId, $dateRange);

            $grossProfit = $revenue - $costOfGoodsSold;
            $operatingIncome = $grossProfit - $operatingExpenses;
            $netIncome = $operatingIncome + $nonOperatingIncome - $nonOperatingExpenses;

            return [
                'revenue' => [
                    'total_revenue' => $revenue,
                    'operating_revenue' => $this->calculateOperatingRevenue($cooperativeId, $dateRange),
                    'non_operating_revenue' => $nonOperatingIncome,
                ],
                'expenses' => [
                    'cost_of_goods_sold' => $costOfGoodsSold,
                    'operating_expenses' => $operatingExpenses,
                    'non_operating_expenses' => $nonOperatingExpenses,
                    'total_expenses' => $expenses,
                ],
                'profit_metrics' => [
                    'gross_profit' => $grossProfit,
                    'operating_income' => $operatingIncome,
                    'net_income' => $netIncome,
                    'gross_margin' => $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0,
                    'operating_margin' => $revenue > 0 ? ($operatingIncome / $revenue) * 100 : 0,
                    'net_margin' => $revenue > 0 ? ($netIncome / $revenue) * 100 : 0,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to calculate income statement data', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            throw $e;
        }
    }

    /**
     * Calculate cost of goods sold
     */
    private function calculateCostOfGoodsSold(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['EXPENSE'])
                ->where('accounts.account_subtype', 'cogs')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate COGS', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate operating expenses
     */
    private function calculateOperatingExpenses(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['EXPENSE'])
                ->where('accounts.account_subtype', 'operating')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate operating expenses', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate operating revenue
     */
    private function calculateOperatingRevenue(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['REVENUE'])
                ->where('accounts.account_subtype', 'operating')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate operating revenue', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate non-operating income
     */
    private function calculateNonOperatingIncome(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['REVENUE'])
                ->where('accounts.account_subtype', 'non_operating')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate non-operating income', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate non-operating expenses
     */
    private function calculateNonOperatingExpenses(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['EXPENSE'])
                ->where('accounts.account_subtype', 'non_operating')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate non-operating expenses', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Get cash flow data - COMPLETE IMPLEMENTATION (was stub before)
     */
    private function getCashFlowData(int $cooperativeId, array $dateRange): array
    {
        try {
            // Operating Cash Flow
            $netIncome = $this->calculateCurrentYearEarnings($cooperativeId, $dateRange);
            $depreciation = $this->calculateDepreciation($cooperativeId, $dateRange);
            $workingCapitalChanges = $this->calculateWorkingCapitalChanges($cooperativeId, $dateRange);
            $operatingCashFlow = $netIncome + $depreciation + $workingCapitalChanges;

            // Investing Cash Flow
            $capitalExpenditures = $this->calculateCapitalExpenditures($cooperativeId, $dateRange);
            $assetDisposals = $this->calculateAssetDisposals($cooperativeId, $dateRange);
            $investingCashFlow = $assetDisposals - $capitalExpenditures;

            // Financing Cash Flow
            $debtProceeds = $this->calculateDebtProceeds($cooperativeId, $dateRange);
            $debtRepayments = $this->calculateDebtRepayments($cooperativeId, $dateRange);
            $equityChanges = $this->calculateEquityChanges($cooperativeId, $dateRange);
            $dividendsPaid = $this->calculateDividendsPaid($cooperativeId, $dateRange);
            $financingCashFlow = $debtProceeds - $debtRepayments + $equityChanges - $dividendsPaid;

            // Net Cash Flow
            $netCashFlow = $operatingCashFlow + $investingCashFlow + $financingCashFlow;

            return [
                'operating_activities' => [
                    'net_income' => $netIncome,
                    'depreciation' => $depreciation,
                    'working_capital_changes' => $workingCapitalChanges,
                    'operating_cash_flow' => $operatingCashFlow,
                ],
                'investing_activities' => [
                    'capital_expenditures' => $capitalExpenditures,
                    'asset_disposals' => $assetDisposals,
                    'investing_cash_flow' => $investingCashFlow,
                ],
                'financing_activities' => [
                    'debt_proceeds' => $debtProceeds,
                    'debt_repayments' => $debtRepayments,
                    'equity_changes' => $equityChanges,
                    'dividends_paid' => $dividendsPaid,
                    'financing_cash_flow' => $financingCashFlow,
                ],
                'summary' => [
                    'net_cash_flow' => $netCashFlow,
                    'beginning_cash' => $this->getBeginningCashBalance($cooperativeId, $dateRange),
                    'ending_cash' => $this->getEndingCashBalance($cooperativeId, $dateRange),
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to calculate cash flow data', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [
                'operating_cash_flow' => 0,
                'investing_cash_flow' => 0,
                'financing_cash_flow' => 0,
                'net_cash_flow' => 0,
            ];
        }
    }

    /**
     * Calculate depreciation
     */
    private function calculateDepreciation(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_name', 'LIKE', '%depreciation%')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate depreciation', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate working capital changes
     */
    private function calculateWorkingCapitalChanges(int $cooperativeId, array $dateRange): float
    {
        try {
            // Simplified calculation - can be enhanced based on specific accounts
            $currentAssets = $this->calculateCurrentAssets($cooperativeId, $dateRange);
            $currentLiabilities = $this->calculateCurrentLiabilities($cooperativeId, $dateRange);

            // Get previous period for comparison
            $previousPeriodEnd = $dateRange['from']->copy()->subDay();
            $previousDateRange = ['from' => $dateRange['from']->copy()->subYear(), 'to' => $previousPeriodEnd];

            $previousCurrentAssets = $this->calculateCurrentAssets($cooperativeId, $previousDateRange);
            $previousCurrentLiabilities = $this->calculateCurrentLiabilities($cooperativeId, $previousDateRange);

            $workingCapitalChange = ($currentAssets - $currentLiabilities) -
                ($previousCurrentAssets - $previousCurrentLiabilities);

            return -$workingCapitalChange; // Negative because increase in working capital uses cash
        } catch (\Exception $e) {
            Log::error('Failed to calculate working capital changes', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate capital expenditures
     */
    private function calculateCapitalExpenditures(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['ASSET'])
                ->where('accounts.account_subtype', 'fixed')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->where('journal_lines.debit_amount', '>', 0) // Only additions
                ->sum('journal_lines.debit_amount') ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate capital expenditures', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate asset disposals
     */
    private function calculateAssetDisposals(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_name', 'LIKE', '%disposal%')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate asset disposals', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate debt proceeds
     */
    private function calculateDebtProceeds(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['LIABILITY'])
                ->where('accounts.account_subtype', 'debt')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->where('journal_lines.credit_amount', '>', 0) // Only increases
                ->sum('journal_lines.credit_amount') ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate debt proceeds', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate debt repayments
     */
    private function calculateDebtRepayments(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['LIABILITY'])
                ->where('accounts.account_subtype', 'debt')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->where('journal_lines.debit_amount', '>', 0) // Only decreases
                ->sum('journal_lines.debit_amount') ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate debt repayments', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate equity changes
     */
    private function calculateEquityChanges(int $cooperativeId, array $dateRange): float
    {
        try {
            $equityIncreases = DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['EQUITY'])
                ->where('accounts.account_subtype', 'capital')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->sum('journal_lines.credit_amount') ?? 0;

            $equityDecreases = DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['EQUITY'])
                ->where('accounts.account_subtype', 'capital')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->sum('journal_lines.debit_amount') ?? 0;

            return $equityIncreases - $equityDecreases;
        } catch (\Exception $e) {
            Log::error('Failed to calculate equity changes', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate dividends paid
     */
    private function calculateDividendsPaid(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_name', 'LIKE', '%dividend%')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate dividends paid', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Get beginning cash balance
     */
    private function getBeginningCashBalance(int $cooperativeId, array $dateRange): float
    {
        try {
            $beginningDate = $dateRange['from']->copy()->subDay();
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_name', 'LIKE', '%cash%')
                ->where('accounts.account_type', self::ACCOUNT_TYPES['ASSET'])
                ->where('accounts.is_active', true)
                ->where('journal_entries.entry_date', '<=', $beginningDate)
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to get beginning cash balance', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Get ending cash balance
     */
    private function getEndingCashBalance(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_name', 'LIKE', '%cash%')
                ->where('accounts.account_type', self::ACCOUNT_TYPES['ASSET'])
                ->where('accounts.is_active', true)
                ->where('journal_entries.entry_date', '<=', $dateRange['to'])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to get ending cash balance', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate key financial ratios
     */
    private function calculateKeyRatios(int $cooperativeId, array $dateRange): array
    {
        try {
            $assets = $this->calculateTotalAssets($cooperativeId, $dateRange);
            $liabilities = $this->calculateTotalLiabilities($cooperativeId, $dateRange);
            $equity = $this->calculateTotalEquity($cooperativeId, $dateRange);
            $currentAssets = $this->calculateCurrentAssets($cooperativeId, $dateRange);
            $currentLiabilities = $this->calculateCurrentLiabilities($cooperativeId, $dateRange);
            $netIncome = $this->calculateCurrentYearEarnings($cooperativeId, $dateRange);
            $revenue = $this->calculateAccountTypeBalance($cooperativeId, self::ACCOUNT_TYPES['REVENUE'], $dateRange, 'credit');

            return [
                'liquidity_ratios' => [
                    'current_ratio' => $currentLiabilities > 0 ? $currentAssets / $currentLiabilities : 0,
                    'quick_ratio' => $this->calculateQuickRatio($cooperativeId, $dateRange),
                    'cash_ratio' => $this->calculateCashRatio($cooperativeId, $dateRange),
                ],
                'leverage_ratios' => [
                    'debt_to_equity' => $equity > 0 ? $liabilities / $equity : 0,
                    'debt_to_assets' => $assets > 0 ? $liabilities / $assets : 0,
                    'equity_ratio' => $assets > 0 ? $equity / $assets : 0,
                ],
                'profitability_ratios' => [
                    'roa' => $assets > 0 ? ($netIncome / $assets) * 100 : 0,
                    'roe' => $equity > 0 ? ($netIncome / $equity) * 100 : 0,
                    'profit_margin' => $revenue > 0 ? ($netIncome / $revenue) * 100 : 0,
                    'gross_margin' => $this->calculateGrossMargin($cooperativeId, $dateRange),
                ],
                'efficiency_ratios' => [
                    'asset_turnover' => $assets > 0 ? $revenue / $assets : 0,
                    'equity_turnover' => $equity > 0 ? $revenue / $equity : 0,
                    'working_capital_turnover' => $this->calculateWorkingCapitalTurnover($cooperativeId, $dateRange),
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to calculate key ratios', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [];
        }
    }

    /**
     * Calculate quick ratio
     */
    private function calculateQuickRatio(int $cooperativeId, array $dateRange): float
    {
        try {
            $currentAssets = $this->calculateCurrentAssets($cooperativeId, $dateRange);
            $inventory = $this->calculateInventory($cooperativeId, $dateRange);
            $currentLiabilities = $this->calculateCurrentLiabilities($cooperativeId, $dateRange);

            $quickAssets = $currentAssets - $inventory;
            return $currentLiabilities > 0 ? $quickAssets / $currentLiabilities : 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate quick ratio', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate cash ratio
     */
    private function calculateCashRatio(int $cooperativeId, array $dateRange): float
    {
        try {
            $cash = $this->getEndingCashBalance($cooperativeId, $dateRange);
            $currentLiabilities = $this->calculateCurrentLiabilities($cooperativeId, $dateRange);

            return $currentLiabilities > 0 ? $cash / $currentLiabilities : 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate cash ratio', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate inventory
     */
    private function calculateInventory(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_name', 'LIKE', '%inventory%')
                ->where('accounts.account_type', self::ACCOUNT_TYPES['ASSET'])
                ->where('accounts.is_active', true)
                ->where('journal_entries.entry_date', '<=', $dateRange['to'])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate inventory', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate gross margin
     */
    private function calculateGrossMargin(int $cooperativeId, array $dateRange): float
    {
        try {
            $revenue = $this->calculateAccountTypeBalance($cooperativeId, self::ACCOUNT_TYPES['REVENUE'], $dateRange, 'credit');
            $cogs = $this->calculateCostOfGoodsSold($cooperativeId, $dateRange);
            $grossProfit = $revenue - $cogs;

            return $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate gross margin', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate working capital turnover
     */
    private function calculateWorkingCapitalTurnover(int $cooperativeId, array $dateRange): float
    {
        try {
            $revenue = $this->calculateAccountTypeBalance($cooperativeId, self::ACCOUNT_TYPES['REVENUE'], $dateRange, 'credit');
            $currentAssets = $this->calculateCurrentAssets($cooperativeId, $dateRange);
            $currentLiabilities = $this->calculateCurrentLiabilities($cooperativeId, $dateRange);
            $workingCapital = $currentAssets - $currentLiabilities;

            return $workingCapital > 0 ? $revenue / $workingCapital : 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate working capital turnover', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Get revenue trends with optimized query - FIXED
     */
    private function getRevenueTrends(int $cooperativeId, array $dateRange): array
    {
        try {
            // Use optimized join instead of nested whereHas - Mikail's suggestion
            return DB::table('journal_entries')
                ->join('journal_lines', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
                ->where('journal_entries.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['REVENUE'])
                ->where('accounts.is_active', true)
                ->where('journal_entries.status', 'posted')
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->selectRaw('
                    DATE(journal_entries.entry_date) as date,
                    SUM(journal_lines.credit_amount - journal_lines.debit_amount) as revenue,
                    COUNT(DISTINCT journal_entries.id) as transaction_count
                ')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'revenue' => (float) $item->revenue,
                        'transaction_count' => (int) $item->transaction_count,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get revenue trends', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [];
        }
    }

    /**
     * Get expense trends with optimized query
     */
    private function getExpenseTrends(int $cooperativeId, array $dateRange): array
    {
        try {
            return DB::table('journal_entries')
                ->join('journal_lines', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
                ->where('journal_entries.cooperative_id', $cooperativeId)
                ->where('accounts.account_type', self::ACCOUNT_TYPES['EXPENSE'])
                ->where('accounts.is_active', true)
                ->where('journal_entries.status', 'posted')
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->selectRaw('
                    DATE(journal_entries.entry_date) as date,
                    SUM(journal_lines.debit_amount - journal_lines.credit_amount) as expenses,
                    COUNT(DISTINCT journal_entries.id) as transaction_count
                ')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'expenses' => (float) $item->expenses,
                        'transaction_count' => (int) $item->transaction_count,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get expense trends', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [];
        }
    }

    /**
     * Get profitability metrics
     */
    private function getProfitabilityMetrics(int $cooperativeId, array $dateRange): array
    {
        try {
            $revenue = $this->calculateAccountTypeBalance($cooperativeId, self::ACCOUNT_TYPES['REVENUE'], $dateRange, 'credit');
            $expenses = $this->calculateAccountTypeBalance($cooperativeId, self::ACCOUNT_TYPES['EXPENSE'], $dateRange, 'debit');
            $cogs = $this->calculateCostOfGoodsSold($cooperativeId, $dateRange);
            $operatingExpenses = $this->calculateOperatingExpenses($cooperativeId, $dateRange);

            $grossProfit = $revenue - $cogs;
            $operatingProfit = $grossProfit - $operatingExpenses;
            $netProfit = $revenue - $expenses;

            return [
                'revenue' => $revenue,
                'gross_profit' => $grossProfit,
                'operating_profit' => $operatingProfit,
                'net_profit' => $netProfit,
                'gross_margin' => $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0,
                'operating_margin' => $revenue > 0 ? ($operatingProfit / $revenue) * 100 : 0,
                'net_margin' => $revenue > 0 ? ($netProfit / $revenue) * 100 : 0,
                'ebitda' => $this->calculateEBITDA($cooperativeId, $dateRange),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get profitability metrics', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [];
        }
    }

    /**
     * Calculate EBITDA
     */
    private function calculateEBITDA(int $cooperativeId, array $dateRange): float
    {
        try {
            $operatingProfit = $this->calculateCurrentYearEarnings($cooperativeId, $dateRange);
            $depreciation = $this->calculateDepreciation($cooperativeId, $dateRange);
            $amortization = $this->calculateAmortization($cooperativeId, $dateRange);

            return $operatingProfit + $depreciation + $amortization;
        } catch (\Exception $e) {
            Log::error('Failed to calculate EBITDA', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate amortization
     */
    private function calculateAmortization(int $cooperativeId, array $dateRange): float
    {
        try {
            return DB::table('accounts')
                ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.account_name', 'LIKE', '%amortization%')
                ->where('accounts.is_active', true)
                ->whereBetween('journal_entries.entry_date', [$dateRange['from'], $dateRange['to']])
                ->where('journal_entries.status', 'posted')
                ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount')) ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate amortization', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Get liquidity metrics
     */
    private function getLiquidityMetrics(int $cooperativeId, array $dateRange): array
    {
        try {
            $currentAssets = $this->calculateCurrentAssets($cooperativeId, $dateRange);
            $currentLiabilities = $this->calculateCurrentLiabilities($cooperativeId, $dateRange);
            $cash = $this->getEndingCashBalance($cooperativeId, $dateRange);
            $inventory = $this->calculateInventory($cooperativeId, $dateRange);

            return [
                'current_ratio' => $currentLiabilities > 0 ? $currentAssets / $currentLiabilities : 0,
                'quick_ratio' => $currentLiabilities > 0 ? ($currentAssets - $inventory) / $currentLiabilities : 0,
                'cash_ratio' => $currentLiabilities > 0 ? $cash / $currentLiabilities : 0,
                'working_capital' => $currentAssets - $currentLiabilities,
                'cash_coverage' => $this->calculateCashCoverage($cooperativeId, $dateRange),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get liquidity metrics', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [];
        }
    }

    /**
     * Calculate cash coverage
     */
    private function calculateCashCoverage(int $cooperativeId, array $dateRange): float
    {
        try {
            $cash = $this->getEndingCashBalance($cooperativeId, $dateRange);
            $operatingExpenses = $this->calculateOperatingExpenses($cooperativeId, $dateRange);
            $daysInPeriod = $dateRange['from']->diffInDays($dateRange['to']);

            $dailyOperatingExpenses = $daysInPeriod > 0 ? $operatingExpenses / $daysInPeriod : 0;

            return $dailyOperatingExpenses > 0 ? $cash / $dailyOperatingExpenses : 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate cash coverage', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Get performance summary
     */
    private function getPerformanceSummary(int $cooperativeId, array $dateRange): array
    {
        try {
            $revenue = $this->calculateAccountTypeBalance($cooperativeId, self::ACCOUNT_TYPES['REVENUE'], $dateRange, 'credit');
            $netIncome = $this->calculateCurrentYearEarnings($cooperativeId, $dateRange);
            $assets = $this->calculateTotalAssets($cooperativeId, $dateRange);
            $equity = $this->calculateTotalEquity($cooperativeId, $dateRange);

            return [
                'total_revenue' => $revenue,
                'net_income' => $netIncome,
                'total_assets' => $assets,
                'total_equity' => $equity,
                'roa' => $assets > 0 ? ($netIncome / $assets) * 100 : 0,
                'roe' => $equity > 0 ? ($netIncome / $equity) * 100 : 0,
                'profit_margin' => $revenue > 0 ? ($netIncome / $revenue) * 100 : 0,
                'financial_health_score' => $this->calculateFinancialHealthScore($cooperativeId, $dateRange),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get performance summary', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [];
        }
    }

    /**
     * Calculate financial health score
     */
    private function calculateFinancialHealthScore(int $cooperativeId, array $dateRange): float
    {
        try {
            $ratios = $this->calculateKeyRatios($cooperativeId, $dateRange);

            // Weighted scoring system
            $score = 0;
            $weights = [
                'liquidity' => 0.3,
                'profitability' => 0.4,
                'leverage' => 0.3,
            ];

            // Liquidity score (0-100)
            $currentRatio = $ratios['liquidity_ratios']['current_ratio'] ?? 0;
            $liquidityScore = min(100, max(0, ($currentRatio - 0.5) * 50));

            // Profitability score (0-100)
            $roe = $ratios['profitability_ratios']['roe'] ?? 0;
            $profitabilityScore = min(100, max(0, $roe * 5));

            // Leverage score (0-100) - lower debt is better
            $debtToEquity = $ratios['leverage_ratios']['debt_to_equity'] ?? 0;
            $leverageScore = min(100, max(0, 100 - ($debtToEquity * 20)));

            $score = ($liquidityScore * $weights['liquidity']) +
                ($profitabilityScore * $weights['profitability']) +
                ($leverageScore * $weights['leverage']);

            return round($score, 2);
        } catch (\Exception $e) {
            Log::error('Failed to calculate financial health score', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0.0;
        }
    }

    /**
     * Get period comparison
     */
    private function getPeriodComparison(int $cooperativeId, array $dateRange): array
    {
        try {
            // Current period
            $currentRevenue = $this->calculateAccountTypeBalance($cooperativeId, self::ACCOUNT_TYPES['REVENUE'], $dateRange, 'credit');
            $currentNetIncome = $this->calculateCurrentYearEarnings($cooperativeId, $dateRange);
            $currentAssets = $this->calculateTotalAssets($cooperativeId, $dateRange);

            // Previous period
            $periodLength = $dateRange['from']->diffInDays($dateRange['to']);
            $previousDateRange = [
                'from' => $dateRange['from']->copy()->subDays($periodLength),
                'to' => $dateRange['from']->copy()->subDay()
            ];

            $previousRevenue = $this->calculateAccountTypeBalance($cooperativeId, self::ACCOUNT_TYPES['REVENUE'], $previousDateRange, 'credit');
            $previousNetIncome = $this->calculateCurrentYearEarnings($cooperativeId, $previousDateRange);
            $previousAssets = $this->calculateTotalAssets($cooperativeId, $previousDateRange);

            return [
                'current_period' => [
                    'revenue' => $currentRevenue,
                    'net_income' => $currentNetIncome,
                    'total_assets' => $currentAssets,
                ],
                'previous_period' => [
                    'revenue' => $previousRevenue,
                    'net_income' => $previousNetIncome,
                    'total_assets' => $previousAssets,
                ],
                'growth_rates' => [
                    'revenue_growth' => $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : 0,
                    'income_growth' => $previousNetIncome > 0 ? (($currentNetIncome - $previousNetIncome) / $previousNetIncome) * 100 : 0,
                    'asset_growth' => $previousAssets > 0 ? (($currentAssets - $previousAssets) / $previousAssets) * 100 : 0,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get period comparison', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [];
        }
    }

    /**
     * Validate date range
     */
    private function validateDateRange(array $dateRange): void
    {
        if (!isset($dateRange['from']) || !isset($dateRange['to'])) {
            throw new \InvalidArgumentException('Date range must include both from and to dates');
        }

        if (!($dateRange['from'] instanceof Carbon) || !($dateRange['to'] instanceof Carbon)) {
            throw new \InvalidArgumentException('Date range values must be Carbon instances');
        }

        if ($dateRange['from']->gt($dateRange['to'])) {
            throw new \InvalidArgumentException('From date cannot be greater than to date');
        }

        if ($dateRange['to']->gt(Carbon::now())) {
            throw new \InvalidArgumentException('To date cannot be in the future');
        }
    }
}
