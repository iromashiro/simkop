<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CooperativeAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Admin Dinas can access all cooperatives
        if ($user->isAdminDinas()) {
            return $next($request);
        }

        // Admin Koperasi can only access their own cooperative
        if ($user->isAdminKoperasi()) {
            $cooperativeId = $this->getCooperativeIdFromRequest($request);

            if ($cooperativeId && $user->cooperative_id != $cooperativeId) {
                abort(403, 'Anda tidak memiliki akses ke data koperasi ini.');
            }
        }

        return $next($request);
    }

    private function getCooperativeIdFromRequest(Request $request): ?int
    {
        // Try to get cooperative_id from route parameters
        $routeParams = $request->route()?->parameters() ?? [];

        if (isset($routeParams['cooperative'])) {
            return (int) $routeParams['cooperative'];
        }

        // Try to get from query parameters
        if ($request->has('cooperative_id')) {
            return (int) $request->get('cooperative_id');
        }

        // Try to get from form data
        if ($request->has('cooperative_id')) {
            return (int) $request->input('cooperative_id');
        }

        return null;
    }
}
