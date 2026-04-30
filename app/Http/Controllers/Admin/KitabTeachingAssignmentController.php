<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKitabTeachingAssignmentRequest;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\GradeSubject;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use App\Services\Diniyyah\SubjectTeachingHourResolver;
use App\Services\RoleCertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KitabTeachingAssignmentController extends Controller
{
    public function __construct(
        private SubjectTeachingHourResolver $teachingHourResolver,
        private RoleCertificateService $certificateService,
    ) {}

    public function index(Request $request): Response
    {
        $selectedSemesterId = (int) $request->input('semester_id', 0);
        $selectedPeriodId = $selectedSemesterId > 0
            ? (int) (DB::table('academic_periods')->where('semester_id', $selectedSemesterId)->value('id') ?? 0)
            : (int) $request->input('period_id', 0);
        if ($selectedPeriodId <= 0) {
            $selectedPeriodId = (int) (DB::table('academic_periods')->where('is_active', true)->value('id') ?? 0);
        }
        if ($selectedPeriodId <= 0) {
            $selectedPeriodId = (int) (DB::table('academic_periods')->value('id') ?? 0);
        }
        $selectedSemesterId = (int) (DB::table('academic_periods')->where('id', $selectedPeriodId)->value('semester_id') ?? 0);

        return Inertia::render('admin/teaching-assignments/index', [
            'assignments' => TeacherAssignment::with([
                'teacher:id,name',
                'schoolClass:id,name,grade_level_id',
                'subject:id,name',
                'period:id,academic_year_id,semester_id,is_active',
            ])
                ->when($selectedPeriodId > 0, fn ($query) => $query->where('period_id', $selectedPeriodId))
                ->orderBy('class_id')
                ->orderBy('subject_id')
                ->get(),
            'teachers' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('name', Role::GURU))
                ->orderBy('name')
                ->get(['id', 'name']),
            'activeAcademicYear' => AcademicPeriod::query()->active()->with('academicYear:id,name')->first()?->academicYear?->only(['id', 'name']),
            'classes' => SchoolClass::query()->orderBy('order')->orderBy('name')->get(['id', 'name', 'grade_level_id']),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'gradeSubjects' => GradeSubject::query()->get(['grade_level_id', 'subject_id']),
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
            'selectedPeriodId' => $selectedPeriodId,
            'selectedSemesterId' => $selectedSemesterId,
        ]);
    }

    public function store(StoreKitabTeachingAssignmentRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $periodId = $this->resolvePeriodIdBySemesterId((int) $payload['semester_id']);
        $this->ensureSubjectMappedToClass((int) $payload['subject_id'], (int) $payload['class_id']);
        $resolvedTargetJam = $this->resolveTargetJam(
            (int) $payload['class_id'],
            (int) $payload['subject_id'],
            $periodId,
            null
        );
        $assignment = TeacherAssignment::updateOrCreate(
            [
                'class_id' => $payload['class_id'],
                'subject_id' => $payload['subject_id'],
                'period_id' => $periodId,
            ],
            [
                'teacher_id' => $payload['teacher_id'],
                'target_jam' => $resolvedTargetJam,
            ]
        );
        $this->certificateService->issueForTeacherAssignment($assignment, $request->user()?->id);

        return redirect()->route('admin.teaching-assignments.index', ['semester_id' => $payload['semester_id']])
            ->with('success', 'Penugasan guru berhasil ditambahkan.');
    }

    public function destroy(Request $request, TeacherAssignment $teachingAssignment): RedirectResponse
    {
        $semesterId = $request->integer('semester_id');
        $teachingAssignment->delete();

        return redirect()->route('admin.teaching-assignments.index', ['semester_id' => $semesterId > 0 ? $semesterId : null])
            ->with('success', 'Penugasan guru berhasil dihapus.');
    }

    protected function resolvePeriodIdBySemesterId(int $semesterId): int
    {
        return (int) DB::table('academic_periods')
            ->where('semester_id', $semesterId)
            ->value('id');
    }

    protected function resolveTargetJam(int $classId, int $subjectId, int $periodId, ?int $requestedTargetJam): int
    {
        return $this->teachingHourResolver->resolve($classId, $subjectId, $periodId, $requestedTargetJam);
    }

    protected function ensureSubjectMappedToClass(int $subjectId, int $classId): void
    {
        $class = SchoolClass::query()->findOrFail($classId);

        $isMapped = GradeSubject::query()
            ->where('grade_level_id', $class->grade_level_id)
            ->where('subject_id', $subjectId)
            ->exists();

        if (! $isMapped) {
            abort(422, 'Pelajaran ini gak dipelajari di sini.');
        }
    }
}
