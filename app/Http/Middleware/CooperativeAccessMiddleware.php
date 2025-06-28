<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CooperativeAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Anda harus login terlebih dahulu.');
        }

        $user = auth()->user();

        // Admin dinas can access all cooperatives
        if ($user->hasRole('admin_dinas')) {
            return $next($request);
        }

        // Admin koperasi can only access their own cooperative
        if ($user->hasRole('admin_koperasi')) {
            $cooperativeId = $this->extractCooperativeId($request);

            if ($cooperativeId && $cooperativeId != $user->cooperative_id) {
                abort(403, 'Anda tidak memiliki akses ke koperasi ini.');
            }
        }

        return $next($request);
    }

    /**
     * Extract cooperative ID from request.
     */
    private function extractCooperativeId(Request $request): ?int
    {
        // Check route parameters
        if ($request->route('cooperative')) {
            return (int) $request->route('cooperative');
        }

        if ($request->route('cooperative_id')) {
            return (int) $request->route('cooperative_id');
        }

        // Check query parameters
        if ($request->query('cooperative_id')) {
            return (int) $request->query('cooperative_id');
        }

        // Check form data
        if ($request->input('cooperative_id')) {
            return (int) $request->input('cooperative_id');
        }

        return null;
    }
}
