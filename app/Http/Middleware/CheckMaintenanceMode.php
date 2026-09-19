<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.is_maintenance', false)) {
            // Allow health check endpoints or direct maintenance preview route
            if ($request->is('health') || $request->is('up') || $request->is('maintenance')) {
                return $next($request);
            }

            return response()->view('pages.maintenance', ['title' => 'Maintenance'], 503);
        }

        return $next($request);
    }
}
