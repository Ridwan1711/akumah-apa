<?php

namespace App\Services\Whatsapp;

use App\Services\Finance\FinanceWhatsappPhone;
use App\Services\Finance\NgedeployWaClient;
use App\Services\Whatsapp\Exceptions\WhatsappHaltedException;
use App\Services\Whatsapp\Exceptions\WhatsappNotReadyException;
use App\Services\Whatsapp\Exceptions\WhatsappRateLimitedException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WhatsappClient
{
    public function __construct(
        private readonly WhatsappRateLimiter $rateLimiter,
        private readonly WhatsappSendLogger $sendLogger,
        private readonly WhatsappSessionResolver $sessionResolver,
    ) {}

    /**
     * @throws WhatsappNotReadyException
     * @throws WhatsappRateLimitedException
     * @throws WhatsappHaltedException
     * @throws RuntimeException
     */
    public function send(
        string $phone,
        string $text,
        ?string $tag = null,
        bool $applyRateLimit = true,
        ?string $sessionSlug = null,
    ): WhatsappSendResult {
        if (! config('services.wa.enabled')) {
            Log::info('wa_send_skipped_disabled', ['tag' => $tag]);

            return new WhatsappSendResult(success: false);
        }

        if ($this->sendLogger->isHalted()) {
            throw new WhatsappHaltedException('WhatsApp sending dihentikan sementara (halt).');
        }

        $normalized = FinanceWhatsappPhone::normalize($phone);
        if ($normalized === null) {
            throw new RuntimeException('Nomor WhatsApp tidak valid.');
        }

        $session = $sessionSlug ?? $this->sessionResolver->resolveForTag($tag);
        $this->assertReady($session);

        if ($applyRateLimit) {
            $this->rateLimiter->assertCanSend($normalized);
        }

        $jitter = $this->rateLimiter->jitterDelaySeconds();
        if ($jitter > 0) {
            usleep($jitter * 1_000_000);
        }

        try {
            $this->httpSend($normalized, $text, $session);
        } catch (Throwable $e) {
            $this->sendLogger->recordFailureForHalt($normalized, $e);
            $this->sendLogger->log($normalized, 'failed', $tag, $e->getMessage());

            throw $e;
        }

        if ($applyRateLimit) {
            $this->rateLimiter->recordSend($normalized);
        }

        $this->sendLogger->log($normalized, 'sent', $tag);

        return new WhatsappSendResult(success: true);
    }

    public static function maskNumber(string $digits): string
    {
        return NgedeployWaClient::maskNumber($digits);
    }

    /**
     * @throws WhatsappNotReadyException
     */
    public function assertReady(?string $sessionSlug = null): void
    {
        if (! $this->isReady($sessionSlug)) {
            throw new WhatsappNotReadyException('WhatsApp gateway belum ready untuk sesi '.$this->resolveSession($sessionSlug).'.');
        }
    }

    public function isReady(?string $sessionSlug = null): bool
    {
        if (! config('services.wa.enabled')) {
            return false;
        }

        $session = $this->resolveSession($sessionSlug);
        $cacheKey = 'wa:health:ready:'.$session;
        $cacheSeconds = (int) config('services.wa.health_cache_seconds', 30);

        return (bool) Cache::remember($cacheKey, $cacheSeconds, function () use ($session): bool {
            $url = $this->sessionStatusUrl($session);
            $key = config('services.wa.key');
            $headers = [];
            if (is_string($key) && $key !== '') {
                $headers['x-api-key'] = $key;
            }

            try {
                $response = Http::withHeaders($headers)
                    ->timeout((int) config('services.wa.timeout_seconds', 15))
                    ->acceptJson()
                    ->get($url);

                if (! $response->successful()) {
                    return false;
                }

                $json = $response->json();

                return (bool) ($json['ready'] ?? false);
            } catch (Throwable) {
                return false;
            }
        });
    }

    private function httpSend(string $normalizedPhone, string $message, string $sessionSlug): void
    {
        $url = $this->sessionSendUrl($sessionSlug);
        $key = config('services.wa.key');
        $timeout = (int) config('services.wa.timeout_seconds', 30);

        $headers = [];
        if (is_string($key) && $key !== '') {
            $headers['x-api-key'] = $key;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->acceptJson()
                ->post($url, [
                    'number' => $normalizedPhone,
                    'message' => $message,
                ]);
        } catch (Throwable $e) {
            Log::warning('wa_send_http_failed', [
                'error' => $e->getMessage(),
                'session' => $sessionSlug,
                'number_suffix' => self::maskNumber($normalizedPhone),
            ]);

            Cache::forget('wa:health:ready:'.$sessionSlug);

            throw new RuntimeException('WhatsApp HTTP error: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() === 503) {
            Cache::forget('wa:health:ready:'.$sessionSlug);

            throw new WhatsappNotReadyException('WhatsApp belum ready (503).');
        }

        if (! $response->successful()) {
            Log::warning('wa_send_unsuccessful', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
                'session' => $sessionSlug,
                'number_suffix' => self::maskNumber($normalizedPhone),
            ]);

            if ($response->serverError()) {
                Cache::forget('wa:health:ready:'.$sessionSlug);
            }

            throw new RuntimeException('WhatsApp API returned '.$response->status());
        }
    }

    private function gatewayBaseUrl(): string
    {
        $base = (string) config('services.wa.gateway_base_url', '');
        if ($base === '') {
            $legacy = (string) config('services.wa.url', 'http://localhost:3001');
            $stripped = preg_replace('#/send-message/?$#', '', $legacy);
            $base = is_string($stripped) && $stripped !== '' ? $stripped : $legacy;
        }

        return rtrim($base, '/');
    }

    private function resolveSession(?string $sessionSlug): string
    {
        $session = trim((string) ($sessionSlug ?? ''));
        if ($session !== '') {
            return $session;
        }

        return (string) config('services.wa.default_session', 'pesantren');
    }

    private function sessionSendUrl(string $sessionSlug): string
    {
        $legacyUrl = (string) config('services.wa.url');
        if (str_contains($legacyUrl, '/send-message') && $this->gatewayBaseUrl() === rtrim(preg_replace('#/send-message/?$#', '', $legacyUrl) ?: $legacyUrl, '/')) {
            // backward compat when only WA_URL is set
        }

        return $this->gatewayBaseUrl().'/sessions/'.$this->resolveSession($sessionSlug).'/send-message';
    }

    private function sessionStatusUrl(string $sessionSlug): string
    {
        return $this->gatewayBaseUrl().'/sessions/'.$this->resolveSession($sessionSlug).'/status';
    }
}
