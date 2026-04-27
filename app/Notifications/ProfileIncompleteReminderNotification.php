<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProfileIncompleteReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'profile_incomplete_reminder',
            'title' => 'Lengkapi Profil Anda',
            'body' => 'Data profil Anda belum lengkap. Mohon perbarui agar layanan berjalan optimal.',
            'message' => 'Data profil Anda belum lengkap. Mohon perbarui agar layanan berjalan optimal.',
            'url' => '/profile/edit',
            'entity_type' => 'profile',
            'entity_id' => '',
            'role_target' => 'multi',
            'priority' => 'p2',
            'collapse_key' => 'profile_incomplete_reminder',
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        $body = 'Data profil Anda belum lengkap. Mohon perbarui agar layanan berjalan optimal.';

        return [
            'title' => 'Lengkapi Profil Anda',
            'body' => $body,
            'data' => [
                'type' => 'profile_incomplete_reminder',
                'title' => 'Lengkapi Profil Anda',
                'body' => $body,
                'url' => '/profile/edit',
                'entity_type' => 'profile',
                'entity_id' => '',
                'role_target' => 'multi',
                'priority' => 'p2',
                'collapse_key' => 'profile_incomplete_reminder',
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }
}
