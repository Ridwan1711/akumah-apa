<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\KitabReadingExaminerAssignment;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KitabReadingExaminerAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedSemesterId = (int) $request->integer('semester_id');
        $selectedPeriod = $this->resolvePeriod($selectedSemesterId)
            ?? AcademicPeriod::query()->active()->first()
            ?? AcademicPeriod::query()->oldest('id')->first();

        return Inertia::render('admin/kitab-reading-examiners/index', [
            'assignments' => KitabReadingExaminerAssignment::query()
                ->with([
                    'examiner:id,name',
                    'schoolClass:id,name,grade_level_id',
                    'period:id,academic_year_id,semester_id,is_active',
                    'period.academicYear:id,name',
                    'period.semester:id,name',
                ])
                ->when($selectedPeriod, fn ($query) => $query->where('period_id', $selectedPeriod->id))
                ->orderBy('class_id')
                ->orderBy('examiner_id')
                ->get(),
            'examiners' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('name', Role::GURU))
                ->orderBy('name')
                ->get(['id', 'name']),
            'classes' => SchoolClass::query()
                ->orderBy('order')
                ->orderBy('name')
                ->whereNotIn('id', KitabReadingExaminerAssignment::query()->pluck('class_id'))
                ->get(['id', 'name', 'grade_level_id', 'student_gender']),
            'semesters' => Semester::query()
                ->withActivePeriodFlag()
                ->with('academicYear:id,name')
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->get(['id', 'name', 'academic_year_id'])
                ->map(fn (Semester $semester) => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'academic_year_name' => $semester->academicYear?->name,
                    'is_active' => (bool) $semester->is_active,
                ]),
            'selectedSemesterId' => $selectedPeriod?->semester_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'examiner_id' => [
                'required',
                'exists:users,id',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
            'class_id' => ['required', 'exists:classes,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
        ]);

        $period = $this->resolvePeriod((int) $validated['semester_id']);
        abort_unless($period !== null, 422, 'Periode akademik untuk semester ini belum tersedia.');

        $examiner = User::query()->findOrFail((int) $validated['examiner_id']);
        abort_if($examiner->hasAnyRole(Role::SANTRI_ROLES), 422, 'Akun santri, wali santri, atau alumni tidak bisa menjadi penguji.');

        KitabReadingExaminerAssignment::query()->firstOrCreate([
            'examiner_id' => (int) $validated['examiner_id'],
            'class_id' => (int) $validated['class_id'],
            'period_id' => $period->id,
        ]);

        return redirect()->route('admin.kitab-reading-examiners.index', [
            'semester_id' => (int) $validated['semester_id'],
        ])->with('success', 'Penguji baca kitab berhasil ditugaskan.');
    }

    public function destroy(Request $request, KitabReadingExaminerAssignment $kitabReadingExaminer): RedirectResponse
    {
        $semesterId = $request->integer('semester_id');
        $kitabReadingExaminer->delete();

        return redirect()->route('admin.kitab-reading-examiners.index', [
            'semester_id' => $semesterId > 0 ? $semesterId : null,
        ])->with('success', 'Penugasan penguji baca kitab berhasil dihapus.');
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
}
