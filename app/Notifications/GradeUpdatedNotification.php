<?php

namespace App\Notifications;

use App\Models\Diniyyah\Score;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GradeUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Score $score
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        [$url, $roleTarget] = $this->resolveUrlAndRole($notifiable);

        return [
            'type' => 'grade_updated',
            'title' => 'Nilai Diperbarui',
            'body' => $this->body(),
            'message' => $this->body(),
            'url' => $url,
            'entity_type' => 'student',
            'entity_id' => (string) $this->score->student_id,
            'role_target' => $roleTarget,
            'priority' => 'p1',
            'collapse_key' => 'grade_updated_'.(string) $this->score->student_id.'_'.(string) $this->score->subject_id,
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
            'title' => 'Nilai Diperbarui',
            'body' => $body,
            'data' => [
                'type' => 'grade_updated',
                'title' => 'Nilai Diperbarui',
                'body' => $body,
                'url' => $url,
                'entity_type' => 'student',
                'entity_id' => (string) $this->score->student_id,
                'role_target' => $roleTarget,
                'priority' => 'p1',
                'collapse_key' => 'grade_updated_'.(string) $this->score->student_id.'_'.(string) $this->score->subject_id,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    private function body(): string
    {
        $subject = $this->score->subject?->name ?? 'pelajaran';
        $value = $this->score->score !== null ? (string) $this->score->score : '-';

        return "Nilai {$subject} diperbarui menjadi {$value}.";
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveUrlAndRole(object $notifiable): array
    {
        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('wali_santri')) {
            return ['/wali/children/'.$this->score->student_id, 'wali'];
        }

        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('guru')) {
            return ['/guru/kitab-grades', 'guru'];
        }

        return ['/santri/grades', 'santri'];
    }
}
