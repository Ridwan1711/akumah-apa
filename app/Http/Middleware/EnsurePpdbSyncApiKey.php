<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePpdbSyncApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-API-Key', '');
        $expected = (string) config('services.ppdb_sync.api_key', '');

        if ($provided === '' || $expected === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Unauthorized PPDB sync request.');
        }

        return $next($request);
    }
}
