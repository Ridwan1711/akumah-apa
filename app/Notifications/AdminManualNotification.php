<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use App\Models\User;
use Illuminate\Notifications\Notification;

class AdminManualNotification extends Notification
{
    /**
     * @param  array<int, int>  $deviceTokenIds
     */
    public function __construct(
        public string $titleText,
        public string $bodyText,
        public string $deeplink = '/notifications',
        public array $deviceTokenIds = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'admin_manual',
            'title' => $this->titleText,
            'body' => $this->bodyText,
            'message' => $this->bodyText,
            'url' => $this->deeplink,
            'entity_type' => 'announcement',
            'entity_id' => '',
            'priority' => 'p1',
            'collapse_key' => 'admin_manual',
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->titleText,
            'body' => $this->bodyText,
            'data' => [
                'type' => 'admin_manual',
                'title' => $this->titleText,
                'body' => $this->bodyText,
                'url' => $this->deeplink,
                'entity_type' => 'announcement',
                'entity_id' => '',
                'priority' => 'p1',
                'collapse_key' => 'admin_manual',
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tokensForNotifiable(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        $query = $notifiable->deviceTokens();
        if (! empty($this->deviceTokenIds)) {
            $query->whereIn('id', $this->deviceTokenIds);
        }

        return $query->pluck('token')->all();
    }
}
