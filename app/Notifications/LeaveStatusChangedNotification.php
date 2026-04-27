<?php

namespace App\Notifications;

use App\Models\LeavePermission;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LeaveStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LeavePermission $leave
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        [$url, $roleTarget] = $this->resolveUrlAndRole($notifiable);

        return [
            'type' => 'leave_status_changed',
            'title' => 'Status Izin Diperbarui',
            'body' => $this->body(),
            'message' => $this->body(),
            'url' => $url,
            'entity_type' => 'leave_permission',
            'entity_id' => (string) $this->leave->id,
            'role_target' => $roleTarget,
            'priority' => 'p1',
            'collapse_key' => 'leave_status_changed_'.(string) $this->leave->id,
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
        $body = $this->body();

        return [
            'title' => 'Status Izin Diperbarui',
            'body' => $body,
            'data' => [
                'type' => 'leave_status_changed',
                'title' => 'Status Izin Diperbarui',
                'body' => $body,
                'url' => $url,
                'entity_type' => 'leave_permission',
                'entity_id' => (string) $this->leave->id,
                'role_target' => $roleTarget,
                'priority' => 'p1',
                'collapse_key' => 'leave_status_changed_'.(string) $this->leave->id,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    private function body(): string
    {
        return match ($this->leave->status) {
            LeavePermission::STATUS_APPROVED => 'Permohonan izin telah disetujui.',
            LeavePermission::STATUS_REJECTED => 'Permohonan izin ditolak.',
            default => 'Status permohonan izin diperbarui.',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveUrlAndRole(object $notifiable): array
    {
        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('wali_santri')) {
            return ['/wali/children/'.$this->leave->student_id, 'wali'];
        }

        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('super_admin', 'admin_akademik')) {
            return ['/admin/leave-permissions', 'admin'];
        }

        return ['/santri/leaves', 'santri'];
    }
}
