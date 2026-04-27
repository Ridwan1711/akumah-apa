<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AnnouncementSegmentedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $titleText,
        public string $bodyText,
        public string $roleTarget = 'multi',
        public string $url = '/notifications'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement_segmented',
            'title' => $this->titleText,
            'body' => $this->bodyText,
            'message' => $this->bodyText,
            'url' => $this->url,
            'entity_type' => 'announcement',
            'entity_id' => '',
            'role_target' => $this->roleTarget,
            'priority' => 'p1',
            'collapse_key' => 'announcement_segmented_'.$this->roleTarget,
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
                'type' => 'announcement_segmented',
                'title' => $this->titleText,
                'body' => $this->bodyText,
                'url' => $this->url,
                'entity_type' => 'announcement',
                'entity_id' => '',
                'role_target' => $this->roleTarget,
                'priority' => 'p1',
                'collapse_key' => 'announcement_segmented_'.$this->roleTarget,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }
}
