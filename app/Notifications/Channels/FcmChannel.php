<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use Illuminate\Contracts\Container\Container;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

class FcmChannel
{
    public function __construct(private readonly Container $container) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $tokens = [];
        if (method_exists($notification, 'tokensForNotifiable')) {
            $tokens = $notification->tokensForNotifiable($notifiable) ?? [];
        } elseif (method_exists($notifiable, 'routeNotificationFor')) {
            $tokens = $notifiable->routeNotificationFor('fcm', $notification) ?? [];
        }

        $tokens = array_values(array_filter((array) $tokens));
        if (empty($tokens)) {
            $payload = $notification->toFcm($notifiable);
            $data = $this->sanitizeData($payload['data'] ?? []);
            Log::info('notification_dispatch_skipped', [
                'channel' => 'fcm',
                'reason' => 'no_tokens',
                'notification_id' => $data['notification_id'] ?? null,
                'type' => $data['type'] ?? class_basename($notification),
                'target_user_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            ]);

            return;
        }

        try {
            /** @var Messaging $messaging */
            $messaging = $this->container->make(Messaging::class);
        } catch (Throwable $e) {
            // Firebase credential belum diset atau package belum siap.
            // Biarkan channel 'database' tetap bekerja; FCM di-skip diam-diam.
            Log::debug('FCM disabled (Messaging unavailable)', ['error' => $e->getMessage()]);

            return;
        }

        /** @var array{title?: string, body?: string, data?: array<string, string>} $payload */
        $payload = $notification->toFcm($notifiable);
        $data = $this->sanitizeData($payload['data'] ?? []);
        $notificationId = $data['notification_id'] ?? null;
        $type = $data['type'] ?? class_basename($notification);
        $collapseKey = $data['collapse_key'] ?? $this->buildDefaultCollapseKey($type, $data['entity_id'] ?? null);

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create(
                $payload['title'] ?? 'Notifikasi',
                $payload['body'] ?? '',
            ))
            ->withData($data);
        $androidConfig = [
            'priority' => 'high',
            'ttl' => '3600s',
            'notification' => [
                'channel_id' => 'siakad_default',
                'sound' => 'default',
            ],
        ];
        if ($collapseKey !== null && $collapseKey !== '') {
            $androidConfig['collapse_key'] = $collapseKey;
        }
        $message = $message->withAndroidConfig(AndroidConfig::fromArray($androidConfig));

        Log::info('notification_dispatch_attempt', [
            'channel' => 'fcm',
            'status' => 'attempt',
            'notification_id' => $notificationId,
            'type' => $type,
            'target_user_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            'tokens_count' => count($tokens),
            'collapse_key' => $collapseKey,
        ]);

        try {
            $report = $messaging->sendMulticast($message, $tokens);
        } catch (Throwable $e) {
            Log::warning('FCM sendMulticast failed', [
                'error' => $e->getMessage(),
                'notifiable_id' => $notifiable->getKey(),
                'tokens_count' => count($tokens),
                'notification_id' => $notificationId,
                'type' => $type,
                'collapse_key' => $collapseKey,
            ]);

            return;
        }

        $invalid = array_merge($report->invalidTokens(), $report->unknownTokens());
        if (! empty($invalid)) {
            DeviceToken::query()->whereIn('token', $invalid)->delete();
        }

        Log::info('notification_dispatch_result', [
            'channel' => 'fcm',
            'status' => $report->hasFailures() ? 'partial_failure' : 'success',
            'notification_id' => $notificationId,
            'type' => $type,
            'target_user_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            'success' => $report->successes()->count(),
            'failures' => $report->failures()->count(),
            'invalid_cleaned' => count($invalid),
            'collapse_key' => $collapseKey,
        ]);

        if ($report->hasFailures()) {
            Log::info('FCM partial failures', [
                'notifiable_id' => $notifiable->getKey(),
                'success' => $report->successes()->count(),
                'failures' => $report->failures()->count(),
                'invalid_cleaned' => count($invalid),
            ]);
        }
    }

    /**
     * FCM data payload harus berupa string semua.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function sanitizeData(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $result[(string) $key] = is_scalar($value)
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $result;
    }

    private function buildDefaultCollapseKey(string $type, ?string $entityId): string
    {
        $entityPart = $entityId !== null && $entityId !== '' ? "_{$entityId}" : '';

        return "notif_{$type}{$entityPart}";
    }
}
