<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->user()?->isAdmin()) {
            if ($request->ajax()) {
                return response()->json(['error' => 'No autorizado'], 403);
            }
            abort(403, 'No autorizado');
        }
        return $next($request);
    }
}
