<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\LessonAttendance;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\TeacherLocationLog;
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
                'schoolClass:id,name,grade_level_id',
                'schoolClass.gradeLevel:id,name',
                'subject:id,name',
            ])
            ->get();

        $sessionRows = [];
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

            $sessionRows[] = [
                'session' => $session,
                'schedule' => $schedule,
            ];
        }

        $classIds = $schedules->pluck('class_id')->unique()->filter()->values()->all();
        $studentsTotalByClass = [];
        if ($classIds !== []) {
            $studentsTotalByClass = Student::query()
                ->whereIn('current_class_id', $classIds)
                ->where('status', Student::STATUS_ACTIVE)
                ->selectRaw('current_class_id, count(*) as c')
                ->groupBy('current_class_id')
                ->pluck('c', 'current_class_id')
                ->map(fn ($c) => (int) $c)
                ->all();
        }

        $sessionIds = collect($sessionRows)->map(fn (array $r) => $r['session']->id)->all();
        $attendanceCountsBySession = [];
        $sessionIdsHavingAttendance = [];
        if ($sessionIds !== []) {
            $agg = LessonAttendance::query()
                ->whereIn('lesson_session_id', $sessionIds)
                ->selectRaw('lesson_session_id, status, count(*) as cnt')
                ->groupBy('lesson_session_id', 'status')
                ->get();
            foreach ($agg as $row) {
                $sid = (int) $row->lesson_session_id;
                $attendanceCountsBySession[$sid][(string) $row->status] = (int) $row->cnt;
                $sessionIdsHavingAttendance[$sid] = true;
            }
        }

        $sessions = [];
        foreach ($sessionRows as $row) {
            /** @var LessonSession $session */
            $session = $row['session'];
            /** @var AcademicSchedule $schedule */
            $schedule = $row['schedule'];
            $class = $schedule->schoolClass;

            $attendanceFilled = isset($sessionIdsHavingAttendance[$session->id]);
            $byStatus = $attendanceCountsBySession[$session->id] ?? [];
            $present = (int) ($byStatus['present'] ?? 0);
            $excused = (int) ($byStatus['excused'] ?? 0);
            $absent = (int) ($byStatus['absent'] ?? 0);
            $studentsTotal = (int) ($studentsTotalByClass[$class->id] ?? 0);
            $attendancePercent = $studentsTotal > 0
                ? (int) round(($present / $studentsTotal) * 100)
                : null;

            $gradeLevel = $class->gradeLevel;

            $sessions[] = [
                'id' => $session->id,
                'date' => $session->date->toDateString(),
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'status' => $session->status,
                'attendance_filled' => $attendanceFilled,
                'teacher_name' => $user->name,
                'students_total' => $studentsTotal,
                'attendance_counts' => [
                    'present' => $present,
                    'excused' => $excused,
                    'absent' => $absent,
                ],
                'attendance_percent' => $attendancePercent,
                'teacher_presence_status' => $this->normalizeTeacherPresenceStatus($session),
                'teacher_presence_reason' => $session->teacher_presence_reason,
                'teacher_presence_deadline_at' => $session->teacher_presence_deadline_at?->toIso8601String(),
                'teacher_presence_cutoff_at' => $this->resolveSessionCutoffAt($session)->toIso8601String(),
                'teacher_presence_cutoff_passed' => now()->gte($this->resolveSessionCutoffAt($session)),
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'grade_level_id' => $class->grade_level_id,
                ],
                'grade_level' => $gradeLevel ? $gradeLevel->only(['id', 'name']) : null,
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

        $schedule = $session->schedule()->with([
            'schoolClass.gradeLevel:id,name',
            'subject:id,name',
            'teacher:id,name',
        ])->firstOrFail();
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

        $byStatus = LessonAttendance::query()
            ->where('lesson_session_id', $session->id)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->map(fn ($c) => (int) $c)
            ->all();

        $studentsTotal = Student::query()
            ->where('current_class_id', $class->id)
            ->where('status', Student::STATUS_ACTIVE)
            ->count();

        $present = (int) ($byStatus['present'] ?? 0);
        $excused = (int) ($byStatus['excused'] ?? 0);
        $absent = (int) ($byStatus['absent'] ?? 0);
        $attendancePercent = $studentsTotal > 0
            ? (int) round(($present / $studentsTotal) * 100)
            : null;

        return response()->json([
            'session' => [
                'id' => $session->id,
                'date' => $session->date->toDateString(),
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'status' => $session->status,
                'notes' => $session->notes,
                'teacher_presence_status' => $this->normalizeTeacherPresenceStatus($session),
                'teacher_presence_reason' => $session->teacher_presence_reason,
                'teacher_presence_deadline_at' => $session->teacher_presence_deadline_at?->toIso8601String(),
                'teacher_presence_cutoff_at' => $this->resolveSessionCutoffAt($session)->toIso8601String(),
                'teacher_presence_cutoff_passed' => now()->gte($this->resolveSessionCutoffAt($session)),
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'grade_level_id' => $class->grade_level_id,
                ],
                'grade_level' => $class->gradeLevel ? $class->gradeLevel->only(['id', 'name']) : null,
                'subject' => [
                    'id' => $schedule->subject->id,
                    'name' => $schedule->subject->name,
                ],
                'teacher_name' => $schedule->teacher?->name ?? '',
                'students_total' => $studentsTotal,
                'attendance_counts' => [
                    'present' => $present,
                    'excused' => $excused,
                    'absent' => $absent,
                ],
                'attendance_percent' => $attendancePercent,
            ],
            'students' => $data,
        ]);
    }

    public function storeAttendance(Request $request, LessonSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSession($session, $user->id);

        $cutoffAt = $this->resolveSessionCutoffAt($session);
        if (now()->gt($cutoffAt)) {
            return response()->json([
                'message' => 'Waktu input absensi untuk sesi ini sudah habis. Mohon konfirmasi status kehadiran mengajar Anda.',
                'code' => 'teacher_presence_cutoff_passed',
                'cutoff_at' => $cutoffAt->toIso8601String(),
            ], 422);
        }

        $schedule = $session->schedule()->with('schoolClass')->firstOrFail();
        $class = $schedule->schoolClass;

        $validated = $request->validate([
            'attendances' => ['required', 'array'],
            'attendances.*.student_id' => ['required', 'exists:students,id'],
            'attendances.*.status' => ['required', 'in:present,excused,absent'],
            'attendances.*.reason' => ['nullable', 'string', 'max:255'],
            'attendances.*.leave_permission_id' => ['nullable', 'exists:leave_permissions,id'],
            'notes' => ['nullable', 'string', 'max:255'],
            'draft' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array'],
            'meta.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'meta.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'meta.accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'meta.device_recorded_at' => ['nullable', 'date'],
            'meta.is_location_enabled' => ['nullable', 'boolean'],
            'meta.source' => ['nullable', 'in:foreground,background,last_known'],
            'meta.note' => ['nullable', 'string', 'max:255'],
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

        $draft = (bool) ($validated['draft'] ?? false);
        if (array_key_exists('notes', $validated)) {
            $session->notes = $validated['notes'];
        }

        if (! $draft && $session->status !== 'completed') {
            $session->status = 'completed';
        }

        if (! in_array($session->teacher_presence_status, [
            LessonSession::TEACHER_PRESENCE_EXCUSED,
            LessonSession::TEACHER_PRESENCE_SICK,
            LessonSession::TEACHER_PRESENCE_ABSENT,
        ], true)) {
            $session->teacher_presence_status = LessonSession::TEACHER_PRESENCE_PRESENT;
            $session->teacher_presence_source = 'auto_student_attendance';
            $session->teacher_presence_reason = null;
            $session->teacher_presence_confirmed_at = now();
            $session->teacher_presence_deadline_at = null;
        }

        $session->save();

        $geoWarnings = $this->buildGeoWarnings($session, $validated['meta'] ?? null);

        if (! empty($validated['meta']) && array_key_exists('latitude', $validated['meta']) && array_key_exists('longitude', $validated['meta'])) {
            TeacherLocationLog::query()->create([
                'teacher_id' => $user->id,
                'recorded_at' => isset($validated['meta']['device_recorded_at'])
                    ? Carbon::parse($validated['meta']['device_recorded_at'])
                    : now(),
                'latitude' => $validated['meta']['latitude'],
                'longitude' => $validated['meta']['longitude'],
                'accuracy_meters' => $validated['meta']['accuracy_meters'] ?? null,
                'source' => $validated['meta']['source'] ?? 'foreground',
                'app_state' => 'foreground',
                'is_location_enabled' => $validated['meta']['is_location_enabled'] ?? true,
                'note' => $validated['meta']['note'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Kehadiran santri berhasil disimpan.',
            'warnings' => $geoWarnings,
        ]);
    }

    public function pendingTeacherPresence(Request $request): JsonResponse
    {
        $user = $request->user();
        $targetDate = $request->filled('date')
            ? Carbon::parse((string) $request->input('date'))->toDateString()
            : null;

        $sessions = LessonSession::query()
            ->with(['schedule.schoolClass:id,name,grade_level_id', 'schedule.subject:id,name', 'schedule.teacher:id,name'])
            ->whereHas('schedule', fn ($q) => $q->where('teacher_id', $user->id))
            ->when($targetDate, fn ($q) => $q->whereDate('date', $targetDate))
            ->where(function ($q) {
                $q->where('teacher_presence_status', LessonSession::TEACHER_PRESENCE_PENDING)
                    ->orWhereNull('teacher_presence_status');
            })
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (LessonSession $s) => now()->gte($this->resolveSessionCutoffAt($s)))
            ->values();

        return response()->json([
            'pending_sessions' => $sessions->map(function (LessonSession $session) {
                $schedule = $session->schedule;
                $dayName = $this->dayName((int) ($schedule?->day ?? Carbon::parse($session->date)->isoWeekday()));

                return [
                    'session_id' => $session->id,
                    'date' => $session->date->toDateString(),
                    'day_name' => $dayName,
                    'start_time' => $session->start_time,
                    'end_time' => $session->end_time,
                    'jam_no' => $schedule?->jam_no,
                    'class' => [
                        'id' => $schedule?->schoolClass?->id,
                        'name' => $schedule?->schoolClass?->name,
                        'grade_level_id' => $schedule?->schoolClass?->grade_level_id,
                    ],
                    'subject' => [
                        'id' => $schedule?->subject?->id,
                        'name' => $schedule?->subject?->name,
                    ],
                    'teacher_presence_status' => $this->normalizeTeacherPresenceStatus($session),
                    'teacher_presence_reason' => $session->teacher_presence_reason,
                    'teacher_presence_cutoff_at' => $this->resolveSessionCutoffAt($session)->toIso8601String(),
                    'teacher_presence_deadline_at' => $this->resolvePresenceDeadlineAt($session)->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function submitTeacherPresence(Request $request, LessonSession $session): JsonResponse
    {
        $user = $request->user();
        $this->authorizeSession($session, $user->id);

        $validated = $request->validate([
            'status' => ['required', 'in:excused,sick,absent'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $cutoffAt = $this->resolveSessionCutoffAt($session);
        if (now()->lt($cutoffAt)) {
            return response()->json([
                'message' => 'Konfirmasi status kehadiran hanya tersedia setelah batas waktu sesi terlewat.',
                'code' => 'teacher_presence_confirm_too_early',
                'cutoff_at' => $cutoffAt->toIso8601String(),
            ], 422);
        }

        $session->forceFill([
            'teacher_presence_status' => $validated['status'],
            'teacher_presence_reason' => $validated['reason'] ?? null,
            'teacher_presence_source' => 'teacher_confirm',
            'teacher_presence_confirmed_at' => now(),
            'teacher_presence_deadline_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Status kehadiran guru berhasil dikonfirmasi.',
            'teacher_presence_status' => $session->teacher_presence_status,
        ]);
    }

    public function pingLocation(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
            'source' => ['nullable', 'in:foreground,background,last_known'],
            'app_state' => ['nullable', 'in:foreground,background'],
            'is_location_enabled' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $minIntervalSeconds = (int) config('geo_attendance.location_log.ping_min_interval_seconds', 300);
        $recent = TeacherLocationLog::query()
            ->where('teacher_id', $user->id)
            ->latest('recorded_at')
            ->first();

        $recordedAt = isset($validated['recorded_at'])
            ? Carbon::parse($validated['recorded_at'])
            : now();

        if ($recent && $recordedAt->diffInSeconds($recent->recorded_at) < $minIntervalSeconds) {
            return response()->json([
                'message' => 'Ping diabaikan karena interval terlalu dekat.',
                'accepted' => false,
                'min_interval_seconds' => $minIntervalSeconds,
            ]);
        }

        $log = TeacherLocationLog::query()->create([
            'teacher_id' => $user->id,
            'recorded_at' => $recordedAt,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'accuracy_meters' => $validated['accuracy_meters'] ?? null,
            'source' => $validated['source'] ?? 'background',
            'app_state' => $validated['app_state'] ?? 'background',
            'is_location_enabled' => $validated['is_location_enabled'] ?? true,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message' => 'Lokasi guru berhasil direkam.',
            'accepted' => true,
            'log_id' => $log->id,
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

    /**
     * @param  array<string, mixed>|null  $meta
     * @return list<array{code: string, message: string, detail: array<string, mixed>}>
     */
    private function buildGeoWarnings(LessonSession $session, ?array $meta): array
    {
        if (! config('geo_attendance.enabled', true)) {
            return [];
        }

        $warnings = [];

        $scheduleDate = $session->date->toDateString();
        $graceBefore = (int) config('geo_attendance.time_window.grace_before_minutes', 15);
        $graceAfter = (int) config('geo_attendance.time_window.grace_after_minutes', 20);

        $windowStart = Carbon::parse("{$scheduleDate} {$session->start_time}")->subMinutes($graceBefore);
        $windowEnd = Carbon::parse("{$scheduleDate} {$session->end_time}")->addMinutes($graceAfter);
        $deviceRecordedAt = isset($meta['device_recorded_at'])
            ? Carbon::parse((string) $meta['device_recorded_at'])
            : now();

        if ($deviceRecordedAt->lt($windowStart) || $deviceRecordedAt->gt($windowEnd)) {
            $warnings[] = [
                'code' => 'time_outside_window',
                'message' => 'Waktu input absensi berada di luar rentang jadwal mengajar.',
                'detail' => [
                    'recorded_at' => $deviceRecordedAt->toIso8601String(),
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                ],
            ];
        }

        $locationEnabled = (bool) ($meta['is_location_enabled'] ?? true);
        $lat = $meta['latitude'] ?? null;
        $lng = $meta['longitude'] ?? null;

        if (! $locationEnabled || $lat === null || $lng === null) {
            $warnings[] = [
                'code' => 'location_unavailable',
                'message' => 'Lokasi perangkat tidak tersedia atau dinonaktifkan saat absensi.',
                'detail' => [
                    'is_location_enabled' => $locationEnabled,
                ],
            ];

            return $warnings;
        }

        $centerLat = (float) config('geo_attendance.geofence.latitude');
        $centerLng = (float) config('geo_attendance.geofence.longitude');
        $radius = (float) config('geo_attendance.geofence.radius_meters', 300);
        $distance = $this->distanceMeters((float) $lat, (float) $lng, $centerLat, $centerLng);

        if ($distance > $radius) {
            $warnings[] = [
                'code' => 'outside_geofence',
                'message' => 'Lokasi guru berada di luar area geofence pesantren.',
                'detail' => [
                    'distance_meters' => round($distance, 2),
                    'allowed_radius_meters' => $radius,
                ],
            ];
        }

        return $warnings;
    }

    private function resolveSessionCutoffAt(LessonSession $session): Carbon
    {
        $graceAfter = (int) config('teacher_presence.cutoff_grace_minutes', (int) config('geo_attendance.time_window.grace_after_minutes', 20));

        return Carbon::parse("{$session->date->toDateString()} {$session->end_time}")->addMinutes($graceAfter);
    }

    private function resolvePresenceDeadlineAt(LessonSession $session): Carbon
    {
        if ($session->teacher_presence_deadline_at) {
            return Carbon::parse($session->teacher_presence_deadline_at);
        }

        $window = (int) config('teacher_presence.confirm_window_minutes', 180);

        return $this->resolveSessionCutoffAt($session)->copy()->addMinutes($window);
    }

    private function normalizeTeacherPresenceStatus(LessonSession $session): string
    {
        if ($session->teacher_presence_status) {
            return $session->teacher_presence_status;
        }

        return $session->status === LessonSession::STATUS_COMPLETED
            ? LessonSession::TEACHER_PRESENCE_PRESENT
            : LessonSession::TEACHER_PRESENCE_PENDING;
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

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
