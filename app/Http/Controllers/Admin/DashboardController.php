<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cooperative;
use App\Models\User;
use App\Models\Financial\FinancialReport;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardAnalyticsService $analyticsService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_dinas');
    }

    public function index(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));

            // Cache dashboard data for 5 minutes
            $cacheKey = "admin_dashboard_{$year}_" . auth()->id();

            $data = Cache::remember($cacheKey, 300, function () use ($year) {
                return [
                    'overview' => $this->getOverviewStats(),
                    'reports' => $this->getReportsStats($year),
                    'cooperatives' => $this->getCooperativesStats(),
                    'recent_activities' => $this->getRecentActivities(),
                    'pending_approvals' => $this->getPendingApprovals(),
                ];
            });

            // Get chart data (not cached for real-time updates)
            $chartData = [
                'reports_by_month' => $this->analyticsService->getReportsSubmissionTrend($year),
                'reports_by_type' => $this->analyticsService->getReportsByType($year),
                'cooperatives_by_status' => $this->analyticsService->getCooperativesByStatus(),
            ];

            return view('admin.dashboard', array_merge($data, compact('chartData', 'year')));
        } catch (\Exception $e) {
            Log::error('Error loading admin dashboard', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return view('admin.dashboard', [
                'overview' => [],
                'reports' => [],
                'cooperatives' => [],
                'recent_activities' => [],
                'pending_approvals' => [],
                'chartData' => [],
                'year' => $year ?? date('Y'),
                'error' => 'Terjadi kesalahan saat memuat data dashboard.'
            ]);
        }
    }

    private function getOverviewStats(): array
    {
        return [
            'total_cooperatives' => Cooperative::count(),
            'active_cooperatives' => Cooperative::where('status', 'active')->count(),
            'total_users' => User::count(),
            'total_reports' => FinancialReport::count(),
            'pending_reports' => FinancialReport::where('status', 'submitted')->count(),
            'approved_reports' => FinancialReport::where('status', 'approved')->count(),
        ];
    }

    private function getReportsStats(int $year): array
    {
        $baseQuery = FinancialReport::whereYear('created_at', $year);

        return [
            'total' => $baseQuery->count(),
            'submitted' => $baseQuery->where('status', 'submitted')->count(),
            'approved' => $baseQuery->where('status', 'approved')->count(),
            'rejected' => $baseQuery->where('status', 'rejected')->count(),
            'draft' => $baseQuery->where('status', 'draft')->count(),
            'by_type' => $baseQuery->selectRaw('report_type, count(*) as total')
                ->groupBy('report_type')
                ->pluck('total', 'report_type')
                ->toArray(),
        ];
    }

    private function getCooperativesStats(): array
    {
        return [
            'total' => Cooperative::count(),
            'active' => Cooperative::where('status', 'active')->count(),
            'inactive' => Cooperative::where('status', 'inactive')->count(),
            'recent' => Cooperative::where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    private function getRecentActivities(): array
    {
        return FinancialReport::with(['cooperative:id,name', 'creator:id,name'])
            ->select('id', 'cooperative_id', 'report_type', 'status', 'created_by', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'type' => $report->getReportTypeLabel(),
                    'cooperative' => $report->cooperative->name,
                    'creator' => $report->creator->name,
                    'status' => $report->getStatusLabel(),
                    'status_class' => $report->getStatusClass(),
                    'created_at' => $report->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    private function getPendingApprovals(): array
    {
        return FinancialReport::with(['cooperative:id,name', 'creator:id,name'])
            ->where('status', 'submitted')
            ->select('id', 'cooperative_id', 'report_type', 'reporting_year', 'created_by', 'submitted_at')
            ->orderBy('submitted_at', 'asc')
            ->limit(15)
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'type' => $report->getReportTypeLabel(),
                    'year' => $report->reporting_year,
                    'cooperative' => $report->cooperative->name,
                    'creator' => $report->creator->name,
                    'submitted_at' => $report->submitted_at->diffForHumans(),
                    'days_pending' => $report->submitted_at->diffInDays(now()),
                ];
            })
            ->toArray();
    }
}
