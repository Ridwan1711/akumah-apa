<?php

namespace App\Actions\TeacherPresence;

use App\Models\LessonSession;
use App\Models\User;
use App\Notifications\TeacherPresenceConfirmationNotification;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class SendTeacherPresenceOverlayDemoPush
{
    /**
     * Kirim FCM + inbox sama seperti reminder produksi agar app guru
     * memicu overlay (foreground) untuk demo / review Play Store.
     */
    public function execute(User $teacher, ?int $lessonSessionId = null): LessonSession
    {
        if (! $teacher->hasRole('guru')) {
            throw new InvalidArgumentException('User harus memiliki peran guru.');
        }

        if (! $teacher->deviceTokens()->exists()) {
            throw new InvalidArgumentException(
                'Guru belum punya token FCM. Buka aplikasi Android dan login di perangkat demo.'
            );
        }

        $session = $this->resolveSession($teacher, $lessonSessionId);

        Notification::sendNow($teacher, new TeacherPresenceConfirmationNotification($session));

        return $session;
    }

    private function resolveSession(User $teacher, ?int $lessonSessionId): LessonSession
    {
        if ($lessonSessionId !== null) {
            $session = LessonSession::query()
                ->with(['schedule.subject', 'schedule.schoolClass'])
                ->whereKey($lessonSessionId)
                ->firstOrFail();

            if ((int) $session->schedule?->teacher_id !== (int) $teacher->id) {
                throw new InvalidArgumentException('Sesi jadwal ini bukan milik guru yang dipilih.');
            }

            return $session;
        }

        $session = LessonSession::query()
            ->with(['schedule.subject', 'schedule.schoolClass'])
            ->whereHas('schedule', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->first();

        if (! $session) {
            throw new InvalidArgumentException(
                'Tidak ada sesi jadwal untuk guru ini. Buat jadwal/sesi atau isi ID sesi secara manual.'
            );
        }

        return $session;
    }
}
