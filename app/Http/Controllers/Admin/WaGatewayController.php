<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WaGatewaySession;
use App\Services\Whatsapp\WaGatewayHttpClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaGatewayController extends Controller
{
    public function __construct(
        private readonly WaGatewayHttpClient $gateway,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $sessions = WaGatewaySession::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (WaGatewaySession $s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'label' => $s->label,
                'description' => $s->description,
                'status' => $s->status,
                'linked_phone' => $s->linked_phone,
                'has_qr' => filled($s->qr_data_url),
                'qr_data_url' => $s->qr_data_url,
                'qr_updated_at' => $s->qr_updated_at?->toIso8601String(),
                'last_ready_at' => $s->last_ready_at?->toIso8601String(),
                'last_error' => $s->last_error,
                'is_enabled' => $s->is_enabled,
            ]);

        return Inertia::render('admin/wa-gateway/index', [
            'sessions' => $sessions,
            'gateway_base_url' => $this->gateway->baseUrl(),
        ]);
    }

    public function start(Request $request, string $slug): JsonResponse
    {
        $this->authorizeAdmin($request);
        $session = $this->findSessionOrFail($slug);

        $result = $this->gateway->startSession($session->slug);

        return response()->json([
            'message' => 'Sesi dimulai. Tunggu QR muncul.',
            'gateway' => $result,
            'session' => $this->refreshSessionPayload($session->fresh()),
        ]);
    }

    public function qr(Request $request, string $slug): JsonResponse
    {
        $this->authorizeAdmin($request);
        $session = $this->findSessionOrFail($slug);

        return response()->json([
            'session' => $this->refreshSessionPayload($session),
        ]);
    }

    public function status(Request $request, string $slug): JsonResponse
    {
        $this->authorizeAdmin($request);
        $session = $this->findSessionOrFail($slug);

        try {
            $gateway = $this->gateway->sessionStatus($session->slug);
        } catch (\Throwable $e) {
            return response()->json([
                'session' => $this->refreshSessionPayload($session),
                'gateway_error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'session' => $this->refreshSessionPayload($session->fresh()),
            'gateway' => $gateway,
        ]);
    }

    public function logout(Request $request, string $slug): JsonResponse
    {
        $this->authorizeAdmin($request);
        $session = $this->findSessionOrFail($slug);

        $this->gateway->logoutSession($session->slug);

        $session->forceFill([
            'status' => WaGatewaySession::STATUS_DISCONNECTED,
            'linked_phone' => null,
            'qr_data_url' => null,
            'last_error' => null,
        ])->save();

        return response()->json([
            'message' => 'Sesi WhatsApp diputus.',
            'session' => $this->refreshSessionPayload($session->fresh()),
        ]);
    }

    private function findSessionOrFail(string $slug): WaGatewaySession
    {
        $session = WaGatewaySession::query()->where('slug', $slug)->first();
        if ($session === null) {
            abort(404, 'Sesi WA tidak ditemukan.');
        }

        return $session;
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshSessionPayload(WaGatewaySession $session): array
    {
        return [
            'id' => $session->id,
            'slug' => $session->slug,
            'label' => $session->label,
            'description' => $session->description,
            'status' => $session->status,
            'linked_phone' => $session->linked_phone,
            'qr_data_url' => $session->qr_data_url,
            'has_qr' => filled($session->qr_data_url),
            'qr_updated_at' => $session->qr_updated_at?->toIso8601String(),
            'last_ready_at' => $session->last_ready_at?->toIso8601String(),
            'last_error' => $session->last_error,
            'is_enabled' => $session->is_enabled,
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
