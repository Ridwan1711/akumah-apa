<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (empty($permissions)) {
            return $next($request);
        }

        if (! $user->hasAnyPermission($permissions)) {
            Log::warning('Permission denied', [
                'user_id' => $user->id,
                'permissions_checked' => $permissions,
                'path' => $request->path(),
            ]);
            abort(403, 'Anda tidak memiliki izin untuk aksi ini.');
        }

        return $next($request);
    }
}

