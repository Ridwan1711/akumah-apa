<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\WaGatewaySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaGatewayWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event' => ['required', 'string', 'max:64'],
            'session_id' => ['required', 'string', 'max:64'],
            'qr_raw' => ['nullable', 'string'],
            'qr_data_url' => ['nullable', 'string'],
            'linked_phone' => ['nullable', 'string', 'max:32'],
            'message' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        $session = WaGatewaySession::query()
            ->where('slug', $validated['session_id'])
            ->first();

        if ($session === null) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        $event = $validated['event'];

        match ($event) {
            'qr' => $session->forceFill([
                'status' => WaGatewaySession::STATUS_PAIRING,
                'qr_data_url' => $validated['qr_data_url'] ?? null,
                'qr_updated_at' => now(),
                'last_error' => null,
            ]),
            'ready' => $session->forceFill([
                'status' => WaGatewaySession::STATUS_READY,
                'linked_phone' => $validated['linked_phone'] ?? $session->linked_phone,
                'qr_data_url' => null,
                'last_ready_at' => now(),
                'last_error' => null,
            ]),
            'authenticated' => $session->forceFill([
                'last_error' => null,
            ]),
            'auth_failure' => $session->forceFill([
                'status' => WaGatewaySession::STATUS_AUTH_FAILURE,
                'last_error' => $validated['message'] ?? 'Auth failure',
                'qr_data_url' => null,
            ]),
            'disconnected' => $session->forceFill([
                'status' => WaGatewaySession::STATUS_DISCONNECTED,
                'last_error' => $validated['reason'] ?? $validated['message'] ?? null,
                'qr_data_url' => null,
            ]),
            default => null,
        };

        $session->save();

        return response()->json(['success' => true]);
    }
}
