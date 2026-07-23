<?php

namespace App\Http\Middleware;

use App\Support\PermissionResolver;
use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * Usage: ->middleware('permission:branches,view')
     */
    public function handle(Request $request, Closure $next, string $moduleSlug, string $action)
    {
        $user = $request->user();

        abort_unless($user, 401);
        abort_unless(PermissionResolver::can($user, $moduleSlug, $action), 403, 'You do not have permission to perform this action.');

        return $next($request);
    }
}
