<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\LessonAttendance;
use App\Models\LessonSession;
use App\Models\Student;
use App\Notifications\StudentAbsentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class GuruAttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->date ? Carbon::parse($request->date)->toDateString() : now()->toDateString();
        $dayOfWeek = (int) Carbon::parse($date)->isoWeekday();
        $activeSemester = AcademicPeriod::query()->active()->with('semester:id,name')->first()?->semester;

        $schedules = AcademicSchedule::query()
            ->where('day', $dayOfWeek)
            ->where('teacher_id', $user->id)
            ->with([
                'schoolClass:id,name,level',
                'subject:id,name',
            ])
            ->get();

        $sessions = [];
        foreach ($schedules as $schedule) {
            /** @var LessonSession $session */
            $session = LessonSession::firstOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'date' => $date,
                ],
                [
                    'semester_id' => $activeSemester?->id,
                    'start_time' => $schedule->time_start,
                    'end_time' => $schedule->time_end,
                    'status' => 'planned',
                    'created_by' => $user->id,
                ]
            );

            $sessions[] = [
                'id' => $session->id,
                'date' => $session->date->toDateString(),
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'status' => $session->status,
                'class' => [
                    'id' => $schedule->schoolClass->id,
                    'name' => $schedule->schoolClass->name,
                    'level' => $schedule->schoolClass->level,
                ],
                'subject' => [
                    'id' => $schedule->subject->id,
                    'name' => $schedule->subject->name,
                ],
            ];
        }

        return response()->json([
            'date' => $date,
            'semester' => $activeSemester ? $activeSemester->only(['id', 'name']) : null,
            'sessions' => $sessions,
        ]);
    }

    public function students(Request $request, LessonSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSession($session, $user->id);

        $schedule = $session->schedule()->with('schoolClass')->firstOrFail();
        $class = $schedule->schoolClass;

        $students = Student::where('current_class_id', $class->id)
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->get(['id', 'nis', 'full_name']);

        $attendances = LessonAttendance::where('lesson_session_id', $session->id)
            ->get()
            ->keyBy('student_id');

        $data = $students->map(function (Student $student) use ($attendances) {
            $attendance = $attendances->get($student->id);

            return [
                'id' => $student->id,
                'nis' => $student->nis,
                'full_name' => $student->full_name,
                'attendance' => $attendance ? [
                    'id' => $attendance->id,
                    'status' => $attendance->status,
                    'reason' => $attendance->reason,
                ] : null,
            ];
        });

        return response()->json([
            'session' => [
                'id' => $session->id,
                'date' => $session->date->toDateString(),
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'status' => $session->status,
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'level' => $class->level,
                ],
            ],
            'students' => $data,
        ]);
    }

    public function storeAttendance(Request $request, LessonSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSession($session, $user->id);

        $schedule = $session->schedule()->with('schoolClass')->firstOrFail();
        $class = $schedule->schoolClass;

        $validated = $request->validate([
            'attendances' => ['required', 'array'],
            'attendances.*.student_id' => ['required', 'exists:students,id'],
            'attendances.*.status' => ['required', 'in:present,excused,absent'],
            'attendances.*.reason' => ['nullable', 'string', 'max:255'],
            'attendances.*.leave_permission_id' => ['nullable', 'exists:leave_permissions,id'],
        ]);

        $allowedStudentIds = Student::where('current_class_id', $class->id)
            ->where('status', Student::STATUS_ACTIVE)
            ->pluck('id')
            ->all();

        $absentStudentIds = [];
        foreach ($validated['attendances'] as $row) {
            if (! in_array($row['student_id'], $allowedStudentIds, true)) {
                continue;
            }

            LessonAttendance::updateOrCreate(
                [
                    'lesson_session_id' => $session->id,
                    'student_id' => $row['student_id'],
                ],
                [
                    'status' => $row['status'],
                    'reason' => $row['reason'] ?? null,
                    'leave_permission_id' => $row['leave_permission_id'] ?? null,
                    'marked_by' => $user->id,
                    'marked_at' => now(),
                ]
            );

            if ($row['status'] === 'absent') {
                $absentStudentIds[] = $row['student_id'];
            }
        }

        if (! empty($absentStudentIds)) {
            $this->notifyWaliOfAbsence($session, $schedule, $absentStudentIds);
        }

        return response()->json([
            'message' => 'Kehadiran santri berhasil disimpan.',
        ]);
    }

    /**
     * @param  array<int, int>  $absentStudentIds
     */
    private function notifyWaliOfAbsence(LessonSession $session, AcademicSchedule $schedule, array $absentStudentIds): void
    {
        try {
            $subject = $schedule->subject()->first(['id', 'name']);
            $class = $schedule->schoolClass()->first(['id', 'name', 'grade_level_id']);

            $students = Student::query()
                ->whereIn('id', $absentStudentIds)
                ->with(['guardians.user'])
                ->get();

            foreach ($students as $student) {
                $waliUsers = $student->guardians
                    ->pluck('user')
                    ->filter()
                    ->unique('id')
                    ->values();

                if ($waliUsers->isEmpty()) {
                    continue;
                }

                Notification::send(
                    $waliUsers,
                    new StudentAbsentNotification(
                        student: $student,
                        session: $session,
                        subjectName: $subject?->name,
                        className: $class?->name,
                    )
                );
            }
        } catch (\Throwable $e) {

            Log::warning('Failed to send StudentAbsentNotification', [
                'error' => $e->getMessage(),
                'session_id' => $session->id,
                'student_ids' => $absentStudentIds,
            ]);
        }
    }

    public function showAttendance(Request $request, LessonSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSession($session, $user->id);

        $schedule = $session->schedule()->with(['schoolClass', 'subject'])->firstOrFail();

        $attendances = LessonAttendance::where('lesson_session_id', $session->id)
            ->with('student:id,nis,full_name')
            ->get();

        return response()->json([
            'session' => [
                'id' => $session->id,
                'date' => $session->date->toDateString(),
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'status' => $session->status,
                'class' => [
                    'id' => $schedule->schoolClass->id,
                    'name' => $schedule->schoolClass->name,
                    'level' => $schedule->schoolClass->level,
                ],
                'subject' => [
                    'id' => $schedule->subject->id,
                    'name' => $schedule->subject->name,
                ],
            ],
            'attendances' => $attendances->map(function (LessonAttendance $attendance) {
                return [
                    'id' => $attendance->id,
                    'student' => [
                        'id' => $attendance->student->id,
                        'nis' => $attendance->student->nis,
                        'full_name' => $attendance->student->full_name,
                    ],
                    'status' => $attendance->status,
                    'reason' => $attendance->reason,
                    'marked_at' => $attendance->marked_at,
                ];
            }),
        ]);
    }

    private function authorizeSession(LessonSession $session, int $userId): void
    {
        $session->loadMissing('schedule');
        $schedule = $session->schedule;

        if (! $schedule || $schedule->teacher_id !== $userId) {
            abort(403, 'Anda tidak berhak mengelola sesi ini.');
        }
    }
}
