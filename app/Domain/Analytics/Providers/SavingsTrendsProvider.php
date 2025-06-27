<?php
// app/Domain/Analytics/Providers/SavingsTrendsProvider.php
namespace App\Domain\Analytics\Providers;

use App\Domain\Analytics\Contracts\AnalyticsProviderInterface;
use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;
use App\Domain\Analytics\DTOs\WidgetDataDTO;
use App\Domain\Savings\Models\SavingsAccount;
use App\Domain\Savings\Models\SavingsTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Savings Trends Analytics Provider - Enhanced Version
 * SRS Reference: Section 3.6.5 - Savings Analytics
 *
 * @author Mateen (Senior Software Engineer)
 * @version 2.0 - Enhanced with database validation and optimized queries
 */
class SavingsTrendsProvider implements AnalyticsProviderInterface
{
    private array $requiredTables = ['savings_accounts', 'savings_transactions'];
    private array $requiredColumns = [
        'savings_accounts' => ['cooperative_id', 'balance', 'status', 'account_type'],
        'savings_transactions' => ['cooperative_id', 'transaction_date', 'transaction_type', 'amount']
    ];

    public function __construct()
    {
        $this->validateTableStructure();
    }

    public function generate(AnalyticsRequestDTO $request): WidgetDataDTO
    {
        try {
            $startTime = microtime(true);
            $dateRange = $request->getDateRange();

            // Optimized single query approach - Mikail's suggestion
            $savingsStats = $this->getSavingsStatistics($request->cooperativeId);

            $savingsData = [
                'total_savings' => $savingsStats['total_savings'],
                'total_accounts' => $savingsStats['total_accounts'],
                'average_balance' => $savingsStats['average_balance'],
                'growth_rate' => $this->calculateGrowthRate($request->cooperativeId, $dateRange),
                'trends' => $this->getSavingsTrends($request->cooperativeId, $dateRange),
                'distribution' => $this->getSavingsDistribution($request->cooperativeId),
                'top_savers' => $this->getTopSavers($request->cooperativeId, 10),
                'monthly_deposits' => $this->getMonthlyDeposits($request->cooperativeId, $dateRange),
                'performance_metrics' => [
                    'execution_time' => microtime(true) - $startTime,
                    'query_count' => DB::getQueryLog() ? count(DB::getQueryLog()) : 0
                ]
            ];

            return WidgetDataDTO::savings(
                title: 'Savings Trends',
                data: $savingsData,
                chartConfig: $this->getDefaultChartConfig(),
                description: 'Comprehensive savings analytics and trends'
            )->addMetadata('provider_version', '2.0')
                ->addMetadata('last_updated', Carbon::now()->toISOString());
        } catch (\Exception $e) {
            Log::error('SavingsTrendsProvider generation failed', [
                'error' => $e->getMessage(),
                'cooperative_id' => $request->cooperativeId,
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException(
                "Failed to generate savings trends: {$e->getMessage()}. Please check system logs for details.",
                $e->getCode(),
                $e
            );
        }
    }

    public function getName(): string
    {
        return 'Savings Trends';
    }

    public function getDescription(): string
    {
        return 'Savings account trends, growth patterns, and member savings behavior analysis';
    }

    public function getRequiredPermissions(): array
    {
        return ['view_savings_accounts', 'view_savings_reports'];
    }

    public function getCacheKey(AnalyticsRequestDTO $request): string
    {
        return "savings_trends:{$request->cooperativeId}:{$request->period}:" . md5(serialize($request->filters));
    }

    public function getCacheTTL(): int
    {
        return config('analytics.cache.savings_trends_ttl', 1800); // 30 minutes
    }

    public function validate(AnalyticsRequestDTO $request): bool
    {
        if ($request->cooperativeId <= 0) {
            return false;
        }

        // Check if cooperative has savings accounts
        $hasAccounts = SavingsAccount::where('cooperative_id', $request->cooperativeId)->exists();
        if (!$hasAccounts) {
            Log::info('No savings accounts found for cooperative', [
                'cooperative_id' => $request->cooperativeId
            ]);
        }

        return true;
    }

    public function getSupportedMetrics(): array
    {
        return [
            'total_savings',
            'total_accounts',
            'average_balance',
            'growth_rate',
            'deposit_frequency',
            'withdrawal_frequency'
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
            'cache_ttl' => $this->getCacheTTL(),
            'real_time' => true,
            'supported_periods' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'],
            'max_top_savers' => config('analytics.savings.max_top_savers', 50),
            'min_balance_for_analysis' => config('analytics.savings.min_balance', 0)
        ];
    }

    public function getWidgetType(): string
    {
        return 'savings';
    }

    public function getDefaultChartConfig(): array
    {
        return [
            'type' => 'line',
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'ticks' => [
                            'callback' => 'function(value) { return "Rp " + new Intl.NumberFormat("id-ID").format(value); }'
                        ]
                    ],
                    'x' => [
                        'type' => 'time',
                        'time' => [
                            'unit' => 'day'
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
                ],
                'interaction' => [
                    'mode' => 'nearest',
                    'axis' => 'x',
                    'intersect' => false
                ]
            ]
        ];
    }

    public function supportsPeriod(string $period): bool
    {
        return in_array($period, ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']);
    }

    /**
     * Validate table structure - Mikail's suggestion
     */
    private function validateTableStructure(): void
    {
        foreach ($this->requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                throw new \RuntimeException("Required table '{$table}' not found in database schema");
            }
        }

        foreach ($this->requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    throw new \RuntimeException("Required column '{$column}' not found in table '{$table}'");
                }
            }
        }
    }

    /**
     * Get savings statistics with optimized single query - Mikail's suggestion
     */
    private function getSavingsStatistics(int $cooperativeId): array
    {
        $stats = SavingsAccount::where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->selectRaw('
                COUNT(*) as total_accounts,
                SUM(balance) as total_savings,
                AVG(balance) as average_balance,
                MIN(balance) as min_balance,
                MAX(balance) as max_balance
            ')
            ->first();

        return [
            'total_accounts' => $stats->total_accounts ?? 0,
            'total_savings' => $stats->total_savings ?? 0,
            'average_balance' => $stats->average_balance ?? 0,
            'min_balance' => $stats->min_balance ?? 0,
            'max_balance' => $stats->max_balance ?? 0,
        ];
    }

    /**
     * Calculate savings growth rate with error handling
     */
    private function calculateGrowthRate(int $cooperativeId, array $dateRange): float
    {
        try {
            $currentPeriodSavings = SavingsAccount::where('cooperative_id', $cooperativeId)
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                ->sum('balance');

            $previousPeriodStart = $dateRange['from']->copy()->sub($dateRange['to']->diff($dateRange['from']));
            $previousPeriodSavings = SavingsAccount::where('cooperative_id', $cooperativeId)
                ->whereBetween('created_at', [$previousPeriodStart, $dateRange['from']])
                ->sum('balance');

            if ($previousPeriodSavings == 0) {
                return $currentPeriodSavings > 0 ? 100 : 0;
            }

            return (($currentPeriodSavings - $previousPeriodSavings) / $previousPeriodSavings) * 100;
        } catch (\Exception $e) {
            Log::warning('Failed to calculate savings growth rate', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return 0;
        }
    }

    /**
     * Get savings trends over time with optimized query
     */
    private function getSavingsTrends(int $cooperativeId, array $dateRange): array
    {
        try {
            return SavingsTransaction::where('cooperative_id', $cooperativeId)
                ->whereBetween('transaction_date', [$dateRange['from'], $dateRange['to']])
                ->where('transaction_type', 'deposit')
                ->selectRaw('
                    DATE(transaction_date) as date,
                    SUM(amount) as total_deposits,
                    COUNT(*) as transaction_count,
                    AVG(amount) as average_deposit
                ')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'total_deposits' => (float) $item->total_deposits,
                        'transaction_count' => (int) $item->transaction_count,
                        'average_deposit' => (float) $item->average_deposit,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get savings trends', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [];
        }
    }

    /**
     * Get savings distribution by account type
     */
    private function getSavingsDistribution(int $cooperativeId): array
    {
        try {
            return SavingsAccount::where('cooperative_id', $cooperativeId)
                ->where('status', 'active')
                ->selectRaw('
                    account_type,
                    COUNT(*) as account_count,
                    SUM(balance) as total_balance,
                    AVG(balance) as average_balance
                ')
                ->groupBy('account_type')
                ->get()
                ->map(function ($item) {
                    return [
                        'account_type' => $item->account_type,
                        'account_count' => (int) $item->account_count,
                        'total_balance' => (float) $item->total_balance,
                        'average_balance' => (float) $item->average_balance,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get savings distribution', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [];
        }
    }

    /**
     * Get top savers with configurable limit
     */
    private function getTopSavers(int $cooperativeId, int $limit = 10): array
    {
        try {
            $maxLimit = config('analytics.savings.max_top_savers', 50);
            $limit = min($limit, $maxLimit);

            return SavingsAccount::where('cooperative_id', $cooperativeId)
                ->where('status', 'active')
                ->with('member:id,full_name,member_number')
                ->orderBy('balance', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($account) {
                    return [
                        'member_name' => $account->member->full_name ?? 'Unknown',
                        'member_number' => $account->member->member_number ?? 'N/A',
                        'account_number' => $account->account_number,
                        'balance' => (float) $account->balance,
                        'account_type' => $account->account_type,
                        'created_at' => $account->created_at->toISOString(),
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get top savers', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [];
        }
    }

    /**
     * Get monthly deposits with enhanced data
     */
    private function getMonthlyDeposits(int $cooperativeId, array $dateRange): array
    {
        try {
            return SavingsTransaction::where('cooperative_id', $cooperativeId)
                ->whereBetween('transaction_date', [$dateRange['from'], $dateRange['to']])
                ->where('transaction_type', 'deposit')
                ->selectRaw('
                    YEAR(transaction_date) as year,
                    MONTH(transaction_date) as month,
                    SUM(amount) as total_amount,
                    COUNT(*) as transaction_count,
                    AVG(amount) as average_amount,
                    COUNT(DISTINCT savings_account_id) as unique_accounts
                ')
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'year' => (int) $item->year,
                        'month' => (int) $item->month,
                        'period' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                        'total_amount' => (float) $item->total_amount,
                        'transaction_count' => (int) $item->transaction_count,
                        'average_amount' => (float) $item->average_amount,
                        'unique_accounts' => (int) $item->unique_accounts,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get monthly deposits', [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperativeId
            ]);
            return [];
        }
    }
}
