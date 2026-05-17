<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWaGatewayWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.wa.webhook_secret', '');
        if ($secret === '') {
            return $next($request);
        }

        $incoming = (string) $request->header('X-Wa-Gateway-Secret', '');
        if (! hash_equals($secret, $incoming)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
