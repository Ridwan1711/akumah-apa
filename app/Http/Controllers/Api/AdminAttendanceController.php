<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLessonAttendanceRequest;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\LessonAttendance;
use App\Models\LessonSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LessonAttendance::query()
            ->with([
                'lessonSession.schedule.schoolClass:id,name,grade_level_id',
                'lessonSession.schedule.subject:id,name',
                'student:id,nis,full_name',
            ])
            ->orderByDesc('lesson_session_id')
            ->orderBy('student_id');

        if ($request->filled('class_id')) {
            $classId = (int) $request->class_id;
            $query->whereHas('lessonSession.schedule', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', (int) $request->student_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereHas('lessonSession', function ($q) use ($request) {
                $q->where('date', '>=', $request->date_from);
            });
        }

        if ($request->filled('date_to')) {
            $query->whereHas('lessonSession', function ($q) use ($request) {
                $q->where('date', '<=', $request->date_to);
            });
        }

        $attendances = $query->paginate(50);

        return response()->json($attendances);
    }

    public function update(UpdateLessonAttendanceRequest $request, LessonAttendance $attendance): JsonResponse
    {
        $attendance->update($request->validated() + [
            'marked_at' => now(),
        ]);

        return response()->json([
            'message' => 'Kehadiran berhasil diperbarui.',
            'attendance' => $attendance->fresh(),
        ]);
    }

    public function teacherRecap(Request $request): JsonResponse
    {
        $period = (string) $request->input('period', 'weekly');
        $anchor = $request->filled('date')
            ? CarbonImmutable::parse((string) $request->input('date'))
            : CarbonImmutable::now();

        $rangeStart = $period === 'daily'
            ? $anchor->startOfDay()
            : $this->resolveTeachingWeekStart($anchor);
        $rangeEnd = $period === 'daily'
            ? $anchor->endOfDay()
            : $rangeStart->addDays(5)->endOfDay();

        $query = LessonSession::query()
            ->with([
                'schedule.schoolClass:id,name,grade_level_id',
                'schedule.subject:id,name',
                'schedule.teacher:id,name',
            ])
            ->whereDate('date', '>=', $rangeStart->toDateString())
            ->whereDate('date', '<=', $rangeEnd->toDateString())
            ->whereHas('schedule', fn ($q) => $q->whereIn('day', AcademicSchedule::TEACHING_DAYS));

        if ($request->filled('teacher_id')) {
            $teacherId = (int) $request->input('teacher_id');
            $query->whereHas('schedule', fn ($q) => $q->where('teacher_id', $teacherId));
        }

        /** @var Collection<int, LessonSession> $sessions */
        $sessions = $query->orderBy('date')->orderBy('start_time')->get();

        $grouped = $sessions->groupBy(fn (LessonSession $s) => (int) ($s->schedule?->teacher_id ?? 0));

        $teachers = $grouped->map(function (Collection $teacherSessions, int $teacherId) {
            /** @var LessonSession|null $first */
            $first = $teacherSessions->first();
            $teacherName = (string) ($first?->schedule?->teacher?->name ?? '-');

            $hadirTotal = 0;
            $alpaTotal = 0;
            $izinSakitTotal = 0;
            $pendingTotal = 0;
            $alpaDetails = [];
            $izinSakitDetails = [];

            foreach ($teacherSessions as $session) {
                $status = $session->teacher_presence_status
                    ?: ($session->status === LessonSession::STATUS_COMPLETED
                        ? LessonSession::TEACHER_PRESENCE_PRESENT
                        : LessonSession::TEACHER_PRESENCE_PENDING);
                $detail = $this->teacherSessionDetail($session, $status);

                if ($status === LessonSession::TEACHER_PRESENCE_PRESENT) {
                    $hadirTotal++;
                    continue;
                }

                if ($status === LessonSession::TEACHER_PRESENCE_ABSENT) {
                    $alpaTotal++;
                    $alpaDetails[] = $detail;
                    continue;
                }

                if (in_array($status, [LessonSession::TEACHER_PRESENCE_EXCUSED, LessonSession::TEACHER_PRESENCE_SICK], true)) {
                    $izinSakitTotal++;
                    $izinSakitDetails[] = $detail;
                    continue;
                }

                $pendingTotal++;
            }

            return [
                'teacher_id' => $teacherId,
                'teacher_name' => $teacherName,
                'hadir_total' => $hadirTotal,
                'alpa_total' => $alpaTotal,
                'izin_sakit_total' => $izinSakitTotal,
                'pending_total' => $pendingTotal,
                'total_jam' => $teacherSessions->count(),
                'alpa_details' => $alpaDetails,
                'izin_sakit_details' => $izinSakitDetails,
            ];
        })->sortBy('teacher_name')->values();

        return response()->json([
            'period' => $period,
            'date_anchor' => $anchor->toDateString(),
            'period_start' => $rangeStart->toDateString(),
            'period_end' => $rangeEnd->toDateString(),
            'teachers' => $teachers,
            'meta' => [
                'teacher_count' => $teachers->count(),
                'total_sessions' => $sessions->count(),
            ],
        ]);
    }

    public function teacherSessionOverride(Request $request, LessonSession $session): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:present,excused,sick,absent,pending'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $session->forceFill([
            'teacher_presence_status' => $validated['status'],
            'teacher_presence_reason' => $validated['reason'] ?? null,
            'teacher_presence_source' => 'admin_override',
            'teacher_presence_confirmed_at' => now(),
            'teacher_presence_deadline_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Status kehadiran guru berhasil di-override.',
            'session_id' => $session->id,
            'teacher_presence_status' => $session->teacher_presence_status,
        ]);
    }

    public function weeklyRecap(Request $request): JsonResponse
    {
        $anchor = $request->filled('week_anchor_date')
            ? CarbonImmutable::parse((string) $request->input('week_anchor_date'))
            : CarbonImmutable::now();
        $weekStart = $this->resolveTeachingWeekStart($anchor);
        $weekEnd = $weekStart->addDays(5);

        $query = LessonAttendance::query()
            ->with([
                'lessonSession.schedule.schoolClass:id,name,grade_level_id',
                'lessonSession.schedule.subject:id,name',
                'lessonSession.schedule.teacher:id,name',
                'student:id,nis,full_name,current_class_id',
            ])
            ->whereHas('lessonSession', function ($q) use ($weekStart, $weekEnd) {
                $q->whereDate('date', '>=', $weekStart->toDateString())
                    ->whereDate('date', '<=', $weekEnd->toDateString());
            })
            ->whereHas('lessonSession.schedule', function ($q) {
                $q->whereIn('day', AcademicSchedule::TEACHING_DAYS);
            });

        if ($request->filled('class_id')) {
            $classId = (int) $request->input('class_id');
            $query->whereHas('lessonSession.schedule', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }

        if ($request->filled('teacher_id')) {
            $teacherId = (int) $request->input('teacher_id');
            $query->whereHas('lessonSession.schedule', function ($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId);
            });
        }

        /** @var Collection<int, LessonAttendance> $rows */
        $rows = $query->get();

        $grouped = $rows->groupBy(function (LessonAttendance $attendance) {
            $schedule = $attendance->lessonSession?->schedule;
            $classId = (int) ($schedule?->class_id ?? 0);
            $subjectId = (int) ($schedule?->subject_id ?? 0);
            $teacherId = (int) ($schedule?->teacher_id ?? 0);

            return $classId.'|'.$subjectId.'|'.$teacherId;
        });

        $summaries = $grouped->map(function (Collection $items) {
            /** @var LessonAttendance|null $first */
            $first = $items->first();
            $schedule = $first?->lessonSession?->schedule;
            $class = $schedule?->schoolClass;
            $subject = $schedule?->subject;
            $teacher = $schedule?->teacher;

            $excusedRows = $items->filter(fn (LessonAttendance $row) => $row->status === 'excused');
            $absentRows = $items->filter(fn (LessonAttendance $row) => $row->status === 'absent');

            return [
                'class_id' => (int) ($class?->id ?? 0),
                'class_name' => (string) ($class?->name ?? '-'),
                'subject_id' => (int) ($subject?->id ?? 0),
                'subject_name' => (string) ($subject?->name ?? '-'),
                'teacher_id' => (int) ($teacher?->id ?? 0),
                'teacher_name' => (string) ($teacher?->name ?? '-'),
                'hadir_total' => $items->where('status', 'present')->count(),
                'izin_sakit_total' => $excusedRows->count(),
                'alpa_total' => $absentRows->count(),
                'izin_sakit_students' => $this->mapStudentsForRecap($excusedRows),
                'alpa_students' => $this->mapStudentsForRecap($absentRows),
            ];
        })->sortBy([
            ['class_name', 'asc'],
            ['subject_name', 'asc'],
            ['teacher_name', 'asc'],
        ])->values();

        $guruAbsentOrExcused = $rows
            ->filter(fn (LessonAttendance $row) => in_array($row->status, ['excused', 'absent'], true))
            ->map(fn (LessonAttendance $row) => (string) ($row->lessonSession?->schedule?->teacher?->name ?? ''))
            ->filter(fn (string $name) => $name !== '')
            ->unique()
            ->values();

        return response()->json([
            'week_anchor_date' => $anchor->toDateString(),
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'summary_groups' => $summaries,
            'guru_absent_or_excused' => $guruAbsentOrExcused,
            'meta' => [
                'total_groups' => $summaries->count(),
                'total_attendance_rows' => $rows->count(),
            ],
        ]);
    }

    protected function resolveTeachingWeekStart(CarbonImmutable $anchor): CarbonImmutable
    {
        $daysSinceSaturday = ($anchor->dayOfWeekIso - AcademicSchedule::DAY_SATURDAY + 7) % 7;

        return $anchor->subDays($daysSinceSaturday)->startOfDay();
    }

    /**
     * @param  Collection<int, LessonAttendance>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function mapStudentsForRecap(Collection $rows): array
    {
        return $rows
            ->map(function (LessonAttendance $row) {
                return [
                    'student_id' => (int) ($row->student?->id ?? 0),
                    'student_name' => (string) ($row->student?->full_name ?? '-'),
                    'nis' => (string) ($row->student?->nis ?? ''),
                    'class_name' => (string) ($row->lessonSession?->schedule?->schoolClass?->name ?? '-'),
                ];
            })
            ->unique('student_id')
            ->sortBy('student_name')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function teacherSessionDetail(LessonSession $session, string $status): array
    {
        $schedule = $session->schedule;
        $day = (int) ($schedule?->day ?? CarbonImmutable::parse($session->date)->dayOfWeekIso);

        return [
            'session_id' => $session->id,
            'date' => $session->date?->toDateString(),
            'day_of_week' => $day,
            'day_name' => $this->dayName($day),
            'jam_no' => $schedule?->jam_no,
            'class_id' => $schedule?->class_id,
            'class_name' => $schedule?->schoolClass?->name,
            'subject_id' => $schedule?->subject_id,
            'subject_name' => $schedule?->subject?->name,
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'status' => $status,
            'reason' => $session->teacher_presence_reason,
            'source' => $session->teacher_presence_source,
        ];
    }

    protected function dayName(int $day): string
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
