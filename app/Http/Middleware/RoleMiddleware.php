<?php
// app/Http/Middleware/RoleMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized($request);
        }

        // Check if user has any of the required roles
        $userRoles = $user->roles->pluck('name')->toArray();
        $hasRole = !empty(array_intersect($roles, $userRoles));

        if (!$hasRole) {
            return $this->forbidden($request, $roles);
        }

        return $next($request);
    }

    /**
     * Handle unauthorized access
     */
    private function unauthorized(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return redirect()->route('login');
    }

    /**
     * Handle forbidden access
     */
    private function forbidden(Request $request, array $roles): Response
    {
        $message = 'Access denied. Required roles: ' . implode(', ', $roles);

        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        return redirect()->back()->withErrors(['error' => $message]);
    }
}
