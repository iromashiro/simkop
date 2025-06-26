<?php
// app/Domain/Analytics/Services/DashboardService.php - ENHANCED
namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Models\DashboardWidget;
use App\Domain\Analytics\DTOs\CreateWidgetDTO;
use App\Domain\Analytics\DTOs\UpdateWidgetDTO;
use App\Domain\Analytics\Exceptions\InvalidWidgetConfigurationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DashboardService
{
    public function __construct(
        private readonly KPIService $kpiService,
        private readonly AnalyticsService $analyticsService
    ) {}

    /**
     * ✅ FIXED: Validate widget configuration before creation/update
     */
    public function validateWidgetConfiguration(string $widgetType, array $configuration): array
    {
        $errors = [];

        $validationRules = [
            'kpi_summary' => [
                'period' => 'required|in:daily,weekly,monthly,quarterly,yearly',
                'show_comparison' => 'boolean',
                'kpi_types' => 'array|min:1',
                'kpi_types.*' => 'string|in:financial,member,loan,savings,operational',
            ],
            'financial_overview' => [
                'period' => 'required|in:monthly,quarterly,yearly',
                'show_comparison' => 'boolean',
                'comparison_period' => 'string|in:previous_year,previous_quarter,previous_month',
                'include_forecast' => 'boolean',
                'chart_type' => 'string|in:line,bar,area',
            ],
            'member_statistics' => [
                'chart_type' => 'required|in:pie,doughnut,bar,line',
                'show_growth_rate' => 'boolean',
                'breakdown_by' => 'string|in:status,type,registration_date',
                'time_range' => 'string|in:last_30_days,last_90_days,last_year,all_time',
            ],
            'transaction_chart' => [
                'period' => 'required|in:daily,weekly,monthly',
                'chart_type' => 'required|in:line,bar,area,pie',
                'transaction_types' => 'array',
                'transaction_types.*' => 'string|in:savings,loan,deposit,withdrawal,shu,fee',
                'show_trend_line' => 'boolean',
                'group_by' => 'string|in:type,amount_range,member_type',
            ],
            'loan_portfolio' => [
                'show_details' => 'boolean',
                'breakdown_by' => 'string|in:status,amount_range,duration,member_type',
                'include_risk_analysis' => 'boolean',
                'chart_type' => 'string|in:pie,bar,stacked_bar',
            ],
            'savings_growth' => [
                'period' => 'required|in:monthly,quarterly,yearly',
                'chart_type' => 'required|in:line,area,bar',
                'show_target_line' => 'boolean',
                'target_amount' => 'numeric|min:0',
                'breakdown_by' => 'string|in:member_type,account_type',
            ],
            'recent_activities' => [
                'limit' => 'required|integer|min:5|max:50',
                'activity_types' => 'array',
                'activity_types.*' => 'string|in:login,transaction,member_registration,loan_application,report_generation',
                'show_user_details' => 'boolean',
                'time_range' => 'string|in:last_hour,last_24_hours,last_week',
            ],
        ];

        $rules = $validationRules[$widgetType] ?? [];

        if (empty($rules)) {
            $errors[] = "Unknown widget type: {$widgetType}";
            return $errors;
        }

        $validator = Validator::make($configuration, $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $errors[] = $error;
            }
        }

        // Additional business logic validation
        $businessValidationErrors = $this->validateBusinessRules($widgetType, $configuration);
        $errors = array_merge($errors, $businessValidationErrors);

        return $errors;
    }

    /**
     * Validate business rules for widget configuration
     */
    private function validateBusinessRules(string $widgetType, array $configuration): array
    {
        $errors = [];

        switch ($widgetType) {
            case 'kpi_summary':
                if (isset($configuration['kpi_types']) && count($configuration['kpi_types']) > 6) {
                    $errors[] = 'KPI Summary widget cannot display more than 6 KPI types';
                }
                break;

            case 'transaction_chart':
                if (isset($configuration['transaction_types']) && count($configuration['transaction_types']) > 8) {
                    $errors[] = 'Transaction Chart widget cannot display more than 8 transaction types';
                }
                if ($configuration['period'] === 'daily' && $configuration['chart_type'] === 'pie') {
                    $errors[] = 'Pie chart is not suitable for daily transaction data';
                }
                break;

            case 'savings_growth':
                if (isset($configuration['show_target_line']) && $configuration['show_target_line'] && !isset($configuration['target_amount'])) {
                    $errors[] = 'Target amount is required when showing target line';
                }
                break;

            case 'recent_activities':
                if (isset($configuration['limit']) && $configuration['limit'] > 20 && $configuration['show_user_details']) {
                    $errors[] = 'Cannot show user details for more than 20 activities due to performance constraints';
                }
                break;
        }

        return $errors;
    }

    /**
     * Get widget configuration schema for frontend
     */
    public function getWidgetConfigurationSchema(string $widgetType): array
    {
        $schemas = [
            'kpi_summary' => [
                'fields' => [
                    'period' => [
                        'type' => 'select',
                        'label' => 'Time Period',
                        'options' => [
                            'daily' => 'Daily',
                            'weekly' => 'Weekly',
                            'monthly' => 'Monthly',
                            'quarterly' => 'Quarterly',
                            'yearly' => 'Yearly',
                        ],
                        'default' => 'monthly',
                        'required' => true,
                    ],
                    'show_comparison' => [
                        'type' => 'boolean',
                        'label' => 'Show Comparison',
                        'default' => true,
                    ],
                    'kpi_types' => [
                        'type' => 'multiselect',
                        'label' => 'KPI Categories',
                        'options' => [
                            'financial' => 'Financial KPIs',
                            'member' => 'Member KPIs',
                            'loan' => 'Loan KPIs',
                            'savings' => 'Savings KPIs',
                            'operational' => 'Operational KPIs',
                        ],
                        'default' => ['financial', 'member'],
                        'max_selections' => 6,
                    ],
                ],
            ],
            'financial_overview' => [
                'fields' => [
                    'period' => [
                        'type' => 'select',
                        'label' => 'Time Period',
                        'options' => [
                            'monthly' => 'Monthly',
                            'quarterly' => 'Quarterly',
                            'yearly' => 'Yearly',
                        ],
                        'default' => 'monthly',
                        'required' => true,
                    ],
                    'show_comparison' => [
                        'type' => 'boolean',
                        'label' => 'Show Period Comparison',
                        'default' => true,
                    ],
                    'comparison_period' => [
                        'type' => 'select',
                        'label' => 'Compare With',
                        'options' => [
                            'previous_year' => 'Previous Year',
                            'previous_quarter' => 'Previous Quarter',
                            'previous_month' => 'Previous Month',
                        ],
                        'default' => 'previous_year',
                        'depends_on' => 'show_comparison',
                    ],
                    'include_forecast' => [
                        'type' => 'boolean',
                        'label' => 'Include Forecast',
                        'default' => false,
                    ],
                    'chart_type' => [
                        'type' => 'select',
                        'label' => 'Chart Type',
                        'options' => [
                            'line' => 'Line Chart',
                            'bar' => 'Bar Chart',
                            'area' => 'Area Chart',
                        ],
                        'default' => 'line',
                    ],
                ],
            ],
            // Add more widget schemas...
        ];

        return $schemas[$widgetType] ?? [];
    }

    public function createWidget(CreateWidgetDTO $dto): DashboardWidget
    {
        // ✅ FIXED: Add configuration validation
        $validationErrors = $this->validateWidgetConfiguration($dto->widgetType, $dto->configuration);
        if (!empty($validationErrors)) {
            throw new InvalidWidgetConfigurationException(
                'Widget configuration validation failed: ' . implode(', ', $validationErrors),
                $validationErrors
            );
        }

        // Validate widget position doesn't overlap with existing widgets
        $this->validateWidgetPosition($dto->userId, $dto->cooperativeId, $dto->positionX, $dto->positionY, $dto->width, $dto->height);

        $widget = DashboardWidget::create([
            'cooperative_id' => $dto->cooperativeId,
            'user_id' => $dto->userId,
            'widget_type' => $dto->widgetType,
            'title' => $dto->title,
            'configuration' => $dto->configuration,
            'position_x' => $dto->positionX,
            'position_y' => $dto->positionY,
            'width' => $dto->width,
            'height' => $dto->height,
            'is_active' => $dto->isActive,
            'refresh_interval' => $dto->refreshInterval,
        ]);

        $this->clearUserDashboardCache($dto->userId, $dto->cooperativeId);

        Log::info('Dashboard widget created', [
            'widget_id' => $widget->id,
            'type' => $widget->widget_type,
            'user_id' => $dto->userId,
            'cooperative_id' => $dto->cooperativeId,
            'configuration' => $dto->configuration,
        ]);

        return $widget;
    }

    public function updateWidget(int $widgetId, UpdateWidgetDTO $dto): DashboardWidget
    {
        $widget = DashboardWidget::findOrFail($widgetId);

        // ✅ FIXED: Validate configuration if provided
        if ($dto->configuration !== null) {
            $validationErrors = $this->validateWidgetConfiguration($widget->widget_type, $dto->configuration);
            if (!empty($validationErrors)) {
                throw new InvalidWidgetConfigurationException(
                    'Widget configuration validation failed: ' . implode(', ', $validationErrors),
                    $validationErrors
                );
            }
        }

        // Validate new position if provided
        if ($dto->positionX !== null || $dto->positionY !== null || $dto->width !== null || $dto->height !== null) {
            $this->validateWidgetPosition(
                $widget->user_id,
                $widget->cooperative_id,
                $dto->positionX ?? $widget->position_x,
                $dto->positionY ?? $widget->position_y,
                $dto->width ?? $widget->width,
                $dto->height ?? $widget->height,
                $widgetId
            );
        }

        $widget->update(array_filter([
            'title' => $dto->title,
            'configuration' => $dto->configuration,
            'position_x' => $dto->positionX,
            'position_y' => $dto->positionY,
            'width' => $dto->width,
            'height' => $dto->height,
            'is_active' => $dto->isActive,
            'refresh_interval' => $dto->refreshInterval,
        ]));

        $this->clearUserDashboardCache($widget->user_id, $widget->cooperative_id);

        Log::info('Dashboard widget updated', [
            'widget_id' => $widgetId,
            'changes' => array_filter([
                'title' => $dto->title,
                'configuration' => $dto->configuration,
                'position_x' => $dto->positionX,
                'position_y' => $dto->positionY,
                'width' => $dto->width,
                'height' => $dto->height,
                'is_active' => $dto->isActive,
                'refresh_interval' => $dto->refreshInterval,
            ]),
        ]);

        return $widget;
    }

    /**
     * Validate widget position doesn't overlap
     */
    private function validateWidgetPosition(int $userId, int $cooperativeId, int $x, int $y, int $width, int $height, ?int $excludeWidgetId = null): void
    {
        $query = DashboardWidget::where('user_id', $userId)
            ->where('cooperative_id', $cooperativeId)
            ->where('is_active', true);

        if ($excludeWidgetId) {
            $query->where('id', '!=', $excludeWidgetId);
        }

        $existingWidgets = $query->get();

        foreach ($existingWidgets as $existingWidget) {
            if ($this->widgetsOverlap(
                $x,
                $y,
                $width,
                $height,
                $existingWidget->position_x,
                $existingWidget->position_y,
                $existingWidget->width,
                $existingWidget->height
            )) {
                throw new InvalidWidgetConfigurationException(
                    "Widget position overlaps with existing widget '{$existingWidget->title}'"
                );
            }
        }
    }

    /**
     * Check if two widgets overlap
     */
    private function widgetsOverlap(int $x1, int $y1, int $w1, int $h1, int $x2, int $y2, int $w2, int $h2): bool
    {
        return !($x1 >= $x2 + $w2 || $x2 >= $x1 + $w1 || $y1 >= $y2 + $h2 || $y2 >= $y1 + $h1);
    }

    /**
     * ✅ ENHANCED: Dashboard performance monitoring
     */
    public function getUserDashboard(int $userId, int $cooperativeId): array
    {
        $startTime = microtime(true);
        $cacheKey = "dashboard:user:{$userId}:cooperative:{$cooperativeId}";

        $dashboardData = Cache::remember($cacheKey, 900, function () use ($userId, $cooperativeId) {
            $widgets = DashboardWidget::where('user_id', $userId)
                ->where('cooperative_id', $cooperativeId)
                ->active()
                ->orderBy('position_y')
                ->orderBy('position_x')
                ->get();

            $dashboardData = [];

            foreach ($widgets as $widget) {
                $widgetStartTime = microtime(true);

                try {
                    if ($widget->needsRefresh()) {
                        $data = $this->getWidgetData($widget);
                        $widget->markAsRefreshed();
                    } else {
                        $data = $widget->configuration['cached_data'] ?? null;
                    }

                    $widgetLoadTime = microtime(true) - $widgetStartTime;

                    $dashboardData[] = [
                        'id' => $widget->id,
                        'type' => $widget->widget_type,
                        'title' => $widget->title,
                        'position' => [
                            'x' => $widget->position_x,
                            'y' => $widget->position_y,
                            'width' => $widget->width,
                            'height' => $widget->height,
                        ],
                        'data' => $data,
                        'configuration' => $widget->configuration,
                        'last_refreshed' => $widget->last_refreshed_at,
                        'load_time' => round($widgetLoadTime, 3),
                    ];

                    // Log slow widget loading
                    if ($widgetLoadTime > 1.0) {
                        Log::warning('Slow widget load detected', [
                            'widget_id' => $widget->id,
                            'widget_type' => $widget->widget_type,
                            'load_time' => $widgetLoadTime,
                            'user_id' => $userId,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Widget data loading failed', [
                        'widget_id' => $widget->id,
                        'widget_type' => $widget->widget_type,
                        'error' => $e->getMessage(),
                        'user_id' => $userId,
                    ]);

                    // Add error widget
                    $dashboardData[] = [
                        'id' => $widget->id,
                        'type' => $widget->widget_type,
                        'title' => $widget->title,
                        'position' => [
                            'x' => $widget->position_x,
                            'y' => $widget->position_y,
                            'width' => $widget->width,
                            'height' => $widget->height,
                        ],
                        'data' => null,
                        'error' => 'Failed to load widget data',
                        'configuration' => $widget->configuration,
                        'last_refreshed' => $widget->last_refreshed_at,
                    ];
                }
            }

            return $dashboardData;
        });

        $totalLoadTime = microtime(true) - $startTime;

        // ✅ FIXED: Log performance metrics
        if ($totalLoadTime > 2.0) { // Log if dashboard takes more than 2 seconds
            Log::warning('Slow dashboard load detected', [
                'user_id' => $userId,
                'cooperative_id' => $cooperativeId,
                'load_time' => $totalLoadTime,
                'widget_count' => count($dashboardData),
                'cache_hit' => Cache::has($cacheKey),
            ]);
        }

        // Add performance metadata
        $dashboardData['_metadata'] = [
            'load_time' => round($totalLoadTime, 3),
            'widget_count' => count($dashboardData) - 1, // Exclude metadata
            'cache_hit' => Cache::has($cacheKey),
            'generated_at' => now()->toISOString(),
        ];

        return $dashboardData;
    }

    public function deleteWidget(int $widgetId): bool
    {
        $widget = DashboardWidget::findOrFail($widgetId);
        $userId = $widget->user_id;
        $cooperativeId = $widget->cooperative_id;

        $deleted = $widget->delete();

        if ($deleted) {
            $this->clearUserDashboardCache($userId, $cooperativeId);

            Log::info('Dashboard widget deleted', [
                'widget_id' => $widgetId,
                'user_id' => $userId,
                'cooperative_id' => $cooperativeId,
            ]);
        }

        return $deleted;
    }

    public function getWidgetData(DashboardWidget $widget): array
    {
        switch ($widget->widget_type) {
            case 'kpi_summary':
                return $this->getKPISummaryData($widget);
            case 'financial_overview':
                return $this->getFinancialOverviewData($widget);
            case 'member_statistics':
                return $this->getMemberStatisticsData($widget);
            case 'transaction_chart':
                return $this->getTransactionChartData($widget);
            case 'loan_portfolio':
                return $this->getLoanPortfolioData($widget);
            case 'savings_growth':
                return $this->getSavingsGrowthData($widget);
            case 'recent_activities':
                return $this->getRecentActivitiesData($widget);
            default:
                return [];
        }
    }

    private function getKPISummaryData(DashboardWidget $widget): array
    {
        return $this->kpiService->getKPISummary(
            $widget->cooperative_id,
            $widget->configuration['period'] ?? 'monthly'
        );
    }

    private function getFinancialOverviewData(DashboardWidget $widget): array
    {
        return $this->analyticsService->getFinancialOverview(
            $widget->cooperative_id,
            $widget->configuration['period'] ?? 'monthly'
        );
    }

    private function getMemberStatisticsData(DashboardWidget $widget): array
    {
        return $this->analyticsService->getMemberStatistics(
            $widget->cooperative_id
        );
    }

    private function getTransactionChartData(DashboardWidget $widget): array
    {
        return $this->analyticsService->getTransactionTrends(
            $widget->cooperative_id,
            $widget->configuration['period'] ?? 'monthly',
            $widget->configuration['chart_type'] ?? 'line'
        );
    }

    private function getLoanPortfolioData(DashboardWidget $widget): array
    {
        return $this->analyticsService->getLoanPortfolioAnalysis(
            $widget->cooperative_id
        );
    }

    private function getSavingsGrowthData(DashboardWidget $widget): array
    {
        return $this->analyticsService->getSavingsGrowthAnalysis(
            $widget->cooperative_id,
            $widget->configuration['period'] ?? 'monthly'
        );
    }

    private function getRecentActivitiesData(DashboardWidget $widget): array
    {
        return $this->analyticsService->getRecentActivities(
            $widget->cooperative_id,
            $widget->configuration['limit'] ?? 10
        );
    }

    private function clearUserDashboardCache(int $userId, int $cooperativeId): void
    {
        $cacheKey = "dashboard:user:{$userId}:cooperative:{$cooperativeId}";
        Cache::forget($cacheKey);
    }

    public function getAvailableWidgetTypes(): array
    {
        return [
            'kpi_summary' => [
                'name' => 'KPI Summary',
                'description' => 'Key performance indicators overview',
                'default_size' => ['width' => 4, 'height' => 2],
                'configurable' => ['period', 'show_comparison', 'kpi_types'],
                'category' => 'analytics',
            ],
            'financial_overview' => [
                'name' => 'Financial Overview',
                'description' => 'Financial performance summary',
                'default_size' => ['width' => 6, 'height' => 3],
                'configurable' => ['period', 'show_comparison', 'include_forecast'],
                'category' => 'financial',
            ],
            'member_statistics' => [
                'name' => 'Member Statistics',
                'description' => 'Member growth and demographics',
                'default_size' => ['width' => 4, 'height' => 3],
                'configurable' => ['chart_type', 'breakdown_by'],
                'category' => 'member',
            ],
            'transaction_chart' => [
                'name' => 'Transaction Chart',
                'description' => 'Transaction trends and patterns',
                'default_size' => ['width' => 8, 'height' => 4],
                'configurable' => ['period', 'chart_type', 'transaction_types'],
                'category' => 'financial',
            ],
            'loan_portfolio' => [
                'name' => 'Loan Portfolio',
                'description' => 'Loan portfolio analysis',
                'default_size' => ['width' => 6, 'height' => 4],
                'configurable' => ['show_details', 'breakdown_by'],
                'category' => 'loan',
            ],
            'savings_growth' => [
                'name' => 'Savings Growth',
                'description' => 'Savings growth analysis',
                'default_size' => ['width' => 6, 'height' => 3],
                'configurable' => ['period', 'chart_type', 'show_target_line'],
                'category' => 'savings',
            ],
            'recent_activities' => [
                'name' => 'Recent Activities',
                'description' => 'Latest system activities',
                'default_size' => ['width' => 4, 'height' => 4],
                'configurable' => ['limit', 'activity_types'],
                'category' => 'system',
            ],
        ];
    }
}
