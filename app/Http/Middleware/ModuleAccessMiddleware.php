<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModuleAccessMiddleware
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (!$request->user() || !$request->user()->hasModuleAccess($module)) {
            return response()->view('errors.access-denied', [
                'module' => $module,
            ], 403);
        }

        return $next($request);
    }
}
