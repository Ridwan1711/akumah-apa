<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\LessonAttendance;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class GuruAttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))->toDateString()
            : now()->toDateString();
        $dayOfWeek = (int) Carbon::parse($date)->isoWeekday();
        $activeSemester = AcademicPeriod::query()->active()->with('semester:id,name')->first()?->semester;

        $schedules = AcademicSchedule::query()
            ->where('day', $dayOfWeek)
            ->where('teacher_id', $user->id)
            ->with([
                'schoolClass:id,name,level',
                'subject:id,name',
            ])
            ->orderBy('time_start')
            ->get();

        $sessions = [];
        foreach ($schedules as $schedule) {
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
                    'id' => $schedule->schoolClass?->id,
                    'name' => $schedule->schoolClass?->name,
                    'level' => $schedule->schoolClass?->level,
                ],
                'subject' => [
                    'id' => $schedule->subject?->id,
                    'name' => $schedule->subject?->name,
                ],
            ];
        }

        return Inertia::render('guru/attendance-sessions/index', [
            'date' => $date,
            'semester' => $activeSemester ? $activeSemester->only(['id', 'name']) : null,
            'sessions' => $sessions,
            'filters' => $request->only(['date']),
        ]);
    }

    public function show(Request $request, LessonSession $session): Response
    {
        $user = $request->user();
        $this->authorizeSession($session, $user);

        $schedule = $session->schedule()->with([
            'schoolClass:id,name,level',
            'subject:id,name',
        ])->firstOrFail();
        $class = $schedule->schoolClass;

        $students = Student::where('current_class_id', $class->id)
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->get(['id', 'nis', 'full_name']);

        $attendances = LessonAttendance::where('lesson_session_id', $session->id)
            ->get()
            ->keyBy('student_id');

        return Inertia::render('guru/attendance-sessions/show', [
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
                'subject' => [
                    'id' => $schedule->subject?->id,
                    'name' => $schedule->subject?->name,
                ],
            ],
            'students' => $students->map(function (Student $student) use ($attendances) {
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
            })->values(),
        ]);
    }

    public function store(Request $request, LessonSession $session): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeSession($session, $user);

        $schedule = $session->schedule()->with('schoolClass')->firstOrFail();
        $class = $schedule->schoolClass;

        $validated = $request->validate([
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.student_id' => ['required', 'exists:students,id'],
            'attendances.*.status' => ['required', 'in:present,excused,absent'],
            'attendances.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated['attendances'] as $index => $row) {
            if (($row['status'] ?? 'present') !== 'present' && blank($row['reason'] ?? null)) {
                return back()->withErrors([
                    "attendances.{$index}.reason" => 'Keterangan wajib diisi untuk status izin/alpha.',
                ]);
            }
        }

        $allowedStudentIds = Student::where('current_class_id', $class->id)
            ->where('status', Student::STATUS_ACTIVE)
            ->pluck('id')
            ->all();

        foreach ($validated['attendances'] as $row) {
            $studentId = (int) $row['student_id'];
            if (! in_array($studentId, $allowedStudentIds, true)) {
                continue;
            }

            LessonAttendance::updateOrCreate(
                [
                    'lesson_session_id' => $session->id,
                    'student_id' => $studentId,
                ],
                [
                    'status' => $row['status'],
                    'reason' => $row['reason'] ?: null,
                    'marked_by' => $user->id,
                    'marked_at' => now(),
                ]
            );
        }

        return redirect()
            ->route('guru.attendance-sessions.show', $session)
            ->with('success', 'Kehadiran santri berhasil disimpan.');
    }

    private function authorizeSession(LessonSession $session, User $user): void
    {
        $session->loadMissing('schedule');
        $schedule = $session->schedule;
        abort_unless($schedule, 404, 'Sesi tidak memiliki jadwal yang valid.');

        if (! $user->isAdmin() && $schedule->teacher_id !== $user->id) {
            abort(403, 'Anda tidak berhak mengelola sesi ini.');
        }
    }
}
