<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class NgedeployWaClient
{
    public function send(string $numberDigits, string $message): void
    {
        $url = (string) config('services.wa.url');
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
                    'number' => $numberDigits,
                    'message' => $message,
                ]);
        } catch (Throwable $e) {
            Log::warning('wa_send_http_failed', [
                'error' => $e->getMessage(),
                'number_suffix' => self::maskNumber($numberDigits),
            ]);

            throw new RuntimeException('WhatsApp HTTP error: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            Log::warning('wa_send_unsuccessful', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
                'number_suffix' => self::maskNumber($numberDigits),
            ]);

            throw new RuntimeException('WhatsApp API returned '.$response->status());
        }
    }

    public static function maskNumber(string $digits): string
    {
        if (strlen($digits) <= 4) {
            return '****';
        }

        return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }
}
