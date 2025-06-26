<?php
// app/Http/Controllers/API/V1/DashboardController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\Analytics\Services\DashboardService;
use App\Domain\Analytics\Services\KPIService;
use App\Domain\Analytics\DTOs\CreateWidgetDTO;
use App\Domain\Analytics\DTOs\UpdateWidgetDTO;
use App\Http\Requests\API\V1\Dashboard\CreateWidgetRequest;
use App\Http\Requests\API\V1\Dashboard\UpdateWidgetRequest;
use App\Http\Resources\API\V1\DashboardWidgetResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @group Dashboard
 *
 * APIs for dashboard management and widgets
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly KPIService $kpiService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant.scope');
    }

    /**
     * Get user dashboard
     *
     * @authenticated
     * @queryParam refresh boolean Force refresh widget data. Example: true
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($request->boolean('refresh')) {
            // Clear cache to force refresh
            \Cache::forget("dashboard:user:{$user->id}:cooperative:{$user->cooperative_id}");
        }

        $dashboard = $this->dashboardService->getUserDashboard($user->id, $user->cooperative_id);

        return response()->json([
            'success' => true,
            'data' => $dashboard,
        ]);
    }

    /**
     * Get KPI summary
     *
     * @authenticated
     * @queryParam period string Time period for KPIs. Example: monthly
     */
    public function kpiSummary(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'string|in:daily,weekly,monthly,quarterly,yearly',
        ]);

        $user = Auth::user();
        $period = $request->get('period', 'monthly');

        $kpis = $this->kpiService->getKPISummary($user->cooperative_id, $period);

        return response()->json([
            'success' => true,
            'data' => $kpis,
            'meta' => [
                'period' => $period,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get KPI trends
     *
     * @authenticated
     * @queryParam kpi string required KPI name. Example: total_assets
     * @queryParam periods integer Number of periods to analyze. Example: 12
     */
    public function kpiTrends(Request $request): JsonResponse
    {
        $request->validate([
            'kpi' => 'required|string|max:50',
            'periods' => 'integer|min:3|max:24',
        ]);

        $user = Auth::user();
        $kpiName = $request->get('kpi');
        $periods = $request->get('periods', 12);

        $trends = $this->kpiService->getKPITrends($user->cooperative_id, $kpiName, $periods);

        return response()->json([
            'success' => true,
            'data' => $trends,
            'meta' => [
                'kpi' => $kpiName,
                'periods' => $periods,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Create dashboard widget
     *
     * @authenticated
     */
    public function createWidget(CreateWidgetRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $dto = new CreateWidgetDTO(
                cooperativeId: $user->cooperative_id,
                userId: $user->id,
                widgetType: $request->widget_type,
                title: $request->title,
                configuration: $request->configuration ?? [],
                positionX: $request->position_x,
                positionY: $request->position_y,
                width: $request->width,
                height: $request->height,
                isActive: $request->is_active ?? true,
                refreshInterval: $request->refresh_interval ?? 300
            );

            $widget = $this->dashboardService->createWidget($dto);

            return response()->json([
                'success' => true,
                'message' => 'Widget created successfully',
                'data' => new DashboardWidgetResource($widget),
            ], 201);
        } catch (\App\Domain\Analytics\Exceptions\InvalidWidgetConfigurationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid widget configuration',
                'errors' => $e->getValidationErrors(),
            ], 422);
        }
    }

    /**
     * Update dashboard widget
     *
     * @authenticated
     */
    public function updateWidget(int $id, UpdateWidgetRequest $request): JsonResponse
    {
        try {
            $dto = new UpdateWidgetDTO(
                title: $request->title,
                configuration: $request->configuration,
                positionX: $request->position_x,
                positionY: $request->position_y,
                width: $request->width,
                height: $request->height,
                isActive: $request->is_active,
                refreshInterval: $request->refresh_interval
            );

            $widget = $this->dashboardService->updateWidget($id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'Widget updated successfully',
                'data' => new DashboardWidgetResource($widget),
            ]);
        } catch (\App\Domain\Analytics\Exceptions\InvalidWidgetConfigurationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid widget configuration',
                'errors' => $e->getValidationErrors(),
            ], 422);
        }
    }

    /**
     * Delete dashboard widget
     *
     * @authenticated
     */
    public function deleteWidget(int $id): JsonResponse
    {
        $success = $this->dashboardService->deleteWidget($id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Widget not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Widget deleted successfully',
        ]);
    }

    /**
     * Get available widget types
     *
     * @authenticated
     */
    public function widgetTypes(): JsonResponse
    {
        $widgetTypes = $this->dashboardService->getAvailableWidgetTypes();

        return response()->json([
            'success' => true,
            'data' => $widgetTypes,
        ]);
    }

    /**
     * Get widget configuration schema
     *
     * @authenticated
     * @queryParam type string required Widget type. Example: kpi_summary
     */
    public function widgetSchema(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|max:50',
        ]);

        $schema = $this->dashboardService->getWidgetConfigurationSchema($request->type);

        if (empty($schema)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown widget type',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $schema,
        ]);
    }

    /**
     * Refresh widget data
     *
     * @authenticated
     */
    public function refreshWidget(int $id): JsonResponse
    {
        $user = Auth::user();

        $widget = \App\Domain\Analytics\Models\DashboardWidget::where('id', $id)
            ->where('user_id', $user->id)
            ->where('cooperative_id', $user->cooperative_id)
            ->firstOrFail();

        try {
            $data = $this->dashboardService->getWidgetData($widget);
            $widget->markAsRefreshed();

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'refreshed_at' => now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh widget data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dashboard performance metrics
     *
     * @authenticated
     */
    public function performance(): JsonResponse
    {
        $user = Auth::user();

        $metrics = [
            'widget_count' => \App\Domain\Analytics\Models\DashboardWidget::where('user_id', $user->id)
                ->where('cooperative_id', $user->cooperative_id)
                ->where('is_active', true)
                ->count(),
            'cache_status' => \Cache::has("dashboard:user:{$user->id}:cooperative:{$user->cooperative_id}"),
            'last_accessed' => now()->toISOString(),
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }
}
