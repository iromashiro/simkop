<?php
// app/Domain/Analytics/Services/AnalyticsValidationService.php
namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\DTOs\AnalyticsResultDTO;
use App\Domain\Analytics\DTOs\ValidationResultDTO;
use Illuminate\Support\Facades\Log;

/**
 * Analytics Data Validation Service
 */
class AnalyticsValidationService
{
    private array $validationRules = [];

    public function __construct()
    {
        $this->initializeValidationRules();
    }

    /**
     * Validate analytics data for accuracy and consistency
     */
    public function validateAnalyticsData(AnalyticsResultDTO $analytics): ValidationResultDTO
    {
        $warnings = [];
        $errors = [];
        $suggestions = [];

        try {
            Log::info('Starting analytics data validation', [
                'cooperative_id' => $analytics->cooperativeId,
                'widgets_count' => count($analytics->widgets),
            ]);

            foreach ($analytics->widgets as $widgetType => $widget) {
                $widgetValidation = $this->validateWidget($widgetType, $widget);

                $warnings = array_merge($warnings, $widgetValidation['warnings']);
                $errors = array_merge($errors, $widgetValidation['errors']);
                $suggestions = array_merge($suggestions, $widgetValidation['suggestions']);
            }

            // Cross-widget validation
            $crossValidation = $this->validateCrossWidgetConsistency($analytics->widgets);
            $warnings = array_merge($warnings, $crossValidation['warnings']);
            $errors = array_merge($errors, $crossValidation['errors']);

            Log::info('Analytics validation completed', [
                'warnings_count' => count($warnings),
                'errors_count' => count($errors),
                'suggestions_count' => count($suggestions),
            ]);

            return new ValidationResultDTO(
                isValid: empty($errors),
                warnings: $warnings,
                errors: $errors,
                suggestions: $suggestions,
                validatedAt: now()
            );
        } catch (\Exception $e) {
            Log::error('Analytics validation failed', [
                'error' => $e->getMessage(),
                'cooperative_id' => $analytics->cooperativeId,
            ]);

            return new ValidationResultDTO(
                isValid: false,
                warnings: [],
                errors: ['Validation process failed: ' . $e->getMessage()],
                suggestions: [],
                validatedAt: now()
            );
        }
    }

    /**
     * Validate individual widget data
     */
    private function validateWidget(string $widgetType, object $widget): array
    {
        $warnings = [];
        $errors = [];
        $suggestions = [];

        switch ($widgetType) {
            case 'financial_overview':
                $result = $this->validateFinancialOverview($widget);
                break;
            case 'member_growth':
                $result = $this->validateMemberGrowth($widget);
                break;
            case 'savings_trends':
                $result = $this->validateSavingsTrends($widget);
                break;
            case 'loan_portfolio':
                $result = $this->validateLoanPortfolio($widget);
                break;
            default:
                $result = $this->validateGenericWidget($widget);
        }

        return $result;
    }

    /**
     * Validate financial overview widget
     */
    private function validateFinancialOverview(object $widget): array
    {
        $warnings = [];
        $errors = [];
        $suggestions = [];

        $data = $widget->data;

        // Validate balance sheet equation
        if (isset($data['current_period'])) {
            $current = $data['current_period'];
            $assets = $current['total_assets'] ?? 0;
            $liabilities = $current['total_liabilities'] ?? 0;
            $equity = $current['total_equity'] ?? 0;

            $balanceDifference = abs($assets - ($liabilities + $equity));

            if ($balanceDifference > 0.01) {
                $warnings[] = [
                    'type' => 'balance_sheet_imbalance',
                    'message' => "Balance sheet equation not balanced. Difference: " . number_format($balanceDifference, 2),
                    'severity' => 'medium',
                    'data' => [
                        'assets' => $assets,
                        'liabilities' => $liabilities,
                        'equity' => $equity,
                        'difference' => $balanceDifference,
                    ]
                ];
            }

            // Check for negative values where inappropriate
            if ($assets < 0) {
                $errors[] = [
                    'type' => 'negative_assets',
                    'message' => 'Total assets cannot be negative',
                    'value' => $assets,
                ];
            }

            // Check for unrealistic ratios
            if ($assets > 0) {
                $debtToEquityRatio = $equity != 0 ? $liabilities / $equity : 0;

                if ($debtToEquityRatio > 5) {
                    $warnings[] = [
                        'type' => 'high_debt_ratio',
                        'message' => 'Debt-to-equity ratio is very high: ' . number_format($debtToEquityRatio, 2),
                        'severity' => 'high',
                        'suggestion' => 'Consider reviewing debt levels or increasing equity',
                    ];
                }
            }

            // Validate growth rates
            if (isset($data['growth_rates'])) {
                foreach ($data['growth_rates'] as $metric => $rate) {
                    if (abs($rate) > 1000) { // More than 1000% growth
                        $warnings[] = [
                            'type' => 'extreme_growth_rate',
                            'message' => "Extreme growth rate detected for {$metric}: " . number_format($rate, 1) . '%',
                            'severity' => 'medium',
                            'suggestion' => 'Please verify the data accuracy',
                        ];
                    }
                }
            }
        }

        return [
            'warnings' => $warnings,
            'errors' => $errors,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Validate member growth widget
     */
    private function validateMemberGrowth(object $widget): array
    {
        $warnings = [];
        $errors = [];
        $suggestions = [];

        $data = $widget->data;

        // Validate growth data consistency
        if (isset($data['growth_data']['monthly_growth'])) {
            $monthlyGrowth = $data['growth_data']['monthly_growth'];

            foreach ($monthlyGrowth as $month) {
                if ($month->new_members < 0) {
                    $errors[] = [
                        'type' => 'negative_member_growth',
                        'message' => "Negative member growth detected for {$month->month}",
                        'value' => $month->new_members,
                    ];
                }

                if ($month->cumulative_members < $month->new_members) {
                    $errors[] = [
                        'type' => 'invalid_cumulative_count',
                        'message' => "Cumulative members less than new members for {$month->month}",
                        'data' => [
                            'cumulative' => $month->cumulative_members,
                            'new_members' => $month->new_members,
                        ],
                    ];
                }
            }
        }

        // Validate activity metrics
        if (isset($data['activity_metrics'])) {
            $metrics = $data['activity_metrics'];

            if ($metrics['activity_rate'] > 100) {
                $errors[] = [
                    'type' => 'invalid_activity_rate',
                    'message' => 'Activity rate cannot exceed 100%',
                    'value' => $metrics['activity_rate'],
                ];
            }

            if ($metrics['active_members'] > $metrics['total_active_members']) {
                $errors[] = [
                    'type' => 'invalid_member_count',
                    'message' => 'Active members cannot exceed total active members',
                    'data' => [
                        'active' => $metrics['active_members'],
                        'total' => $metrics['total_active_members'],
                    ],
                ];
            }
        }

        return [
            'warnings' => $warnings,
            'errors' => $errors,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Validate cross-widget consistency
     */
    private function validateCrossWidgetConsistency(array $widgets): array
    {
        $warnings = [];
        $errors = [];

        // Check consistency between financial overview and other widgets
        if (isset($widgets['financial_overview']) && isset($widgets['member_growth'])) {
            $financial = $widgets['financial_overview'];
            $memberGrowth = $widgets['member_growth'];

            // Example: Check if member growth aligns with asset growth
            $assetGrowth = $financial->data['growth_rates']['total_assets'] ?? 0;
            $memberGrowthRate = $memberGrowth->data['growth_data']['growth_rate'] ?? 0;

            // If assets are growing much faster than members, it might indicate efficiency gains
            if ($assetGrowth > $memberGrowthRate * 3 && $memberGrowthRate > 0) {
                $warnings[] = [
                    'type' => 'asset_member_growth_divergence',
                    'message' => 'Asset growth significantly exceeds member growth',
                    'data' => [
                        'asset_growth' => $assetGrowth,
                        'member_growth' => $memberGrowthRate,
                    ],
                    'suggestion' => 'This could indicate improved efficiency or potential market expansion opportunities',
                ];
            }
        }

        return [
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * Validate generic widget
     */
    private function validateGenericWidget(object $widget): array
    {
        $warnings = [];
        $errors = [];
        $suggestions = [];

        // Basic data structure validation
        if (!isset($widget->data) || empty($widget->data)) {
            $warnings[] = [
                'type' => 'empty_widget_data',
                'message' => 'Widget contains no data',
                'severity' => 'low',
            ];
        }

        return [
            'warnings' => $warnings,
            'errors' => $errors,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Initialize validation rules
     */
    private function initializeValidationRules(): void
    {
        $this->validationRules = [
            'financial_overview' => [
                'required_fields' => ['total_assets', 'total_liabilities', 'total_equity'],
                'balance_sheet_tolerance' => 0.01,
                'max_debt_to_equity_ratio' => 5.0,
            ],
            'member_growth' => [
                'required_fields' => ['growth_data', 'activity_metrics'],
                'max_activity_rate' => 100,
                'min_growth_rate' => -50, // -50% minimum growth rate
            ],
        ];
    }
}
