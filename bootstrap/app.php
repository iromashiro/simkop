<?php
// bootstrap/app.php - Laravel 11+ Configuration

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // SECURITY: Global middleware for web routes
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
            \App\Http\Middleware\ResolveTenantMiddleware::class,
        ]);

        // SECURITY: API middleware
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // SECURITY: Rate limiting aliases
        $middleware->alias([
            'throttle.login' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':5,1',
            'throttle.api' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':60,1',
            'throttle.financial' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':30,1',
            'tenant.access' => \App\Http\Middleware\CheckCooperativeAccess::class,
            'tenant.auth' => \App\Http\Middleware\TenantAwareAuthMiddleware::class,
            'audit.log' => \App\Http\Middleware\AuditLogMiddleware::class,
        ]);

        // SECURITY: Group middleware
        $middleware->group('tenant', [
            'throttle:60,1',
            \App\Http\Middleware\ResolveTenantMiddleware::class,
            \App\Http\Middleware\CheckCooperativeAccess::class,
            \App\Http\Middleware\AuditLogMiddleware::class,
        ]);

        $middleware->group('financial', [
            'auth',
            'tenant',
            'throttle.financial',
            \App\Http\Middleware\ValidateFinancialAccess::class,
        ]);

        // SECURITY: Priority middleware (runs first)
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
            \App\Http\Middleware\ResolveTenantMiddleware::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // SECURITY: Enhanced exception handling
        $exceptions->render(function (\App\Domain\Financial\Exceptions\UnbalancedEntryException $e) {
            return response()->json([
                'error' => 'Financial transaction error',
                'message' => 'Journal entry must be balanced',
                'code' => 'UNBALANCED_ENTRY'
            ], 422);
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e) {
            \Log::warning('Authorization failed', [
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
                'url' => request()->fullUrl(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Access denied',
                'message' => 'You do not have permission to perform this action'
            ], 403);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e) {
            \Log::warning('Rate limit exceeded', [
                'ip' => request()->ip(),
                'user_id' => auth()->id(),
                'url' => request()->fullUrl(),
            ]);

            return response()->json([
                'error' => 'Too many requests',
                'message' => 'Please slow down and try again later'
            ], 429);
        });
    })
    ->create();
