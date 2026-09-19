<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserTimezone
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timezone = $request->cookie('timezone');

        if ($timezone && in_array($timezone, timezone_identifiers_list(), true)) {
            date_default_timezone_set($timezone);
            config(['app.timezone' => $timezone]);
        }

        return $next($request);
    }
}
