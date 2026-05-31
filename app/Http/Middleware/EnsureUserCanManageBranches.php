<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageBranches
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (! in_array($user->global_role, ['admin', 'editor', 'branch_admin'], true)) {
            abort(403);
        }

        return $next($request);
    }
}