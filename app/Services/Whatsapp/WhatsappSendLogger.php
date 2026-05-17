<?php

namespace App\Services\Whatsapp;

use App\Models\WaSendLog;
use Illuminate\Support\Facades\Cache;

final class WhatsappSendLogger
{
    public static function hashPhone(string $normalizedPhone): string
    {
        return hash_hmac('sha256', $normalizedPhone, (string) config('app.key'));
    }

    public function log(string $normalizedPhone, string $status, ?string $tag = null, ?string $error = null): void
    {
        try {
            WaSendLog::query()->create([
                'phone_hash' => self::hashPhone($normalizedPhone),
                'tag' => $tag !== null && $tag !== '' ? mb_substr($tag, 0, 64) : null,
                'status' => mb_substr($status, 0, 32),
                'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Audit log failure must not block sends.
        }
    }

    public function recordFailureForHalt(string $normalizedPhone, \Throwable $e): void
    {
        $this->log($normalizedPhone, 'failed', null, $e->getMessage());

        $key = 'wa:failures:'.now()->format('Y-m-d-H-i');
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addMinutes(2));

        $threshold = (int) config('services.wa.halt_failure_threshold', 5);
        if ($count >= $threshold) {
            $haltMinutes = (int) config('services.wa.halt_minutes', 30);
            Cache::put('wa:halt', true, now()->addMinutes($haltMinutes));
        }
    }

    public function isHalted(): bool
    {
        return (bool) Cache::get('wa:halt', false);
    }
}
