<?php

namespace App\Notifications;

use App\Models\ReportCard;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReportCardPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ReportCard $reportCard
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        [$url, $roleTarget] = $this->resolveUrlAndRole($notifiable);

        return [
            'type' => 'report_card_published',
            'title' => 'Rapor Dipublikasikan',
            'body' => 'Rapor semester terbaru sudah tersedia.',
            'message' => 'Rapor semester terbaru sudah tersedia.',
            'url' => $url,
            'entity_type' => 'student',
            'entity_id' => (string) $this->reportCard->student_id,
            'role_target' => $roleTarget,
            'priority' => 'p1',
            'collapse_key' => 'report_card_published_'.(string) $this->reportCard->student_id.'_'.(string) $this->reportCard->semester_id,
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
        $body = 'Rapor semester terbaru sudah tersedia.';

        return [
            'title' => 'Rapor Dipublikasikan',
            'body' => $body,
            'data' => [
                'type' => 'report_card_published',
                'title' => 'Rapor Dipublikasikan',
                'body' => $body,
                'url' => $url,
                'entity_type' => 'student',
                'entity_id' => (string) $this->reportCard->student_id,
                'role_target' => $roleTarget,
                'priority' => 'p1',
                'collapse_key' => 'report_card_published_'.(string) $this->reportCard->student_id.'_'.(string) $this->reportCard->semester_id,
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
            return ['/wali/children/'.$this->reportCard->student_id, 'wali'];
        }

        return ['/santri/grades', 'santri'];
    }
}
