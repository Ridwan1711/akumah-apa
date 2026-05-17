<?php

namespace App\Services\Whatsapp;

use App\Services\Whatsapp\Exceptions\WhatsappRateLimitedException;
use Illuminate\Support\Facades\Cache;

final class WhatsappRateLimiter
{
    /**
     * @throws WhatsappRateLimitedException
     */
    public function assertCanSend(string $normalizedPhone): void
    {
        $cooldownKey = 'wa:cooldown:'.$normalizedPhone;
        if (Cache::has($cooldownKey)) {
            throw new WhatsappRateLimitedException(
                'Cooldown aktif untuk nomor ini',
                (int) config('services.wa.cooldown_seconds', 30),
            );
        }

        $dailyKey = 'wa:daily:'.$normalizedPhone.':'.now()->format('Y-m-d');
        $dailyCount = (int) Cache::get($dailyKey, 0);
        $dailyLimit = (int) config('services.wa.daily_per_phone', 20);
        if ($dailyCount >= $dailyLimit) {
            throw new WhatsappRateLimitedException('Batas harian nomor tercapai', 3600);
        }

        $globalKey = 'wa:global:'.now()->format('Y-m-d-H-i');
        $globalCount = (int) Cache::get($globalKey, 0);
        $globalLimit = (int) config('services.wa.global_per_minute', 60);
        if ($globalCount >= $globalLimit) {
            throw new WhatsappRateLimitedException('Batas global per menit tercapai', 60);
        }
    }

    public function recordSend(string $normalizedPhone): void
    {
        $cooldownSeconds = (int) config('services.wa.cooldown_seconds', 30);
        Cache::put('wa:cooldown:'.$normalizedPhone, true, now()->addSeconds($cooldownSeconds));

        $dailyKey = 'wa:daily:'.$normalizedPhone.':'.now()->format('Y-m-d');
        $dailyCount = (int) Cache::get($dailyKey, 0);
        Cache::put($dailyKey, $dailyCount + 1, now()->addDay());

        $globalKey = 'wa:global:'.now()->format('Y-m-d-H-i');
        $globalCount = (int) Cache::get($globalKey, 0);
        Cache::put($globalKey, $globalCount + 1, now()->addMinutes(2));
    }

    public function jitterDelaySeconds(): int
    {
        return random_int(0, (int) config('services.wa.jitter_max_seconds', 3));
    }
}
