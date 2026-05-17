<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class WaGatewayHttpClient
{
    public function baseUrl(): string
    {
        $base = (string) config('services.wa.gateway_base_url', 'http://localhost:3001');

        return rtrim($base, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function headers(): array
    {
        $headers = ['Accept' => 'application/json'];
        $key = config('services.wa.key');
        if (is_string($key) && $key !== '') {
            $headers['x-api-key'] = $key;
        }

        return $headers;
    }

    private function timeout(): int
    {
        return (int) config('services.wa.timeout_seconds', 15);
    }

    /**
     * @return array<string, mixed>
     */
    public function startSession(string $slug): array
    {
        return $this->request('post', "/sessions/{$slug}/start");
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionStatus(string $slug): array
    {
        return $this->request('get', "/sessions/{$slug}/status");
    }

    /**
     * @return array<string, mixed>
     */
    public function logoutSession(string $slug): array
    {
        return $this->request('post', "/sessions/{$slug}/logout");
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path): array
    {
        $url = $this->baseUrl().$path;

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout())
                ->{$method}($url);

            if (! $response->successful()) {
                Log::warning('wa_gateway_http_failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                throw new RuntimeException('WA gateway returned '.$response->status());
            }

            return $response->json() ?? [];
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException('WA gateway HTTP error: '.$e->getMessage(), 0, $e);
        }
    }
}
