<?php
// app/Http/Controllers/API/V1/SystemController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\System\Services\SystemService;
use App\Domain\Auth\Services\SessionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @group System
 *
 * APIs for system management and monitoring
 */
class SystemController extends Controller
{
    public function __construct(
        private readonly SystemService $systemService,
        private readonly SessionService $sessionService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant.scope');
        $this->middleware('permission:manage_system')->except(['health', 'info']);
    }

    /**
     * Get system health status
     *
     * @authenticated
     */
    public function health(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => [],
        ];

        // Database check
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = ['status' => 'healthy', 'response_time' => 0];
        } catch (\Exception $e) {
            $health['checks']['database'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
            $health['status'] = 'unhealthy';
        }

        // Cache check
        try {
            Cache::put('health_check', 'ok', 10);
            $cached = Cache::get('health_check');
            $health['checks']['cache'] = ['status' => $cached === 'ok' ? 'healthy' : 'unhealthy'];
        } catch (\Exception $e) {
            $health['checks']['cache'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
            $health['status'] = 'unhealthy';
        }

        // Queue check
        try {
            $queueSize = DB::table('jobs')->count();
            $health['checks']['queue'] = [
                'status' => 'healthy',
                'pending_jobs' => $queueSize,
            ];
        } catch (\Exception $e) {
            $health['checks']['queue'] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }

        return response()->json($health);
    }

    /**
     * Get system information
     *
     * @authenticated
     */
    public function info(): JsonResponse
    {
        $info = [
            'app' => [
                'name' => config('app.name'),
                'version' => '1.0.0',
                'environment' => config('app.env'),
                'debug' => config('app.debug'),
                'timezone' => config('app.timezone'),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'database' => [
                'driver' => config('database.default'),
                'version' => DB::select('SELECT version()')[0]->version ?? 'Unknown',
            ],
            'cache' => [
                'driver' => config('cache.default'),
            ],
            'queue' => [
                'driver' => config('queue.default'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $info,
        ]);
    }

    /**
     * Get system statistics
     *
     * @authenticated
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        $stats = $this->systemService->getSystemStatistics($user->cooperative_id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get session statistics
     *
     * @authenticated
     */
    public function sessionStatistics(): JsonResponse
    {
        $user = Auth::user();

        $stats = $this->sessionService->getSessionStatistics($user->cooperative_id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get suspicious activity
     *
     * @authenticated
     * @queryParam hours integer Hours to look back. Example: 24
     */
    public function suspiciousActivity(Request $request): JsonResponse
    {
        $request->validate([
            'hours' => 'integer|min:1|max:168', // Max 1 week
        ]);

        $user = Auth::user();
        $hours = $request->get('hours', 24);

        $activity = $this->sessionService->getSuspiciousActivity($user->cooperative_id, $hours);

        return response()->json([
            'success' => true,
            'data' => $activity,
        ]);
    }

    /**
     * Clear system cache
     *
     * @authenticated
     */
    public function clearCache(Request $request): JsonResponse
    {
        $request->validate([
            'cache_types' => 'array',
            'cache_types.*' => 'string|in:config,route,view,permission,kpi,dashboard',
        ]);

        $cacheTypes = $request->get('cache_types', ['config', 'route', 'view']);
        $cleared = [];

        foreach ($cacheTypes as $type) {
            switch ($type) {
                case 'config':
                    \Artisan::call('config:clear');
                    $cleared[] = 'config';
                    break;
                case 'route':
                    \Artisan::call('route:clear');
                    $cleared[] = 'route';
                    break;
                case 'view':
                    \Artisan::call('view:clear');
                    $cleared[] = 'view';
                    break;
                case 'permission':
                    // Clear permission cache for cooperative
                    $user = Auth::user();
                    $this->systemService->clearPermissionCache($user->cooperative_id);
                    $cleared[] = 'permission';
                    break;
                case 'kpi':
                    // Clear KPI cache
                    $user = Auth::user();
                    $this->systemService->clearKPICache($user->cooperative_id);
                    $cleared[] = 'kpi';
                    break;
                case 'dashboard':
                    // Clear dashboard cache
                    $user = Auth::user();
                    $this->systemService->clearDashboardCache($user->cooperative_id);
                    $cleared[] = 'dashboard';
                    break;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully',
            'data' => ['cleared_types' => $cleared],
        ]);
    }

    /**
     * Get system logs
     *
     * @authenticated
     * @queryParam level string Log level filter. Example: error
     * @queryParam limit integer Number of log entries. Example: 100
     */
    public function logs(Request $request): JsonResponse
    {
        $request->validate([
            'level' => 'string|in:emergency,alert,critical,error,warning,notice,info,debug',
            'limit' => 'integer|min:1|max:1000',
        ]);

        $level = $request->get('level');
        $limit = $request->get('limit', 100);

        $logs = $this->systemService->getSystemLogs($level, $limit);

        return response()->json([
            'success' => true,
            'data' => $logs,
            'meta' => [
                'level_filter' => $level,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Force cleanup expired sessions
     *
     * @authenticated
     */
    public function cleanupSessions(Request $request): JsonResponse
    {
        $request->validate([
            'timeout_minutes' => 'integer|min:1|max:10080', // Max 1 week
        ]);

        $timeoutMinutes = $request->get('timeout_minutes', 120);

        $cleanedCount = $this->sessionService->cleanupExpiredSessions($timeoutMinutes);

        return response()->json([
            'success' => true,
            'message' => 'Session cleanup completed',
            'data' => [
                'cleaned_sessions' => $cleanedCount,
                'timeout_minutes' => $timeoutMinutes,
            ],
        ]);
    }

    /**
     * Get database statistics
     *
     * @authenticated
     */
    public function databaseStatistics(): JsonResponse
    {
        $stats = $this->systemService->getDatabaseStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Optimize database
     *
     * @authenticated
     */
    public function optimizeDatabase(): JsonResponse
    {
        try {
            $result = $this->systemService->optimizeDatabase();

            return response()->json([
                'success' => true,
                'message' => 'Database optimization completed',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database optimization failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system configuration
     *
     * @authenticated
     */
    public function configuration(): JsonResponse
    {
        $config = $this->systemService->getSystemConfiguration();

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Update system configuration
     *
     * @authenticated
     */
    public function updateConfiguration(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        try {
            $user = Auth::user();

            $updated = $this->systemService->updateSystemConfiguration(
                $user->cooperative_id,
                $request->settings,
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Configuration updated successfully',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
