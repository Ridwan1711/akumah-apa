<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLessonAttendanceRequest;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\LessonAttendance;
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
                'lessonSession.schedule.schoolClass:id,name,level',
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

    public function weeklyRecap(Request $request): JsonResponse
    {
        $anchor = $request->filled('week_anchor_date')
            ? CarbonImmutable::parse((string) $request->input('week_anchor_date'))
            : CarbonImmutable::now();
        $weekStart = $this->resolveTeachingWeekStart($anchor);
        $weekEnd = $weekStart->addDays(5);

        $query = LessonAttendance::query()
            ->with([
                'lessonSession.schedule.schoolClass:id,name,level',
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
}
