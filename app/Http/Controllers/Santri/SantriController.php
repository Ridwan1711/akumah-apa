<?php

namespace App\Http\Controllers\Santri;

use App\Http\Controllers\Controller;
use App\Http\Requests\Santri\UpdateOwnGuardianRequest;
use App\Http\Requests\Santri\UpdateOwnProfileRequest;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Guardian;
use App\Models\Diniyyah\Score;
use App\Models\LessonAttendance;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\Firebase\AccountLinkSyncService;

class SantriController extends Controller
{
    public function __construct(
        private readonly AccountLinkSyncService $accountLinkSync,
    ) {}

    private static function dayName(int $day): string
    {
        return match ($day) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Ahad',
            default => 'Unknown',
        };
    }

    private function getStudent(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student, 404, 'Data santri tidak ditemukan.');

        return $student;
    }

    public function grades(Request $request): Response
    {
        $student = $this->getStudent($request);

        $semesterId = $request->semester_id;
        $semesters = Semester::with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']);

        $grades = [];
        if ($semesterId) {
            $grades = Score::where('student_id', $student->id)
                ->whereHas('period', fn ($q) => $q->where('semester_id', $semesterId))
                ->with(['subject:id,name', 'component:id,name'])
                ->get();
        }

        return Inertia::render('santri/grades', [
            'student' => $student->only('id', 'full_name', 'nis'),
            'semesters' => $semesters,
            'grades' => $grades,
            'filters' => $request->only('semester_id'),
        ]);
    }

    public function violations(Request $request): Response
    {
        $student = $this->getStudent($request);
        $student->load('violationSummary');

        $violations = $student->violations()
            ->with('violationType:id,name,points,category')
            ->orderByDesc('date')
            ->paginate(15);

        return Inertia::render('santri/violations', [
            'student' => $student->only('id', 'full_name', 'nis'),
            'summary' => $student->violationSummary,
            'violations' => $violations,
        ]);
    }

    public function profile(Request $request): Response
    {
        $student = $this->getStudent($request);
        $student->load(['currentClass:id,name', 'guardians']);

        return Inertia::render('santri/profile', [
            'student' => $student,
        ]);
    }

    public function editProfile(Request $request): Response
    {
        $student = $this->getStudent($request);
        $student->load(['currentClass:id,name', 'guardians']);

        return Inertia::render('santri/profile-edit', [
            'student' => $student,
            'account' => $request->user()->only(['name', 'email', 'whatsapp_phone', 'google_connected']),
        ]);
    }

    public function updateProfile(UpdateOwnProfileRequest $request): RedirectResponse
    {
        $student = $this->getStudent($request);
        $validated = $request->validated();

        $student->update(collect($validated)->except(['whatsapp_phone', 'google_connected'])->all());

        $user = $request->user();
        $user->fill([
            'whatsapp_phone' => $validated['whatsapp_phone'] ?? null,
            'google_connected' => (bool) ($validated['google_connected'] ?? false),
        ]);
        $user->save();
        $this->accountLinkSync->syncUser($user);

        return redirect()->route('santri.profile')
            ->with('success', 'Profil berhasil diperbarui.');
    }

    public function updateGuardian(UpdateOwnGuardianRequest $request, Guardian $guardian): RedirectResponse
    {
        $student = $this->getStudent($request);

        abort_unless(
            $student->guardians()->whereKey($guardian->id)->exists(),
            403,
            'Data wali tidak terhubung dengan akun santri ini.'
        );

        $validated = $request->validated();

        $guardian->update([
            'full_name' => $validated['full_name'],
            'nik' => $validated['nik'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'occupation' => $validated['occupation'] ?? null,
            'income_band' => $validated['income_band'] ?? null,
        ]);

        $student->guardians()->updateExistingPivot($guardian->id, [
            'relationship' => $validated['relationship'],
        ]);

        return redirect()->route('santri.profile.edit')
            ->with('success', "Data wali {$guardian->full_name} berhasil diperbarui.");
    }

    public function schedule(Request $request): Response
    {
        $student = $this->getStudent($request);
        $student->load(['currentClass']);

        $class = $student->currentClass;
        abort_unless($class, 404, 'Kelas santri tidak ditemukan.');

        $activeSemester = Semester::where('is_active', true)->first();

        $schedules = AcademicSchedule::query()
            ->where('class_id', $class->id)
            ->with([
                'subject:id,name',
                'teacher:id,name',
            ])
            ->orderBy('day')
            ->orderBy('time_start')
            ->get();

        $week = [];
        foreach ($schedules as $item) {
            $day = (int) $item->day;
            if (! isset($week[$day])) {
                $week[$day] = [
                    'day_of_week' => $day,
                    'day_name' => self::dayName($day),
                    'entries' => [],
                ];
            }
            $week[$day]['entries'][] = [
                'id' => $item->id,
                'subject' => [
                    'id' => $item->subject?->id,
                    'name' => $item->subject?->name,
                ],
                'teacher' => [
                    'id' => $item->teacher?->id,
                    'name' => $item->teacher?->name,
                ],
                'start_time' => $item->time_start,
                'end_time' => $item->time_end,
                'room' => null,
            ];
        }

        ksort($week);

        return Inertia::render('santri/schedule', [
            'class' => $class->only(['id', 'name', 'grade_level_id']),
            'semester' => $activeSemester ? $activeSemester->only(['id', 'name']) : null,
            'week' => array_values($week),
        ]);
    }

    public function attendances(Request $request): Response
    {
        $student = $this->getStudent($request);

        $query = LessonAttendance::where('student_id', $student->id)
            ->with([
                'lessonSession.schedule.schoolClass:id,name,level',
                'lessonSession.schedule.subject:id,name',
            ])
            ->orderByDesc('lesson_session_id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereHas('lessonSession', function ($q) use ($request) {
                $q->where('date', '>=', $request->string('date_from'));
            });
        }

        if ($request->filled('date_to')) {
            $query->whereHas('lessonSession', function ($q) use ($request) {
                $q->where('date', '<=', $request->string('date_to'));
            });
        }

        $attendances = $query->paginate(20)->withQueryString();

        return Inertia::render('santri/attendances', [
            'attendances' => $attendances,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
        ]);
    }
}
