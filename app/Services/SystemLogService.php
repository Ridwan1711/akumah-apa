<?php

namespace App\Services;

use App\Models\SystemLog;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;

class SystemLogService
{
    public function write(
        string $level,
        string $message,
        array $context = [],
        array $extra = [],
        ?string $channel = null,
        ?DateTimeInterface $loggedAt = null,
    ): void {
        try {
            $request = request();

            SystemLog::query()->create([
                'channel' => $channel ?? 'app',
                'level' => strtolower($level),
                'message' => $message,
                'context' => $context === [] ? null : $context,
                'extra' => $extra === [] ? null : $extra,
                'user_id' => Auth::id(),
                'ip_address' => $request?->ip(),
                'method' => $request?->method(),
                'url' => $request?->fullUrl(),
                'trace' => $this->extractTrace($context),
                'logged_at' => $loggedAt ?? now(),
            ]);
        } catch (\Throwable) {
        }
    }

    public function info(string $message, array $context = [], array $extra = [], ?string $channel = null): void
    {
        $this->write('info', $message, $context, $extra, $channel);
    }

    public function warning(string $message, array $context = [], array $extra = [], ?string $channel = null): void
    {
        $this->write('warning', $message, $context, $extra, $channel);
    }

    public function error(string $message, array $context = [], array $extra = [], ?string $channel = null): void
    {
        $this->write('error', $message, $context, $extra, $channel);
    }

    private function extractTrace(array $context): ?string
    {
        $exception = $context['exception'] ?? null;

        if ($exception instanceof \Throwable) {
            return $exception->getTraceAsString();
        }

        return null;
    }
}
