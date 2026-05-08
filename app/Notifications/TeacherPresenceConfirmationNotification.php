<?php

namespace App\Notifications;

use App\Models\LessonSession;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TeacherPresenceConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LessonSession $session) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'teacher_presence_confirmation_required',
            'title' => 'Konfirmasi kehadiran mengajar',
            'body' => $this->body(),
            'message' => $this->body(),
            'url' => '/guru/attendance',
            'entity_type' => 'lesson_session',
            'entity_id' => (string) $this->session->id,
            'role_target' => 'guru',
            'priority' => 'p0',
            'collapse_key' => 'teacher_presence_'.$this->session->id,
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        $body = $this->body();

        return [
            'title' => 'Konfirmasi kehadiran mengajar',
            'body' => $body,
            'data' => [
                'type' => 'teacher_presence_confirmation_required',
                'title' => 'Konfirmasi kehadiran mengajar',
                'body' => $body,
                'url' => '/guru/attendance',
                'entity_type' => 'lesson_session',
                'entity_id' => (string) $this->session->id,
                'role_target' => 'guru',
                'priority' => 'p0',
                'collapse_key' => 'teacher_presence_'.$this->session->id,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    private function body(): string
    {
        $schedule = $this->session->schedule;
        $subject = $schedule?->subject?->name ?? 'Mapel';
        $class = $schedule?->schoolClass?->name ?? 'Kelas';
        $date = $this->session->date?->translatedFormat('d M Y') ?? '';
        $time = trim(($this->session->start_time ?? '').' - '.($this->session->end_time ?? ''));

        return "Sesi {$subject} ({$class}) {$date} {$time} belum memiliki konfirmasi kehadiran. Mohon pilih status Hadir/Izin/Sakit/Alpa.";
    }
}

