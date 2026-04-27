<?php

namespace App\Console\Commands;

use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ScheduleTomorrowNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class SendTomorrowScheduleReminders extends Command
{
    protected $signature = 'schedule:remind-tomorrow {--date= : Override target date (YYYY-MM-DD). Defaults to tomorrow.}';

    protected $description = 'Kirim notifikasi FCM + database berisi ringkasan jadwal besok untuk Guru, Santri, dan Wali.';

    public function handle(): int
    {
        $target = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now()->addDay();

        $dayOfWeek = (int) $target->isoWeekday();
        $dayName = $this->dayName($dayOfWeek);
        $dateStr = $target->toDateString();

        $this->info("Mengirim pengingat untuk {$dayName} ({$dateStr}).");

        $schedules = AcademicSchedule::query()
            ->where('day', $dayOfWeek)
            ->with([
                'schoolClass:id,name,level',
                'subject:id,name',
                'teacher:id,name',
            ])
            ->orderBy('time_start')
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('Tidak ada jadwal untuk hari tersebut.');

            return self::SUCCESS;
        }

        $byTeacher = $schedules->groupBy('teacher_id');
        $byClass = $schedules->groupBy('class_id');

        $guruSent = $this->sendToTeachers($byTeacher, $dayName);
        $santriSent = $this->sendToSantri($byClass, $dayName);
        $waliSent = $this->sendToWali($byClass, $dayName);

        $this->info("Guru: {$guruSent}, Santri: {$santriSent}, Wali: {$waliSent} notifikasi dikirim.");

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, AcademicSchedule>>  $byTeacher
     */
    private function sendToTeachers($byTeacher, string $dayName): int
    {
        $teacherIds = $byTeacher->keys()->filter()->all();
        if (empty($teacherIds)) {
            return 0;
        }

        $teachers = User::whereIn('id', $teacherIds)->get();
        $count = 0;

        foreach ($teachers as $teacher) {
            $entries = $byTeacher->get($teacher->id, collect())
                ->map(fn (AcademicSchedule $s) => $this->entry($s))
                ->values()
                ->all();

            Notification::send($teacher, new ScheduleTomorrowNotification(
                role: 'guru',
                entries: $entries,
                dayName: $dayName,
                deeplink: '/guru/schedule',
            ));
            $count++;
        }

        return $count;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, AcademicSchedule>>  $byClass
     */
    private function sendToSantri($byClass, string $dayName): int
    {
        $classIds = $byClass->keys()->filter()->all();
        if (empty($classIds)) {
            return 0;
        }

        $students = Student::query()
            ->whereIn('current_class_id', $classIds)
            ->where('status', Student::STATUS_ACTIVE)
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        $count = 0;
        foreach ($students as $student) {
            $user = $student->user;
            if (! $user) {
                continue;
            }

            $entries = $byClass->get($student->current_class_id, collect())
                ->map(fn (AcademicSchedule $s) => $this->entry($s))
                ->values()
                ->all();

            Notification::send($user, new ScheduleTomorrowNotification(
                role: 'santri',
                entries: $entries,
                dayName: $dayName,
                deeplink: '/santri/schedule',
            ));
            $count++;
        }

        return $count;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, AcademicSchedule>>  $byClass
     */
    private function sendToWali($byClass, string $dayName): int
    {
        $classIds = $byClass->keys()->filter()->all();
        if (empty($classIds)) {
            return 0;
        }

        $studentIds = Student::query()
            ->whereIn('current_class_id', $classIds)
            ->where('status', Student::STATUS_ACTIVE)
            ->pluck('id');

        if ($studentIds->isEmpty()) {
            return 0;
        }

        $guardians = Guardian::query()
            ->whereNotNull('user_id')
            ->whereHas('students', fn ($q) => $q->whereIn('students.id', $studentIds))
            ->with(['user', 'students' => fn ($q) => $q->whereIn('students.id', $studentIds)])
            ->get();

        $count = 0;
        foreach ($guardians as $guardian) {
            $user = $guardian->user;
            if (! $user) {
                continue;
            }

            $firstChild = $guardian->students->first();
            $deeplink = $firstChild
                ? '/wali/children/'.$firstChild->id.'/schedule'
                : '/wali/children';

            $entries = [];
            foreach ($guardian->students as $child) {
                $childEntries = $byClass->get($child->current_class_id, collect())
                    ->map(function (AcademicSchedule $s) use ($child) {
                        $entry = $this->entry($s);
                        $entry['student_id'] = $child->id;
                        $entry['student_name'] = $child->full_name;

                        return $entry;
                    })
                    ->values()
                    ->all();
                $entries = array_merge($entries, $childEntries);
            }

            Notification::send($user, new ScheduleTomorrowNotification(
                role: 'wali',
                entries: $entries,
                dayName: $dayName,
                deeplink: $deeplink,
            ));
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(AcademicSchedule $schedule): array
    {
        return [
            'schedule_id' => $schedule->id,
            'class_id' => $schedule->class_id,
            'class_name' => $schedule->schoolClass?->name,
            'subject_id' => $schedule->subject_id,
            'subject_name' => $schedule->subject?->name,
            'teacher_id' => $schedule->teacher_id,
            'teacher_name' => $schedule->teacher?->name,
            'start_time' => $schedule->time_start,
            'end_time' => $schedule->time_end,
        ];
    }

    private function dayName(int $day): string
    {
        return match ($day) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Ahad',
            default => 'Hari',
        };
    }
}
