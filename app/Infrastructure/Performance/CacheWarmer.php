<?php
// app/Infrastructure/Performance/CacheWarmer.php
namespace App\Infrastructure\Performance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Domain\Cooperative\Services\CooperativeService;
use App\Domain\Member\Services\MemberService;
use App\Domain\Financial\Services\AccountService;

class CacheWarmer
{
    public function __construct(
        private CooperativeService $cooperativeService,
        private MemberService $memberService,
        private AccountService $accountService
    ) {}

    /**
     * Warm cooperative cache
     */
    public function warmCooperativeCache(int $cooperativeId): void
    {
        try {
            Log::info("Starting cache warming for cooperative: {$cooperativeId}");

            // Warm cooperative statistics
            CacheManager::remember('cooperative_stats', ['id' => $cooperativeId], function () use ($cooperativeId) {
                return $this->cooperativeService->getStatistics($cooperativeId);
            });

            // Warm member count
            CacheManager::remember('cooperative_member_count', ['id' => $cooperativeId], function () use ($cooperativeId) {
                return $this->memberService->getActiveCount($cooperativeId);
            });

            // Warm financial summary
            CacheManager::remember('financial_summary', ['id' => $cooperativeId], function () use ($cooperativeId) {
                return $this->accountService->getFinancialSummary($cooperativeId);
            });

            // Warm dashboard widgets
            $this->warmDashboardWidgets($cooperativeId);

            Log::info("Cache warming completed for cooperative: {$cooperativeId}");
        } catch (\Exception $e) {
            Log::error("Cache warming failed for cooperative: {$cooperativeId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Warm dashboard widgets cache
     */
    private function warmDashboardWidgets(int $cooperativeId): void
    {
        $widgets = [
            'total_members',
            'total_savings',
            'total_loans',
            'monthly_growth',
            'recent_transactions',
        ];

        foreach ($widgets as $widget) {
            try {
                $cacheKey = "dashboard_widget:{$widget}:{$cooperativeId}";

                Cache::remember($cacheKey, 600, function () use ($widget, $cooperativeId) {
                    return $this->generateWidgetData($widget, $cooperativeId);
                });
            } catch (\Exception $e) {
                Log::error("Failed to warm widget cache: {$widget}", [
                    'cooperative_id' => $cooperativeId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Generate widget data
     */
    private function generateWidgetData(string $widget, int $cooperativeId): array
    {
        return match ($widget) {
            'total_members' => [
                'value' => $this->memberService->getActiveCount($cooperativeId),
                'change' => $this->memberService->getGrowthRate($cooperativeId),
            ],
            'total_savings' => [
                'value' => $this->accountService->getTotalSavings($cooperativeId),
                'change' => $this->accountService->getSavingsGrowthRate($cooperativeId),
            ],
            'total_loans' => [
                'value' => $this->accountService->getTotalLoans($cooperativeId),
                'change' => $this->accountService->getLoansGrowthRate($cooperativeId),
            ],
            'monthly_growth' => [
                'value' => $this->cooperativeService->getMonthlyGrowthRate($cooperativeId),
                'trend' => $this->cooperativeService->getGrowthTrend($cooperativeId),
            ],
            'recent_transactions' => [
                'transactions' => $this->accountService->getRecentTransactions($cooperativeId, 10),
                'total_amount' => $this->accountService->getRecentTransactionsTotal($cooperativeId),
            ],
            default => [],
        };
    }

    /**
     * Warm all active cooperatives cache
     */
    public function warmAllCooperatives(): void
    {
        try {
            $activeCooperatives = $this->cooperativeService->getActiveCooperativeIds();

            Log::info("Starting cache warming for all cooperatives", [
                'count' => count($activeCooperatives),
            ]);

            foreach ($activeCooperatives as $cooperativeId) {
                $this->warmCooperativeCache($cooperativeId);

                // Add small delay to prevent overwhelming the system
                usleep(100000); // 100ms
            }

            Log::info("Cache warming completed for all cooperatives");
        } catch (\Exception $e) {
            Log::error("Failed to warm cache for all cooperatives", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Schedule cache warming
     */
    public function scheduleWarming(): void
    {
        // This would typically be called from a scheduled job
        $this->warmAllCooperatives();
    }
}
