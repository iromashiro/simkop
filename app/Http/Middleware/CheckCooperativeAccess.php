<?php
// app/Http/Middleware/CheckCooperativeAccess.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CheckCooperativeAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized($request, 'User not authenticated');
        }

        // Rate limiting per user
        $rateLimitKey = 'cooperative_access:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 1000)) {
            Log::warning('Rate limit exceeded for cooperative access', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'error' => 'Too many requests',
                'retry_after' => RateLimiter::availableIn($rateLimitKey)
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 3600); // 1 hour window

        // Check cooperative access
        if (!$user->cooperative_id) {
            Log::warning('User attempted access without cooperative', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'No cooperative access'], 403);
        }

        // Cache cooperative status for performance
        $cooperative = Cache::remember(
            "cooperative:{$user->cooperative_id}:status",
            300, // 5 minutes
            fn() => $user->cooperative
        );

        if (!$cooperative || $cooperative->status !== 'active') {
            Log::warning('User attempted access to inactive cooperative', [
                'user_id' => $user->id,
                'cooperative_id' => $user->cooperative_id,
                'cooperative_status' => $cooperative?->status,
            ]);

            return response()->json(['error' => 'Cooperative is not active'], 403);
        }

        // Check cooperative subscription status
        if ($this->isSubscriptionExpired($cooperative)) {
            Log::warning('User attempted access with expired subscription', [
                'user_id' => $user->id,
                'cooperative_id' => $user->cooperative_id,
            ]);

            return response()->json(['error' => 'Cooperative subscription expired'], 402);
        }

        // Add cooperative context to request
        $request->merge([
            'cooperative_id' => $user->cooperative_id,
            'cooperative' => $cooperative,
        ]);

        // Log successful access
        Log::info('Cooperative access granted', [
            'user_id' => $user->id,
            'cooperative_id' => $user->cooperative_id,
            'route' => $request->route()?->getName(),
        ]);

        return $next($request);
    }

    /**
     * Handle unauthorized access
     */
    private function unauthorized(Request $request, string $reason): Response
    {
        Log::warning('Unauthorized cooperative access attempt', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return redirect()->route('login')->withErrors(['error' => $reason]);
    }

    /**
     * Check if cooperative subscription is expired
     */
    private function isSubscriptionExpired($cooperative): bool
    {
        if (!$cooperative->subscription_expires_at) {
            return false; // No expiration set
        }

        return $cooperative->subscription_expires_at->isPast();
    }
}
