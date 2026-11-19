<?php

namespace App\Http\Middleware;

use App\Support\Plan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After any form submission the cached plan is dropped — a bulk change written
 * straight to the database (no model event) must still show on the next page.
 */
class FlushPlanAfterWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $request->isMethodSafe()) {
            Plan::flush();
        }

        return $response;
    }
}
