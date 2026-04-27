<?php

namespace App\Notifications;

use App\Models\LessonSession;
use App\Models\Student;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StudentAbsentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Student $student,
        public LessonSession $session,
        public ?string $subjectName = null,
        public ?string $className = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_absent',
            'title' => 'Anak Anda tidak hadir',
            'body' => $this->body(),
            'message' => $this->body(),
            'url' => '/wali/children/'.$this->student->id,
            'entity_type' => 'student',
            'entity_id' => (string) $this->student->id,
            'role_target' => 'wali',
            'priority' => 'p0',
            'collapse_key' => 'student_absent_'.(string) $this->student->id.'_'.(string) $this->session->id,
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
            'title' => 'Anak Anda tidak hadir',
            'body' => $this->body(),
            'data' => [
                'type' => 'student_absent',
                'title' => 'Anak Anda tidak hadir',
                'body' => $this->body(),
                'url' => '/wali/children/'.$this->student->id,
                'entity_type' => 'student',
                'entity_id' => (string) $this->student->id,
                'role_target' => 'wali',
                'priority' => 'p0',
                'collapse_key' => 'student_absent_'.(string) $this->student->id.'_'.(string) $this->session->id,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    private function body(): string
    {
        $name = $this->student->full_name ?? 'Santri';
        $subject = $this->subjectName ?? 'pelajaran';
        $class = $this->className ? " ({$this->className})" : '';
        $date = $this->session->date?->translatedFormat('d M Y') ?? '';

        return "{$name} tercatat tidak hadir pada {$subject}{$class} {$date}.";
    }
}
