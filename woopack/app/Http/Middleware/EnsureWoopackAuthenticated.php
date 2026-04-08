<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWoopackAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('woopack_authenticated', false)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
