<?php
// app/Http/Middleware/SecurityHeadersMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Security headers from config
        $headers = config('security.headers', []);

        foreach ($headers as $header => $value) {
            $response->headers->set($header, $value);
        }

        // Content Security Policy
        if (config('security.csp.enabled', true)) {
            $cspDirectives = config('security.csp.directives', []);
            $csp = collect($cspDirectives)
                ->map(fn($value, $key) => "{$key} {$value}")
                ->implode('; ');

            $headerName = config('security.csp.report_only', false)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($headerName, $csp);
        }

        // HSTS for HTTPS
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
