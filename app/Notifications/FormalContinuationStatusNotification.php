<?php

namespace App\Notifications;

use App\Models\FormalContinuationRound;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentFormalContinuationRequest;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FormalContinuationStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public FormalContinuationRound $round,
        public Student $student,
        public string $event,
        public ?StudentFormalContinuationRequest $request = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        [$url, $roleTarget] = $this->resolveUrlAndRole($notifiable);
        $body = $this->body();

        return [
            'type' => 'formal_continuation_status',
            'title' => $this->title(),
            'body' => $body,
            'message' => $body,
            'url' => $url,
            'entity_type' => 'student_formal_continuation_request',
            'entity_id' => (string) ($this->request?->id ?? $this->round->id),
            'role_target' => $roleTarget,
            'priority' => 'p1',
            'collapse_key' => 'formal_continuation_'.$this->student->id.'_'.$this->event,
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
        $title = $this->title();
        $body = $this->body();

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'formal_continuation_status',
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'entity_type' => 'student_formal_continuation_request',
                'entity_id' => (string) ($this->request?->id ?? $this->round->id),
                'role_target' => $roleTarget,
                'priority' => 'p1',
                'collapse_key' => 'formal_continuation_'.$this->student->id.'_'.$this->event,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    private function title(): string
    {
        return match ($this->event) {
            'invited' => 'Konfirmasi Lanjut Formal',
            'santri_pending' => 'Konfirmasi Santri Diperlukan',
            'wali_pending' => 'Konfirmasi Wali Diperlukan',
            'pending_admin', 'admin_pending' => 'Menunggu Admin',
            'approved' => 'Lanjut Formal Disetujui',
            'rejected' => 'Konfirmasi Ditolak',
            'cancelled' => 'Permohonan Dibatalkan',
            default => 'Lanjut Formal',
        };
    }

    private function body(): string
    {
        $name = $this->student->full_name;
        $this->round->loadMissing('sourceAcademicYear', 'targetAcademicYear');
        $ta = $this->round->targetAcademicYear?->name ?? 'tahun ajaran berikutnya';

        return match ($this->event) {
            'invited' => "Konfirmasi lanjut formal (MA 10 / Kuliah) untuk {$name} menuju TA {$ta}. Santri dan wali harus mengisi; keputusan wali yang berlaku.",
            'santri_pending' => "Wali sudah mengisi. Santri {$name} perlu konfirmasi lanjut formal.",
            'wali_pending' => "Santri {$name} sudah mengisi. Wali perlu konfirmasi (keputusan wali yang berlaku).",
            'pending_admin', 'admin_pending' => "Konfirmasi lanjut formal {$name} menunggu persetujuan admin.",
            'approved' => "Enrollment formal TA {$ta} untuk {$name} telah disetujui admin.",
            'rejected' => "Konfirmasi lanjut formal {$name} ditolak admin.",
            'cancelled' => "Konfirmasi lanjut formal {$name} dibatalkan.",
            default => "Status konfirmasi lanjut formal {$name} diperbarui.",
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveUrlAndRole(object $notifiable): array
    {
        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole(Role::WALI_SANTRI)) {
            return ['/wali/children/'.$this->student->id.'/formal-continuation', 'wali'];
        }

        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole(Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK)) {
            return ['/admin/formal-continuation', 'admin'];
        }

        return ['/santri/formal-continuation', 'santri'];
    }
}
