<?php
// app/Http/Controllers/Web/DashboardController.php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Analytics\Services\DashboardService;
use App\Domain\Analytics\Services\KPIService;
use App\Domain\Analytics\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly KPIService $kpiService,
        private readonly AnalyticsService $analyticsService
    ) {
        $this->middleware('auth');
        $this->middleware('tenant.scope');
    }

    /**
     * Display the main dashboard
     */
    public function index(): View
    {
        $user = Auth::user();

        // Get dashboard data
        $dashboard = $this->dashboardService->getUserDashboard($user->id, $user->cooperative_id);

        // Get KPI summary
        $kpiSummary = $this->kpiService->getKPISummary($user->cooperative_id);

        // Get recent activities
        $recentActivities = $this->analyticsService->getRecentActivities($user->cooperative_id, 10);

        // Get quick stats
        $quickStats = [
            'total_members' => \App\Domain\Member\Models\Member::where('cooperative_id', $user->cooperative_id)->count(),
            'active_loans' => \App\Domain\Member\Models\Loan::whereHas('member', function ($q) use ($user) {
                $q->where('cooperative_id', $user->cooperative_id);
            })->where('status', 'active')->count(),
            'total_savings' => \App\Domain\Member\Models\Savings::whereHas('member', function ($q) use ($user) {
                $q->where('cooperative_id', $user->cooperative_id);
            })->sum('balance'),
            'pending_approvals' => \App\Domain\Workflow\Models\WorkflowInstance::whereHas('workflow', function ($q) use ($user) {
                $q->where('cooperative_id', $user->cooperative_id);
            })->where('status', 'pending')->count(),
        ];

        return view('dashboard.index', compact(
            'dashboard',
            'kpiSummary',
            'recentActivities',
            'quickStats'
        ));
    }

    /**
     * Display financial dashboard
     */
    public function financial(): View
    {
        $user = Auth::user();

        $financialData = $this->analyticsService->getFinancialOverview($user->cooperative_id);
        $kpiTrends = $this->kpiService->getKPITrends($user->cooperative_id, 'total_assets', 12);

        return view('dashboard.financial', compact('financialData', 'kpiTrends'));
    }

    /**
     * Display member dashboard
     */
    public function members(): View
    {
        $user = Auth::user();

        $memberStats = $this->analyticsService->getMemberStatistics($user->cooperative_id);
        $memberTrends = $this->kpiService->getKPITrends($user->cooperative_id, 'total_members', 12);

        return view('dashboard.members', compact('memberStats', 'memberTrends'));
    }

    /**
     * Display loan dashboard
     */
    public function loans(): View
    {
        $user = Auth::user();

        $loanPortfolio = $this->analyticsService->getLoanPortfolioAnalysis($user->cooperative_id);
        $loanTrends = $this->kpiService->getKPITrends($user->cooperative_id, 'outstanding_loans', 12);

        return view('dashboard.loans', compact('loanPortfolio', 'loanTrends'));
    }

    /**
     * Display savings dashboard
     */
    public function savings(): View
    {
        $user = Auth::user();

        $savingsAnalysis = $this->analyticsService->getSavingsGrowthAnalysis($user->cooperative_id);
        $savingsTrends = $this->kpiService->getKPITrends($user->cooperative_id, 'total_savings', 12);

        return view('dashboard.savings', compact('savingsAnalysis', 'savingsTrends'));
    }

    /**
     * Widget management page
     */
    public function widgets(): View
    {
        $user = Auth::user();

        $widgets = \App\Domain\Analytics\Models\DashboardWidget::where('user_id', $user->id)
            ->where('cooperative_id', $user->cooperative_id)
            ->orderBy('position_y')
            ->orderBy('position_x')
            ->get();

        $availableWidgets = $this->dashboardService->getAvailableWidgetTypes();

        return view('dashboard.widgets', compact('widgets', 'availableWidgets'));
    }
}
