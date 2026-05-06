<?php

namespace App\Http\Controllers\Wali;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wali\UpdateChildProfileRequest;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\Score;
use App\Models\EmProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaliSantriController extends Controller
{
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

    public function children(Request $request): Response
    {
        $guardian = $request->user()->primaryGuardian();
        abort_unless($guardian, 404, 'Data wali tidak ditemukan.');

        $children = $guardian->students()
            ->with(['currentClass:id,name', 'violationSummary'])
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->get();

        return Inertia::render('wali/children', [
            'children' => $children,
        ]);
    }

    public function childDetail(Request $request, Student $student): Response
    {
        $guardian = $request->user()->primaryGuardian();
        abort_unless($guardian && $guardian->students()->where('students.id', $student->id)->exists(), 403, 'Anda tidak memiliki akses ke data santri ini.');

        $student->load(['currentClass:id,name', 'violationSummary', 'emisProfile']);

        $activeSemester = AcademicPeriod::query()->active()->with('semester:id,name')->first()?->semester;
        $semesterId = $request->semester_id ?? $activeSemester?->id;

        $grades = [];
        $semester = null;
        if ($semesterId) {
            $semester = Semester::with('academicYear:id,name')->find($semesterId);
            $grades = Score::where('student_id', $student->id)
                ->whereHas('period', fn ($q) => $q->where('semester_id', $semesterId))
                ->with(['subject:id,name', 'component:id,name'])
                ->get();
        }

        $recentViolations = StudentViolation::where('student_id', $student->id)
            ->with('violationType:id,name,points,category')
            ->orderByDesc('date')
            ->limit(10)->get();

        $semesters = Semester::with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']);

        return Inertia::render('wali/child-detail', [
            'student' => $student,
            'semesters' => $semesters,
            'currentSemesterId' => $semesterId,
            'semester' => $semester,
            'grades' => $grades,
            'recentViolations' => $recentViolations,
        ]);
    }

    public function editChild(Request $request, Student $student): Response
    {
        $guardian = $request->user()->primaryGuardian();
        abort_unless($guardian && $guardian->students()->where('students.id', $student->id)->exists(), 403, 'Anda tidak memiliki akses ke data santri ini.');

        $student->load(['currentClass:id,name', 'emisProfile']);

        return Inertia::render('wali/child-edit', [
            'student' => $student,
        ]);
    }

    public function updateChild(UpdateChildProfileRequest $request, Student $student): RedirectResponse
    {
        $guardian = $request->user()->primaryGuardian();
        abort_unless($guardian && $guardian->students()->where('students.id', $student->id)->exists(), 403, 'Anda tidak memiliki akses ke data santri ini.');

        $validated = $request->validated();

        $emProfileData = $validated['em_profile'] ?? [];
        $studentFields = collect($validated)->except(['em_profile'])->all();

        $student->update($studentFields);

        if (is_array($emProfileData) && count($emProfileData) > 0) {
            $student->loadMissing('emisProfile');
            $current = $student->emProfilePayload();
            $merged = array_replace_recursive($current, ['santri' => $emProfileData]);
            $attributes = EmProfile::fromPayload($merged);
            $student->emisProfile()->updateOrCreate([], $attributes);
            $student->forceFill(['em_profile' => $merged])->save();
        }

        return redirect()->route('wali.children.show', $student)
            ->with('success', 'Data anak berhasil diperbarui.');
    }

    public function childSchedule(Request $request, Student $student): Response
    {
        $guardian = $request->user()->primaryGuardian();
        abort_unless($guardian && $guardian->students()->where('students.id', $student->id)->exists(), 403, 'Anda tidak memiliki akses ke data santri ini.');

        $student->load('currentClass:id,name,grade_level_id');
        abort_unless($student->currentClass, 404, 'Kelas santri tidak ditemukan.');

        $activeSemester = AcademicPeriod::query()->active()->with('semester:id,name')->first()?->semester;
        $schedules = AcademicSchedule::query()
            ->where('class_id', $student->currentClass->id)
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

        return Inertia::render('wali/child-schedule', [
            'student' => $student->only(['id', 'full_name', 'nis']),
            'class' => $student->currentClass,
            'semester' => $activeSemester ? $activeSemester->only(['id', 'name']) : null,
            'week' => array_values($week),
        ]);
    }
}
