<?php
// app/Http/Middleware/PermissionMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized($request);
        }

        // Check if user has any of the required permissions
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return $next($request);
            }
        }

        return $this->forbidden($request, $permissions);
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
    private function forbidden(Request $request, array $permissions): Response
    {
        $message = 'Access denied. Required permissions: ' . implode(', ', $permissions);

        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        return redirect()->back()->withErrors(['error' => $message]);
    }
}
