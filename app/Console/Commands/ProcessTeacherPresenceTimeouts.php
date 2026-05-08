<?php

namespace App\Console\Commands;

use App\Models\LessonSession;
use App\Notifications\TeacherPresenceConfirmationNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class ProcessTeacherPresenceTimeouts extends Command
{
    protected $signature = 'teacher-presence:process-timeouts {--date= : Override now reference (Y-m-d H:i:s)}';

    protected $description = 'Proses sesi terlewat: set pending konfirmasi guru, kirim reminder, lalu auto alpa saat deadline.';

    public function handle(): int
    {
        $now = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))
            : now();

        /** @var \Illuminate\Database\Eloquent\Collection<int, LessonSession> $sessions */
        $sessions = LessonSession::query()
            ->with(['schedule.teacher:id,name', 'schedule.schoolClass:id,name', 'schedule.subject:id,name'])
            ->whereDate('date', '<=', $now->toDateString())
            ->where(function ($q) {
                $q->whereNull('teacher_presence_status')
                    ->orWhere('teacher_presence_status', LessonSession::TEACHER_PRESENCE_PENDING);
            })
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $movedToPending = 0;
        $autoAbsent = 0;
        $notified = 0;

        foreach ($sessions as $session) {
            /** @var LessonSession $session */
            $cutoff = $this->resolveCutoffAt($session);
            $deadline = $this->resolveDeadlineAt($session, $cutoff);

            if ($session->teacher_presence_status === null && $now->gte($cutoff)) {
                $session->forceFill([
                    'teacher_presence_status' => LessonSession::TEACHER_PRESENCE_PENDING,
                    'teacher_presence_source' => 'system_timeout',
                    'teacher_presence_deadline_at' => $deadline,
                ])->save();
                $movedToPending++;

                $teacher = $session->schedule?->teacher;
                if ($teacher) {
                    Notification::sendNow($teacher, new TeacherPresenceConfirmationNotification($session));
                    $notified++;
                }
            }

            if ($session->teacher_presence_status === LessonSession::TEACHER_PRESENCE_PENDING && $now->gte($deadline)) {
                $session->forceFill([
                    'teacher_presence_status' => LessonSession::TEACHER_PRESENCE_ABSENT,
                    'teacher_presence_source' => 'system_timeout',
                    'teacher_presence_reason' => $session->teacher_presence_reason ?: 'Auto alpa: tidak ada konfirmasi sampai deadline.',
                    'teacher_presence_confirmed_at' => $now,
                    'teacher_presence_deadline_at' => $deadline,
                ])->save();
                $autoAbsent++;
            }
        }

        $this->info("Teacher presence processed: pending={$movedToPending}, notified={$notified}, auto_absent={$autoAbsent}");

        return self::SUCCESS;
    }

    private function resolveCutoffAt(LessonSession $session): Carbon
    {
        $grace = (int) config('teacher_presence.cutoff_grace_minutes', 20);

        return Carbon::parse($session->date->toDateString().' '.$session->end_time)->addMinutes($grace);
    }

    private function resolveDeadlineAt(LessonSession $session, Carbon $cutoff): Carbon
    {
        if ($session->teacher_presence_deadline_at) {
            return Carbon::parse($session->teacher_presence_deadline_at);
        }

        $window = (int) config('teacher_presence.confirm_window_minutes', 180);

        return $cutoff->copy()->addMinutes($window);
    }
}

