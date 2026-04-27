<?php

namespace App\Notifications;

use App\Models\StudentViolation;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ViolationRecordedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StudentViolation $violation
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        [$url, $roleTarget] = $this->resolveUrlAndRole($notifiable);
        $typeName = $this->violation->violationType?->name ?? 'Pelanggaran';

        return [
            'type' => 'violation_recorded',
            'title' => 'Pelanggaran Tercatat',
            'body' => "Pelanggaran baru tercatat: {$typeName}.",
            'message' => "Pelanggaran baru tercatat: {$typeName}.",
            'url' => $url,
            'entity_type' => 'student',
            'entity_id' => (string) $this->violation->student_id,
            'role_target' => $roleTarget,
            'priority' => 'p1',
            'collapse_key' => 'violation_recorded_'.(string) $this->violation->id,
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        [$url, $roleTarget] = $this->resolveUrlAndRole($notifiable);
        $typeName = $this->violation->violationType?->name ?? 'Pelanggaran';
        $body = "Pelanggaran baru tercatat: {$typeName}.";

        return [
            'title' => 'Pelanggaran Tercatat',
            'body' => $body,
            'data' => [
                'type' => 'violation_recorded',
                'title' => 'Pelanggaran Tercatat',
                'body' => $body,
                'url' => $url,
                'entity_type' => 'student',
                'entity_id' => (string) $this->violation->student_id,
                'role_target' => $roleTarget,
                'priority' => 'p1',
                'collapse_key' => 'violation_recorded_'.(string) $this->violation->id,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveUrlAndRole(object $notifiable): array
    {
        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('wali_santri')) {
            return ['/wali/children/'.$this->violation->student_id, 'wali'];
        }

        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('super_admin', 'admin_akademik')) {
            return ['/admin/violations', 'admin'];
        }

        return ['/santri/violations', 'santri'];
    }
}
