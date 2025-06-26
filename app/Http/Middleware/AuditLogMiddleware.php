<?php
// app/Http/Middleware/AuditLogMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use App\Domain\System\Models\ActivityLog;
use App\Jobs\ProcessAuditLogJob;

class AuditLogMiddleware
{
    /**
     * Routes to skip logging
     */
    private const SKIP_ROUTES = [
        'api/health',
        'api/ping',
        'api/dashboard/widgets',
        'api/notifications/unread-count',
    ];

    /**
     * Sensitive fields to exclude from logging
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
        '_token',
        'credit_card',
        'ssn',
        'id_number',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $executionTime = microtime(true) - $startTime;

        // Log the activity asynchronously for better performance
        $this->queueAuditLog($request, $response, $executionTime);

        return $response;
    }

    /**
     * Queue audit log processing
     */
    private function queueAuditLog(Request $request, Response $response, float $executionTime): void
    {
        $user = Auth::user();

        // Only log authenticated requests
        if (!$user) {
            return;
        }

        // Skip logging for certain routes
        if ($this->shouldSkipLogging($request)) {
            return;
        }

        // Prepare audit data
        $auditData = [
            'cooperative_id' => $user->cooperative_id,
            'user_id' => $user->id,
            'action' => $this->getActionName($request),
            'model_type' => $this->getModelType($request),
            'model_id' => $this->getModelId($request),
            'ip_address' => $request->ip(),
            'user_agent' => $this->sanitizeUserAgent($request->userAgent()),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'execution_time' => round($executionTime * 1000, 2), // Convert to milliseconds
            'request_data' => $this->getRequestData($request),
            'response_data' => $this->getResponseData($response),
            'created_at' => now(),
        ];

        // Queue the audit log job for async processing
        Queue::push(new ProcessAuditLogJob($auditData));
    }

    /**
     * Determine if logging should be skipped
     */
    private function shouldSkipLogging(Request $request): bool
    {
        $path = $request->path();

        foreach (self::SKIP_ROUTES as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }

        // Skip OPTIONS requests
        if ($request->method() === 'OPTIONS') {
            return true;
        }

        // Skip if response is too large
        $contentLength = $request->header('Content-Length', 0);
        if ($contentLength > 1048576) { // 1MB
            return true;
        }

        return false;
    }

    /**
     * Get action name from request
     */
    private function getActionName(Request $request): string
    {
        $method = $request->method();
        $route = $request->route()?->getName() ?? $request->path();

        $action = match ($method) {
            'GET' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => strtolower($method),
        };

        return "{$action}:{$route}";
    }

    /**
     * Get model type from request
     */
    private function getModelType(Request $request): ?string
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        // Extract model from route parameters
        $parameters = $route->parameters();
        foreach ($parameters as $key => $value) {
            if (is_object($value) && method_exists($value, 'getMorphClass')) {
                return $value->getMorphClass();
            }
        }

        // Try to extract from route name
        $routeName = $route->getName();
        if ($routeName) {
            $parts = explode('.', $routeName);
            if (count($parts) >= 2) {
                return $parts[0]; // e.g., 'members.show' -> 'members'
            }
        }

        return null;
    }

    /**
     * Get model ID from request
     */
    private function getModelId(Request $request): ?int
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        // Extract model ID from route parameters
        $parameters = $route->parameters();
        foreach ($parameters as $key => $value) {
            if (is_object($value) && isset($value->id)) {
                return $value->id;
            }
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * Get sanitized request data
     */
    private function getRequestData(Request $request): array
    {
        $data = $request->except(self::SENSITIVE_FIELDS);

        // Remove file uploads from logging
        $data = array_filter($data, function ($value) {
            return !($value instanceof \Illuminate\Http\UploadedFile);
        });

        // Limit data size
        $jsonData = json_encode($data);
        if (strlen($jsonData) > 10000) {
            return [
                'message' => 'Request data too large to log',
                'size' => strlen($jsonData),
                'fields_count' => count($data),
            ];
        }

        return $data;
    }

    /**
     * Get sanitized response data
     */
    private function getResponseData(Response $response): ?array
    {
        // Only log JSON responses
        $contentType = $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'application/json')) {
            return null;
        }

        $content = $response->getContent();
        if (strlen($content) > 10000) {
            return [
                'message' => 'Response data too large to log',
                'size' => strlen($content),
                'status_code' => $response->getStatusCode(),
            ];
        }

        $data = json_decode($content, true);

        // Remove sensitive data from response
        if (is_array($data)) {
            $data = $this->removeSensitiveData($data);
        }

        return $data;
    }

    /**
     * Remove sensitive data from array
     */
    private function removeSensitiveData(array $data): array
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        // Recursively clean nested arrays
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->removeSensitiveData($value);
            }
        }

        return $data;
    }

    /**
     * Sanitize user agent string
     */
    private function sanitizeUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        // Limit length and remove potentially harmful characters
        $userAgent = substr($userAgent, 0, 500);
        return preg_replace('/[<>"\']/', '', $userAgent);
    }
}
