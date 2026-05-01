<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\KitabReadingAssessment;
use App\Models\Diniyyah\KitabReadingExaminerAssignment;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KitabReadingAssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        $classIds = $this->visibleClassIds($request);
        $classId = (int) $request->integer('class_id');
        $semesterId = (int) $request->integer('semester_id');
        $period = $this->resolvePeriod($semesterId);
        $students = collect();
        $assessments = collect();

        if ($period && $classId > 0 && $this->canAccessClassPeriod($request, $classId, $period->id)) {
            $students = Student::query()
                ->where('current_class_id', $classId)
                ->where('status', Student::STATUS_ACTIVE)
                ->orderBy('full_name')
                ->get(['id', 'nis', 'full_name', 'gender']);

            $assessments = KitabReadingAssessment::query()
                ->where('class_id', $classId)
                ->where('period_id', $period->id)
                ->with('examiner:id,name')
                ->get(['id', 'student_id', 'class_id', 'period_id', 'examiner_id', 'score', 'notes', 'assessed_at'])
                ->keyBy('student_id');
        }

        return Inertia::render('admin/kitab-reading-assessments/index', [
            'classes' => SchoolClass::query()
                ->whereIn('id', $classIds)
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name', 'grade_level_id', 'student_gender']),
            'semesters' => Semester::query()
                ->with('academicYear:id,name')
                ->orderByDesc('id')
                ->get(['id', 'name', 'academic_year_id', 'start_date', 'end_date']),
            'students' => $students,
            'assessments' => $assessments,
            'filters' => $request->only(['class_id', 'semester_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $classIds = $this->visibleClassIds($request);
        $validated = $request->validate([
            'class_id' => ['required', 'integer', Rule::in($classIds)],
            'semester_id' => ['required', 'exists:semesters,id'],
            'assessments' => ['required', 'array'],
            'assessments.*.student_id' => ['required', 'exists:students,id'],
            'assessments.*.score' => ['required', 'numeric', 'min:0', 'max:100'],
            'assessments.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $period = $this->resolvePeriod((int) $validated['semester_id']);
        abort_unless($period !== null, 422, 'Periode akademik untuk semester ini belum tersedia.');
        abort_unless(
            $this->canAccessClassPeriod($request, (int) $validated['class_id'], $period->id),
            403,
            'Anda belum ditugaskan sebagai penguji baca kitab untuk kelas dan semester ini.'
        );

        $studentIds = Student::query()
            ->where('current_class_id', (int) $validated['class_id'])
            ->where('status', Student::STATUS_ACTIVE)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($validated['assessments'] as $row) {
            $studentId = (int) $row['student_id'];
            abort_unless(in_array($studentId, $studentIds, true), 422, 'Santri tidak berada di kelas yang dipilih.');

            KitabReadingAssessment::query()->updateOrCreate(
                [
                    'student_id' => $studentId,
                    'period_id' => $period->id,
                ],
                [
                    'class_id' => (int) $validated['class_id'],
                    'examiner_id' => $request->user()->id,
                    'score' => $row['score'],
                    'notes' => $row['notes'] ?? null,
                    'assessed_at' => now(),
                ]
            );
        }

        return redirect()->route('guru.kitab-reading-assessments.index', [
            'class_id' => (int) $validated['class_id'],
            'semester_id' => (int) $validated['semester_id'],
        ])->with('success', 'Nilai kemahiran membaca kitab berhasil disimpan.');
    }

    private function resolvePeriod(int $semesterId): ?AcademicPeriod
    {
        if ($semesterId <= 0) {
            return null;
        }

        return AcademicPeriod::query()
            ->where('semester_id', $semesterId)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int>
     */
    private function visibleClassIds(Request $request): array
    {
        $user = $request->user();
        if ($user->hasRole(Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK)) {
            return SchoolClass::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return collect()
            ->merge($user->kitabReadingExaminerAssignments()->pluck('class_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function canAccessClassPeriod(Request $request, int $classId, int $periodId): bool
    {
        $user = $request->user();
        if ($user->hasRole(Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK)) {
            return true;
        }

        return KitabReadingExaminerAssignment::query()
            ->where('examiner_id', $user->id)
            ->where('class_id', $classId)
            ->where('period_id', $periodId)
            ->exists();
    }
}
