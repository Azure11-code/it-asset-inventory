<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $resource, string $action = 'view'): Response
    {
        $user = $request->user();
        if (!$user || !$user->hasPermission($resource, $action)) {
            abort(403, "You don't have permission to {$action} {$resource}.");
        }
        return $next($request);
    }
}
