<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PeriodicReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'periodic_reminder',
            'title' => 'Pengingat Berkala',
            'body' => 'Tetap cek aplikasi untuk pembaruan jadwal, nilai, dan informasi penting lainnya.',
            'message' => 'Tetap cek aplikasi untuk pembaruan jadwal, nilai, dan informasi penting lainnya.',
            'url' => '/notifications',
            'entity_type' => 'reminder',
            'entity_id' => '',
            'role_target' => 'multi',
            'priority' => 'p2',
            'collapse_key' => 'periodic_reminder',
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        $body = 'Tetap cek aplikasi untuk pembaruan jadwal, nilai, dan informasi penting lainnya.';

        return [
            'title' => 'Pengingat Berkala',
            'body' => $body,
            'data' => [
                'type' => 'periodic_reminder',
                'title' => 'Pengingat Berkala',
                'body' => $body,
                'url' => '/notifications',
                'entity_type' => 'reminder',
                'entity_id' => '',
                'role_target' => 'multi',
                'priority' => 'p2',
                'collapse_key' => 'periodic_reminder',
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }
}
