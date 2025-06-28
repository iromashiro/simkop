<?php

namespace App\Services;

use App\Models\Financial\FinancialReport;
use App\Models\Cooperative;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DashboardAnalyticsService
{
    /**
     * Get dashboard analytics for admin dinas.
     */
    public function getAdminDinasAnalytics(): array
    {
        try {
            return Cache::remember('admin_dinas_analytics', 300, function () {
                return [
                    'overview' => $this->getAdminDinasOverview(),
                    'cooperative_statistics' => $this->getCooperativeStatistics(),
                    'report_statistics' => $this->getReportStatistics(),
                    'recent_activities' => $this->getRecentActivities(),
                    'financial_trends' => $this->getFinancialTrends(),
                    'compliance_status' => $this->getComplianceStatus(),
                    'alerts' => $this->getSystemAlerts()
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error getting admin dinas analytics', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get dashboard analytics for admin koperasi.
     */
    public function getAdminKoperasiAnalytics(int $cooperativeId): array
    {
        try {
            return Cache::remember("admin_koperasi_analytics_{$cooperativeId}", 300, function () use ($cooperativeId) {
                return [
                    'overview' => $this->getCooperativeOverview($cooperativeId),
                    'financial_summary' => $this->getFinancialSummary($cooperativeId),
                    'report_status' => $this->getReportStatus($cooperativeId),
                    'member_analytics' => $this->getMemberAnalytics($cooperativeId),
                    'performance_metrics' => $this->getPerformanceMetrics($cooperativeId),
                    'upcoming_deadlines' => $this->getUpcomingDeadlines($cooperativeId),
                    'notifications' => $this->getRecentNotifications($cooperativeId)
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error getting admin koperasi analytics', [
                'cooperative_id' => $cooperativeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get admin dinas overview.
     */
    private function getAdminDinasOverview(): array
    {
        $currentYear = now()->year;

        return [
            'total_cooperatives' => Cooperative::where('is_active', true)->count(),
            'active_cooperatives' => Cooperative::where('operational_status', 'active')->count(),
            'total_reports_this_year' => FinancialReport::where('reporting_year', $currentYear)->count(),
            'pending_approvals' => FinancialReport::where('status', 'submitted')->count(),
            'approved_reports_this_month' => FinancialReport::where('status', 'approved')
                ->whereMonth('approved_at', now()->month)
                ->whereYear('approved_at', $currentYear)
                ->count(),
            'total_users' => User::where('is_active', true)->count()
        ];
    }

    /**
     * Get cooperative statistics.
     */
    private function getCooperativeStatistics(): array
    {
        $cooperatives = Cooperative::select('type', 'operational_status', 'total_members', 'current_assets')
            ->where('is_active', true)
            ->get();

        $stats = [
            'by_type' => $cooperatives->groupBy('type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_members' => $group->sum('total_members'),
                    'total_assets' => $group->sum('current_assets')
                ];
            }),
            'by_status' => $cooperatives->groupBy('operational_status')->map(function ($group) {
                return $group->count();
            }),
            'member_distribution' => [
                'small' => $cooperatives->where('total_members', '<=', 100)->count(),
                'medium' => $cooperatives->whereBetween('total_members', [101, 500])->count(),
                'large' => $cooperatives->where('total_members', '>', 500)->count()
            ],
            'asset_distribution' => [
                'under_1b' => $cooperatives->where('current_assets', '<', 1000000000)->count(),
                '1b_to_5b' => $cooperatives->whereBetween('current_assets', [1000000000, 5000000000])->count(),
                'over_5b' => $cooperatives->where('current_assets', '>', 5000000000)->count()
            ]
        ];

        return $stats;
    }

    /**
     * Get report statistics.
     */
    private function getReportStatistics(): array
    {
        $currentYear = now()->year;

        $reportStats = FinancialReport::select('report_type', 'status', 'reporting_year')
            ->where('reporting_year', '>=', $currentYear - 2)
            ->get();

        return [
            'by_type' => $reportStats->groupBy('report_type')->map(function ($group) {
                return [
                    'total' => $group->count(),
                    'approved' => $group->where('status', 'approved')->count(),
                    'pending' => $group->where('status', 'submitted')->count(),
                    'draft' => $group->where('status', 'draft')->count()
                ];
            }),
            'by_year' => $reportStats->groupBy('reporting_year')->map(function ($group) {
                return [
                    'total' => $group->count(),
                    'completion_rate' => $group->where('status', 'approved')->count() / max($group->count(), 1) * 100
                ];
            }),
            'submission_trends' => $this->getSubmissionTrends(),
            'approval_times' => $this->getAverageApprovalTimes()
        ];
    }

    /**
     * Get recent activities.
     */
    private function getRecentActivities(): array
    {
        $activities = [];

        // Recent report submissions
        $recentSubmissions = FinancialReport::with('cooperative')
            ->where('status', 'submitted')
            ->orderBy('submitted_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($recentSubmissions as $report) {
            $activities[] = [
                'type' => 'report_submitted',
                'message' => "{$report->cooperative->name} submitted {$report->report_type} report",
                'timestamp' => $report->submitted_at,
                'cooperative_id' => $report->cooperative_id,
                'report_id' => $report->id
            ];
        }

        // Recent approvals
        $recentApprovals = FinancialReport::with('cooperative')
            ->where('status', 'approved')
            ->orderBy('approved_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($recentApprovals as $report) {
            $activities[] = [
                'type' => 'report_approved',
                'message' => "{$report->cooperative->name} {$report->report_type} report approved",
                'timestamp' => $report->approved_at,
                'cooperative_id' => $report->cooperative_id,
                'report_id' => $report->id
            ];
        }

        // Sort by timestamp
        usort($activities, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return array_slice($activities, 0, 20);
    }

    /**
     * Get financial trends.
     */
    private function getFinancialTrends(): array
    {
        $currentYear = now()->year;
        $years = [$currentYear - 2, $currentYear - 1, $currentYear];

        $trends = [];

        foreach ($years as $year) {
            $balanceSheets = FinancialReport::where('report_type', 'balance_sheet')
                ->where('reporting_year', $year)
                ->where('status', 'approved')
                ->with('balanceSheetAccounts')
                ->get();

            $incomeStatements = FinancialReport::where('report_type', 'income_statement')
                ->where('reporting_year', $year)
                ->where('status', 'approved')
                ->with('incomeStatementAccounts')
                ->get();

            $totalAssets = 0;
            $totalEquity = 0;
            $totalRevenue = 0;
            $totalExpenses = 0;

            foreach ($balanceSheets as $report) {
                $totalAssets += $report->balanceSheetAccounts->where('account_category', 'asset')->sum('current_year_amount');
                $totalEquity += $report->balanceSheetAccounts->where('account_category', 'equity')->sum('current_year_amount');
            }

            foreach ($incomeStatements as $report) {
                $totalRevenue += $report->incomeStatementAccounts->where('account_category', 'revenue')->sum('current_year_amount');
                $totalExpenses += $report->incomeStatementAccounts->where('account_category', 'expense')->sum('current_year_amount');
            }

            $trends[$year] = [
                'total_assets' => $totalAssets,
                'total_equity' => $totalEquity,
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'net_income' => $totalRevenue - $totalExpenses,
                'cooperative_count' => $balanceSheets->count()
            ];
        }

        return $trends;
    }

    /**
     * Get compliance status.
     */
    private function getComplianceStatus(): array
    {
        $currentYear = now()->year;
        $requiredReports = ['balance_sheet', 'income_statement', 'cash_flow'];

        $cooperatives = Cooperative::where('is_active', true)->get();
        $compliance = [
            'fully_compliant' => 0,
            'partially_compliant' => 0,
            'non_compliant' => 0,
            'details' => []
        ];

        foreach ($cooperatives as $cooperative) {
            $submittedReports = FinancialReport::where('cooperative_id', $cooperative->id)
                ->where('reporting_year', $currentYear)
                ->whereIn('report_type', $requiredReports)
                ->where('status', 'approved')
                ->pluck('report_type')
                ->toArray();

            $complianceRate = count($submittedReports) / count($requiredReports) * 100;

            if ($complianceRate == 100) {
                $compliance['fully_compliant']++;
                $status = 'compliant';
            } elseif ($complianceRate > 0) {
                $compliance['partially_compliant']++;
                $status = 'partial';
            } else {
                $compliance['non_compliant']++;
                $status = 'non_compliant';
            }

            $compliance['details'][] = [
                'cooperative_id' => $cooperative->id,
                'cooperative_name' => $cooperative->name,
                'compliance_rate' => $complianceRate,
                'status' => $status,
                'submitted_reports' => $submittedReports,
                'missing_reports' => array_diff($requiredReports, $submittedReports)
            ];
        }

        return $compliance;
    }

    /**
     * Get system alerts.
     */
    private function getSystemAlerts(): array
    {
        $alerts = [];

        // Overdue reports
        $overdueReports = FinancialReport::where('status', 'draft')
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        if ($overdueReports > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$overdueReports} reports are overdue (draft for more than 30 days)",
                'action_url' => '/admin/reports?status=overdue'
            ];
        }

        // Pending approvals
        $pendingApprovals = FinancialReport::where('status', 'submitted')
            ->where('submitted_at', '<', now()->subDays(7))
            ->count();

        if ($pendingApprovals > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$pendingApprovals} reports are pending approval for more than 7 days",
                'action_url' => '/admin/reports?status=pending'
            ];
        }

        // Inactive cooperatives
        $inactiveCooperatives = Cooperative::where('operational_status', 'inactive')
            ->where('is_active', true)
            ->count();

        if ($inactiveCooperatives > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$inactiveCooperatives} cooperatives are marked as inactive",
                'action_url' => '/admin/cooperatives?status=inactive'
            ];
        }

        return $alerts;
    }

    /**
     * Get cooperative overview.
     */
    private function getCooperativeOverview(int $cooperativeId): array
    {
        $cooperative = Cooperative::findOrFail($cooperativeId);
        $currentYear = now()->year;

        $reportCounts = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('reporting_year', $currentYear)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'cooperative_name' => $cooperative->name,
            'total_members' => $cooperative->total_members,
            'active_members' => $cooperative->active_members,
            'current_assets' => $cooperative->current_assets,
            'reports_this_year' => array_sum($reportCounts),
            'approved_reports' => $reportCounts['approved'] ?? 0,
            'pending_reports' => $reportCounts['submitted'] ?? 0,
            'draft_reports' => $reportCounts['draft'] ?? 0,
            'last_report_date' => FinancialReport::where('cooperative_id', $cooperativeId)
                ->where('status', 'approved')
                ->max('approved_at')
        ];
    }

    /**
     * Get financial summary for cooperative.
     */
    private function getFinancialSummary(int $cooperativeId): array
    {
        $currentYear = now()->year;

        $balanceSheet = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('report_type', 'balance_sheet')
            ->where('reporting_year', $currentYear)
            ->where('status', 'approved')
            ->with('balanceSheetAccounts')
            ->first();

        $incomeStatement = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('report_type', 'income_statement')
            ->where('reporting_year', $currentYear)
            ->where('status', 'approved')
            ->with('incomeStatementAccounts')
            ->first();

        $summary = [
            'total_assets' => 0,
            'total_liabilities' => 0,
            'total_equity' => 0,
            'total_revenue' => 0,
            'total_expenses' => 0,
            'net_income' => 0,
            'financial_ratios' => []
        ];

        if ($balanceSheet) {
            $summary['total_assets'] = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'asset')->sum('current_year_amount');
            $summary['total_liabilities'] = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'liability')->sum('current_year_amount');
            $summary['total_equity'] = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'equity')->sum('current_year_amount');
        }

        if ($incomeStatement) {
            $summary['total_revenue'] = $incomeStatement->incomeStatementAccounts
                ->where('account_category', 'revenue')->sum('current_year_amount');
            $summary['total_expenses'] = $incomeStatement->incomeStatementAccounts
                ->where('account_category', 'expense')->sum('current_year_amount');
            $summary['net_income'] = $summary['total_revenue'] - $summary['total_expenses'];
        }

        // Calculate financial ratios
        if ($summary['total_assets'] > 0) {
            $summary['financial_ratios']['debt_to_equity'] = $summary['total_equity'] > 0 ?
                $summary['total_liabilities'] / $summary['total_equity'] : 0;
            $summary['financial_ratios']['return_on_assets'] =
                ($summary['net_income'] / $summary['total_assets']) * 100;
        }

        if ($summary['total_equity'] > 0) {
            $summary['financial_ratios']['return_on_equity'] =
                ($summary['net_income'] / $summary['total_equity']) * 100;
        }

        if ($summary['total_revenue'] > 0) {
            $summary['financial_ratios']['profit_margin'] =
                ($summary['net_income'] / $summary['total_revenue']) * 100;
        }

        return $summary;
    }

    /**
     * Get report status for cooperative.
     */
    private function getReportStatus(int $cooperativeId): array
    {
        $currentYear = now()->year;
        $reportTypes = ['balance_sheet', 'income_statement', 'cash_flow', 'equity_changes'];

        $status = [];

        foreach ($reportTypes as $reportType) {
            $report = FinancialReport::where('cooperative_id', $cooperativeId)
                ->where('report_type', $reportType)
                ->where('reporting_year', $currentYear)
                ->orderBy('created_at', 'desc')
                ->first();

            $status[$reportType] = [
                'exists' => $report !== null,
                'status' => $report?->status ?? 'not_started',
                'last_updated' => $report?->updated_at,
                'submitted_at' => $report?->submitted_at,
                'approved_at' => $report?->approved_at
            ];
        }

        return $status;
    }

    /**
     * Get member analytics for cooperative.
     */
    private function getMemberAnalytics(int $cooperativeId): array
    {
        // Get member savings data
        $memberSavingsReport = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('report_type', 'member_savings')
            ->where('reporting_year', now()->year)
            ->where('status', 'approved')
            ->with('memberSavings')
            ->first();

        $analytics = [
            'total_savings' => 0,
            'average_savings_per_member' => 0,
            'savings_by_type' => [],
            'top_savers' => []
        ];

        if ($memberSavingsReport) {
            $memberSavings = $memberSavingsReport->memberSavings;

            $analytics['total_savings'] = $memberSavings->sum('ending_balance');
            $analytics['average_savings_per_member'] = $memberSavings->avg('ending_balance');

            $analytics['savings_by_type'] = $memberSavings->groupBy('savings_type')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_amount' => $group->sum('ending_balance'),
                        'average_amount' => $group->avg('ending_balance')
                    ];
                });

            $analytics['top_savers'] = $memberSavings->sortByDesc('ending_balance')
                ->take(10)
                ->map(function ($saving) {
                    return [
                        'member_name' => $saving->member_name,
                        'savings_type' => $saving->savings_type,
                        'amount' => $saving->ending_balance
                    ];
                })
                ->values();
        }

        return $analytics;
    }

    /**
     * Get performance metrics for cooperative.
     */
    private function getPerformanceMetrics(int $cooperativeId): array
    {
        $currentYear = now()->year;
        $previousYear = $currentYear - 1;

        $currentYearData = $this->getYearFinancialData($cooperativeId, $currentYear);
        $previousYearData = $this->getYearFinancialData($cooperativeId, $previousYear);

        $metrics = [];

        // Calculate growth rates
        if ($previousYearData['total_assets'] > 0) {
            $metrics['asset_growth'] = (($currentYearData['total_assets'] - $previousYearData['total_assets']) /
                $previousYearData['total_assets']) * 100;
        }

        if ($previousYearData['total_revenue'] > 0) {
            $metrics['revenue_growth'] = (($currentYearData['total_revenue'] - $previousYearData['total_revenue']) /
                $previousYearData['total_revenue']) * 100;
        }

        if ($previousYearData['net_income'] != 0) {
            $metrics['profit_growth'] = (($currentYearData['net_income'] - $previousYearData['net_income']) /
                abs($previousYearData['net_income'])) * 100;
        }

        // Performance indicators
        $metrics['performance_indicators'] = [
            'profitability' => $currentYearData['net_income'] > 0 ? 'profitable' : 'loss',
            'growth_trend' => ($metrics['revenue_growth'] ?? 0) > 0 ? 'growing' : 'declining',
            'financial_health' => $this->assessFinancialHealth($currentYearData)
        ];

        return $metrics;
    }

    /**
     * Get upcoming deadlines for cooperative.
     */
    private function getUpcomingDeadlines(int $cooperativeId): array
    {
        $deadlines = [];
        $currentYear = now()->year;
        $currentQuarter = ceil(now()->month / 3);

        // Check for missing quarterly reports
        $requiredReports = ['balance_sheet', 'income_statement'];

        foreach ($requiredReports as $reportType) {
            for ($quarter = 1; $quarter <= $currentQuarter; $quarter++) {
                $exists = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', $reportType)
                    ->where('reporting_year', $currentYear)
                    ->where('reporting_period', "Q{$quarter}")
                    ->exists();

                if (!$exists) {
                    $deadlines[] = [
                        'type' => 'report_deadline',
                        'report_type' => $reportType,
                        'period' => "Q{$quarter} {$currentYear}",
                        'deadline' => $this->getReportDeadline($quarter, $currentYear),
                        'status' => 'overdue'
                    ];
                }
            }
        }

        return $deadlines;
    }

    /**
     * Get recent notifications for cooperative.
     */
    private function getRecentNotifications(int $cooperativeId): array
    {
        return Notification::where('cooperative_id', $cooperativeId)
            ->orWhere('recipient_type', 'broadcast')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'created_at' => $notification->created_at,
                    'is_read' => $notification->is_read
                ];
            })
            ->toArray();
    }

    /**
     * Get submission trends.
     */
    private function getSubmissionTrends(): array
    {
        $trends = [];
        $months = collect(range(1, 12))->map(function ($month) {
            return now()->month($month)->startOfMonth();
        });

        foreach ($months as $month) {
            $count = FinancialReport::whereYear('submitted_at', $month->year)
                ->whereMonth('submitted_at', $month->month)
                ->count();

            $trends[] = [
                'month' => $month->format('M Y'),
                'submissions' => $count
            ];
        }

        return $trends;
    }

    /**
     * Get average approval times.
     */
    private function getAverageApprovalTimes(): array
    {
        $approvedReports = FinancialReport::whereNotNull('submitted_at')
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', now()->subMonths(6))
            ->get();

        $times = [];

        foreach ($approvedReports as $report) {
            $approvalTime = $report->submitted_at->diffInDays($report->approved_at);
            $times[] = $approvalTime;
        }

        return [
            'average_days' => count($times) > 0 ? array_sum($times) / count($times) : 0,
            'median_days' => count($times) > 0 ? $this->calculateMedian($times) : 0,
            'max_days' => count($times) > 0 ? max($times) : 0,
            'min_days' => count($times) > 0 ? min($times) : 0
        ];
    }

    /**
     * Get financial data for a specific year.
     */
    private function getYearFinancialData(int $cooperativeId, int $year): array
    {
        $balanceSheet = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('report_type', 'balance_sheet')
            ->where('reporting_year', $year)
            ->where('status', 'approved')
            ->with('balanceSheetAccounts')
            ->first();

        $incomeStatement = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('report_type', 'income_statement')
            ->where('reporting_year', $year)
            ->where('status', 'approved')
            ->with('incomeStatementAccounts')
            ->first();

        $data = [
            'total_assets' => 0,
            'total_liabilities' => 0,
            'total_equity' => 0,
            'total_revenue' => 0,
            'total_expenses' => 0,
            'net_income' => 0
        ];

        if ($balanceSheet) {
            $data['total_assets'] = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'asset')->sum('current_year_amount');
            $data['total_liabilities'] = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'liability')->sum('current_year_amount');
            $data['total_equity'] = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'equity')->sum('current_year_amount');
        }

        if ($incomeStatement) {
            $data['total_revenue'] = $incomeStatement->incomeStatementAccounts
                ->where('account_category', 'revenue')->sum('current_year_amount');
            $data['total_expenses'] = $incomeStatement->incomeStatementAccounts
                ->where('account_category', 'expense')->sum('current_year_amount');
            $data['net_income'] = $data['total_revenue'] - $data['total_expenses'];
        }

        return $data;
    }

    /**
     * Assess financial health.
     */
    private function assessFinancialHealth(array $financialData): string
    {
        $score = 0;

        // Profitability check
        if ($financialData['net_income'] > 0) {
            $score += 2;
        } elseif ($financialData['net_income'] >= 0) {
            $score += 1;
        }

        // Debt-to-equity ratio check
        if ($financialData['total_equity'] > 0) {
            $debtToEquity = $financialData['total_liabilities'] / $financialData['total_equity'];
            if ($debtToEquity < 0.5) {
                $score += 2;
            } elseif ($debtToEquity < 1.0) {
                $score += 1;
            }
        }

        // Asset growth (simplified check)
        if ($financialData['total_assets'] > 0) {
            $score += 1;
        }

        // Determine health level
        if ($score >= 4) {
            return 'excellent';
        } elseif ($score >= 3) {
            return 'good';
        } elseif ($score >= 2) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /**
     * Get report deadline.
     */
    private function getReportDeadline(int $quarter, int $year): \DateTime
    {
        $deadlineMonth = $quarter * 3 + 1; // Q1: April, Q2: July, Q3: October, Q4: January next year
        $deadlineYear = $quarter == 4 ? $year + 1 : $year;

        return new \DateTime("{$deadlineYear}-{$deadlineMonth}-15");
    }

    /**
     * Calculate median.
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);

        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        } else {
            return $values[floor($count / 2)];
        }
    }
}
