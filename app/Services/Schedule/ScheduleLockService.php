<?php

namespace App\Services\Schedule;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ScheduleLockService
{
    public const DEFAULT_TTL_SECONDS = 10;

    public function acquire(
        int $scheduleSetId,
        int $classId,
        int $day,
        int $jamNo,
        User $user,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): array {
        $ttlSeconds = max(3, $ttlSeconds);
        $token = Str::lower(Str::random(32));
        $expiresAt = CarbonImmutable::now()->addSeconds($ttlSeconds);

        $payload = [
            'schedule_set_id' => $scheduleSetId,
            'class_id' => $classId,
            'day' => $day,
            'jam_no' => $jamNo,
            'user_id' => (int) $user->id,
            'user_name' => (string) ($user->name ?? "User #{$user->id}"),
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        $key = $this->slotKey($scheduleSetId, $classId, $day, $jamNo);
        $acquired = (bool) Redis::setnx($key, json_encode($payload));
        if ($acquired) {
            Redis::expire($key, $ttlSeconds);
            Redis::sadd($this->userIndexKey($scheduleSetId, (int) $user->id), [$key]);

            return [
                'acquired' => true,
                'lock' => $payload,
            ];
        }

        $existing = $this->peek($scheduleSetId, $classId, $day, $jamNo);

        return [
            'acquired' => false,
            'lock' => $existing,
        ];
    }

    public function peek(int $scheduleSetId, int $classId, int $day, int $jamNo): ?array
    {
        $raw = Redis::get($this->slotKey($scheduleSetId, $classId, $day, $jamNo));
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function extend(
        int $scheduleSetId,
        int $classId,
        int $day,
        int $jamNo,
        string $token,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): bool {
        $lock = $this->peek($scheduleSetId, $classId, $day, $jamNo);
        if (! $lock || ($lock['token'] ?? null) !== $token) {
            return false;
        }

        $ttlSeconds = max(3, $ttlSeconds);
        $lock['expires_at'] = CarbonImmutable::now()->addSeconds($ttlSeconds)->toIso8601String();
        $key = $this->slotKey($scheduleSetId, $classId, $day, $jamNo);
        Redis::setex($key, $ttlSeconds, json_encode($lock));

        return true;
    }

    public function release(
        int $scheduleSetId,
        int $classId,
        int $day,
        int $jamNo,
        string $token,
    ): bool {
        $lock = $this->peek($scheduleSetId, $classId, $day, $jamNo);
        if (! $lock || ($lock['token'] ?? null) !== $token) {
            return false;
        }

        $key = $this->slotKey($scheduleSetId, $classId, $day, $jamNo);
        Redis::del($key);
        $userId = (int) ($lock['user_id'] ?? 0);
        if ($userId > 0) {
            Redis::srem($this->userIndexKey($scheduleSetId, $userId), [$key]);
        }

        return true;
    }

    public function releaseAllForUser(int $scheduleSetId, int $userId): int
    {
        $indexKey = $this->userIndexKey($scheduleSetId, $userId);
        $keys = Redis::smembers($indexKey);
        $released = 0;

        if (is_array($keys)) {
            foreach ($keys as $key) {
                $raw = Redis::get($key);
                if (! is_string($raw) || $raw === '') {
                    continue;
                }
                $decoded = json_decode($raw, true);
                if (! is_array($decoded)) {
                    continue;
                }
                if ((int) ($decoded['user_id'] ?? 0) !== $userId) {
                    continue;
                }
                Redis::del($key);
                $released++;
            }
        }

        Redis::del($indexKey);

        return $released;
    }

    public function releaseAllForUserAcrossSets(int $userId): int
    {
        $pattern = "schedule_set:*:locks:user:{$userId}";
        $keys = Redis::command('keys', [$pattern]);
        $released = 0;

        if (! is_array($keys) || empty($keys)) {
            return 0;
        }

        foreach ($keys as $indexKey) {
            $parts = explode(':', (string) $indexKey);
            $setId = isset($parts[1]) ? (int) $parts[1] : 0;
            if ($setId <= 0) {
                continue;
            }
            $released += $this->releaseAllForUser($setId, $userId);
        }

        return $released;
    }

    public function isLockedByOther(
        int $scheduleSetId,
        int $classId,
        int $day,
        int $jamNo,
        int $currentUserId,
    ): ?array {
        $lock = $this->peek($scheduleSetId, $classId, $day, $jamNo);
        if (! $lock) {
            return null;
        }

        $userId = (int) ($lock['user_id'] ?? 0);
        if ($userId === 0 || $userId === $currentUserId) {
            return null;
        }

        return $lock;
    }

    public function slotKey(int $scheduleSetId, int $classId, int $day, int $jamNo): string
    {
        return "schedule_set:{$scheduleSetId}:lock:{$classId}:{$day}:{$jamNo}";
    }

    public function userIndexKey(int $scheduleSetId, int $userId): string
    {
        return "schedule_set:{$scheduleSetId}:locks:user:{$userId}";
    }
}

