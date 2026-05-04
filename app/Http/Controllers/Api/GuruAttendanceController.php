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

        if ($session->status !== 'completed') {
            $session->status = 'completed';
            $session->save();
        }

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
