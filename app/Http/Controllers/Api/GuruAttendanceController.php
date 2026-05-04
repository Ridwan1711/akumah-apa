<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\LessonAttendance;
use App\Models\LessonSession;
use App\Models\Role;
use App\Models\Student;
use App\Models\TeacherAttendance;
use App\Notifications\StudentAbsentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

class GuruAttendanceController extends Controller
{
    public function teacherAttendanceToday(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $attendance = TeacherAttendance::query()
            ->where('teacher_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        return response()->json([
            'date' => $today,
            'attendance' => $attendance ? [
                'id' => $attendance->id,
                'teacher_id' => $attendance->teacher_id,
                'status' => $attendance->status,
                'check_in_at' => $attendance->check_in_at,
                'check_out_at' => $attendance->check_out_at,
                'notes' => $attendance->notes,
            ] : null,
        ]);
    }

    public function teacherCheckIn(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        /** @var TeacherAttendance $attendance */
        $attendance = TeacherAttendance::query()->firstOrCreate(
            [
                'teacher_id' => $user->id,
                'date' => $today,
            ],
            [
                'status' => 'present',
                'check_in_at' => now(),
            ]
        );

        if (! $attendance->check_in_at) {
            $attendance->check_in_at = now();
            $attendance->save();
        }

        return response()->json([
            'message' => 'Check-in guru berhasil.',
            'attendance' => [
                'id' => $attendance->id,
                'teacher_id' => $attendance->teacher_id,
                'status' => $attendance->status,
                'check_in_at' => $attendance->check_in_at,
                'check_out_at' => $attendance->check_out_at,
                'notes' => $attendance->notes,
            ],
        ]);
    }

    public function teacherCheckOut(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        /** @var TeacherAttendance|null $attendance */
        $attendance = TeacherAttendance::query()
            ->where('teacher_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (! $attendance) {
            return response()->json([
                'message' => 'Anda belum melakukan check-in hari ini.',
            ], 422);
        }

        if (! $attendance->check_in_at) {
            $attendance->check_in_at = now();
        }
        $attendance->check_out_at = now();
        $attendance->save();

        return response()->json([
            'message' => 'Check-out guru berhasil.',
            'attendance' => [
                'id' => $attendance->id,
                'teacher_id' => $attendance->teacher_id,
                'status' => $attendance->status,
                'check_in_at' => $attendance->check_in_at,
                'check_out_at' => $attendance->check_out_at,
                'notes' => $attendance->notes,
            ],
        ]);
    }

    public function recap(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdminScope = $user->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK]);

        $validator = Validator::make($request->all(), [
            'month' => ['nullable', 'date_format:Y-m'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
        ]);
        $validator->validate();

        if ($request->filled('month')) {
            $startDate = Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth()->toDateString();
            $endDate = Carbon::createFromFormat('Y-m', $request->string('month'))->endOfMonth()->toDateString();
        } else {
            $startDate = $request->date_from
                ? Carbon::parse($request->date_from)->toDateString()
                : now()->startOfMonth()->toDateString();
            $endDate = $request->date_to
                ? Carbon::parse($request->date_to)->toDateString()
                : now()->endOfMonth()->toDateString();
        }

        $teacherAttendanceQuery = TeacherAttendance::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->with('teacher:id,name');

        if (! $isAdminScope) {
            $teacherAttendanceQuery->where('teacher_id', $user->id);
        } elseif ($request->filled('teacher_id')) {
            $teacherAttendanceQuery->where('teacher_id', (int) $request->teacher_id);
        }

        $teacherRows = $teacherAttendanceQuery
            ->orderBy('date')
            ->get();

        $teacherSummary = $teacherRows
            ->groupBy('teacher_id')
            ->map(function ($items) {
                $first = $items->first();
                $presentCount = $items->where('status', 'present')->count();
                $lateCount = $items->where('status', 'late')->count();
                $excusedCount = $items->where('status', 'excused')->count();
                $absentCount = $items->where('status', 'absent')->count();

                return [
                    'teacher_id' => $first?->teacher_id,
                    'teacher_name' => $first?->teacher?->name,
                    'days_recorded' => $items->count(),
                    'present_count' => $presentCount,
                    'late_count' => $lateCount,
                    'excused_count' => $excusedCount,
                    'absent_count' => $absentCount,
                    'completion_rate' => $items->count() > 0
                        ? round((($presentCount + $lateCount) / $items->count()) * 100, 2)
                        : 0,
                ];
            })
            ->values();

        $studentByClassSummary = collect();
        if ($isAdminScope) {
            $studentByClassQuery = LessonAttendance::query()
                ->join('lesson_sessions', 'lesson_sessions.id', '=', 'lesson_attendances.lesson_session_id')
                ->join('schedules', 'schedules.id', '=', 'lesson_sessions.schedule_id')
                ->join('classes', 'classes.id', '=', 'schedules.class_id')
                ->whereBetween('lesson_sessions.date', [$startDate, $endDate])
                ->selectRaw(
                    'classes.id as class_id, classes.name as class_name, lesson_attendances.status, COUNT(*) as total'
                )
                ->groupBy('classes.id', 'classes.name', 'lesson_attendances.status');

            if ($request->filled('class_id')) {
                $studentByClassQuery->where('classes.id', (int) $request->class_id);
            }

            $studentByClassSummary = $studentByClassQuery
                ->get()
                ->groupBy('class_id')
                ->map(function ($rows) {
                    $first = $rows->first();
                    $presentCount = (int) ($rows->firstWhere('status', 'present')->total ?? 0);
                    $excusedCount = (int) ($rows->firstWhere('status', 'excused')->total ?? 0);
                    $absentCount = (int) ($rows->firstWhere('status', 'absent')->total ?? 0);
                    $total = $presentCount + $excusedCount + $absentCount;

                    return [
                        'class_id' => (int) $first->class_id,
                        'class_name' => (string) $first->class_name,
                        'present_count' => $presentCount,
                        'excused_count' => $excusedCount,
                        'absent_count' => $absentCount,
                        'total_records' => $total,
                        'present_rate' => $total > 0 ? round(($presentCount / $total) * 100, 2) : 0,
                    ];
                })
                ->values();
        }

        return response()->json([
            'scope' => $isAdminScope ? 'admin' : 'guru',
            'period' => [
                'date_from' => $startDate,
                'date_to' => $endDate,
            ],
            'teacher_attendance' => [
                'summary' => $teacherSummary,
                'records' => $teacherRows->map(function (TeacherAttendance $attendance) {
                    return [
                        'id' => $attendance->id,
                        'date' => $attendance->date->toDateString(),
                        'teacher_id' => $attendance->teacher_id,
                        'teacher_name' => $attendance->teacher?->name,
                        'status' => $attendance->status,
                        'check_in_at' => $attendance->check_in_at,
                        'check_out_at' => $attendance->check_out_at,
                        'notes' => $attendance->notes,
                    ];
                })->values(),
            ],
            'student_attendance_by_class' => $studentByClassSummary,
        ]);
    }

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
                'schoolClass:id,name,grade_level_id',
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

            $attendanceFilled = $session->attendances()->exists();

            $sessions[] = [
                'id' => $session->id,
                'date' => $session->date->toDateString(),
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'status' => $session->status,
                'attendance_filled' => $attendanceFilled,
                'class' => [
                    'id' => $schedule->schoolClass->id,
                    'name' => $schedule->schoolClass->name,
                    'grade_level_id' => $schedule->schoolClass->grade_level_id,
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
                    'grade_level_id' => $class->grade_level_id,
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

        if ($session->status !== 'completed') {
            $session->status = 'completed';
            $session->save();
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
                    'grade_level_id' => $schedule->schoolClass->grade_level_id,
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
