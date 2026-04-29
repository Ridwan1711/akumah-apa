<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\AssessmentComponent;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\Semester;
use App\Models\Student;
use App\Notifications\GradeUpdatedNotification;
use App\Services\Diniyyah\ClassSubjectGradingResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KitabGradeController extends Controller
{
    public function entry(Request $request): RedirectResponse
    {
        if ($request->filled('class_id') && $request->filled('subject_id') && $request->filled('period_id')) {
            $url = route('guru.admin.kitab-grades.input', [
                'academic_period' => (int) $request->query('period_id'),
                'kitab_subject' => (int) $request->query('subject_id'),
                'diniyah_class' => (int) $request->query('class_id'),
            ]);
            if ($request->filled('component_id')) {
                $url .= '?'.http_build_query(['component_id' => $request->query('component_id')]);
            }

            return redirect()->to($url);
        }

        return redirect()->route('guru.admin.kitab-grades.subject', [
            'academic_period' => $this->resolveDefaultAcademicPeriod()->id,
        ]);
    }

    public function pickSubject(Request $request, Semester $academic_period): Response
    {
        $period = $this->resolvePeriodBySemesterId((int) $academic_period->id);
        $user = $request->user();
        $hasLimitedView = ! $user->hasPermission('kitab_grades.view_all');

        if ($hasLimitedView) {
            $hasAnyAssignment = TeacherAssignment::where('teacher_id', $user->id)
                ->where('period_id', $period->id)
                ->exists();
            if (! $hasAnyAssignment) {
                abort(403, 'Anda tidak memiliki penugasan untuk periode akademik ini.');
            }
        }

        $subjectsQuery = Subject::query()->orderBy('name');
        if ($hasLimitedView) {
            $subjectIds = TeacherAssignment::where('teacher_id', $user->id)
                ->where('period_id', $period->id)
                ->pluck('subject_id')
                ->unique();
            $subjectsQuery->whereIn('id', $subjectIds);
        }

        $subjects = $subjectsQuery->get(['id', 'name']);

        return Inertia::render('admin/kitab-grades/select-subject', [
            'academicPeriod' => ['id' => $academic_period->id, 'name' => $academic_period->name, 'type' => null],
            'semesters' => Semester::query()
                ->withActivePeriodFlag()
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->get(['id', 'name'])
                ->map(fn (Semester $semester) => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'is_active' => (bool) $semester->is_active,
                ]),
            'subjects' => $subjects,
            'activeSemester' => AcademicPeriod::query()->active()->with('semester:id,name')->first()?->semester?->only(['id', 'name']),
        ]);
    }

    public function pickClass(Request $request, Semester $academic_period, Subject $kitab_subject): Response
    {
        $period = $this->resolvePeriodBySemesterId((int) $academic_period->id);
        $user = $request->user();
        $hasLimitedView = ! $user->hasPermission('kitab_grades.view_all');
        $resolver = app(ClassSubjectGradingResolver::class);

        if ($hasLimitedView) {
            $allowed = TeacherAssignment::where('teacher_id', $user->id)
                ->where('period_id', $period->id)
                ->where('subject_id', $kitab_subject->id)
                ->exists();
            if (! $allowed) {
                abort(403, 'Anda tidak ditugaskan untuk mata pelajaran ini pada periode ini.');
            }
        }

        $classesQuery = SchoolClass::query()->orderBy('order')->orderBy('name');
        if ($hasLimitedView) {
            $classIds = TeacherAssignment::where('teacher_id', $user->id)
                ->where('period_id', $period->id)
                ->where('subject_id', $kitab_subject->id)
                ->pluck('class_id')
                ->unique();
            $classesQuery->whereIn('id', $classIds);
        }

        /** @var Collection<int, SchoolClass> $classes */
        $classes = $classesQuery->get(['id', 'name', 'grade_level_id'])
            ->filter(fn (SchoolClass $c) => $resolver->gradingEnabled($c->id, $kitab_subject->id, (int) $period->id))
            ->values();

        return Inertia::render('admin/kitab-grades/select-class', [
            'academicPeriod' => ['id' => $academic_period->id, 'name' => $academic_period->name, 'type' => null],
            'subject' => $kitab_subject->only(['id', 'name']),
            'classes' => $classes,
        ]);
    }

    public function input(Request $request, Semester $academic_period, Subject $kitab_subject, SchoolClass $diniyah_class): Response|RedirectResponse
    {
        $period = $this->resolvePeriodBySemesterId((int) $academic_period->id);
        $user = $request->user();
        $hasLimitedView = ! $user->hasPermission('kitab_grades.view_all');
        $resolver = app(ClassSubjectGradingResolver::class);

        if (! $resolver->gradingEnabled($diniyah_class->id, $kitab_subject->id, (int) $period->id)) {
            abort(403, 'Mata pelajaran ini tidak dinilai untuk kelas/periode ini.');
        }

        if ($hasLimitedView) {
            $allowed = TeacherAssignment::where('teacher_id', $user->id)
                ->where('class_id', $diniyah_class->id)
                ->where('subject_id', $kitab_subject->id)
                ->where('period_id', $period->id)
                ->exists();
            if (! $allowed) {
                abort(403, 'Anda tidak ditugaskan untuk mengajar mata pelajaran ini di kelas ini.');
            }
        }

        $assessmentComponents = AssessmentComponent::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $students = Student::where('current_class_id', $diniyah_class->id)
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->get(['id', 'nis', 'full_name']);

        $componentIds = $assessmentComponents->pluck('id')->all();
        $rawScores = collect();
        if (! empty($componentIds) && $students->isNotEmpty()) {
            $rawScores = Score::query()
                ->where('subject_id', $kitab_subject->id)
                ->where('period_id', $period->id)
                ->whereIn('component_id', $componentIds)
                ->whereIn('student_id', $students->pluck('id'))
                ->get(['student_id', 'component_id', 'score']);
        }

        $gradeMatrix = [];
        foreach ($rawScores as $score) {
            $studentId = (int) $score->student_id;
            $componentId = (int) $score->component_id;
            if (! isset($gradeMatrix[$studentId])) {
                $gradeMatrix[$studentId] = [];
            }
            $gradeMatrix[$studentId][$componentId] = (float) $score->score;
        }

        return Inertia::render('admin/kitab-grades/input', [
            'academicPeriod' => ['id' => $academic_period->id, 'name' => $academic_period->name, 'type' => null],
            'subject' => $kitab_subject->only(['id', 'name']),
            'schoolClass' => $diniyah_class->only(['id', 'name', 'grade_level_id']),
            'assessmentComponents' => $assessmentComponents,
            'students' => $students,
            'gradeMatrix' => $gradeMatrix,
            'isGuru' => $hasLimitedView,
        ]);
    }

    public function store(Request $request, Semester $academic_period, Subject $kitab_subject, SchoolClass $diniyah_class): RedirectResponse
    {
        $period = $this->resolvePeriodBySemesterId((int) $academic_period->id);
        $request->merge([
            'class_id' => $diniyah_class->id,
            'subject_id' => $kitab_subject->id,
            'period_id' => $period->id,
        ]);

        $rules = [
            'grades' => ['required', 'array'],
            'grades.*.student_id' => ['required', 'exists:students,id'],
            'grades.*.components' => ['required', 'array'],
            'grades.*.components.*' => ['required', 'numeric', 'min:0', 'max:100'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'period_id' => ['required', 'exists:academic_periods,id'],
            'class_id' => ['required', 'exists:classes,id'],
        ];

        $request->validate($rules);

        $user = $request->user();
        $resolver = app(ClassSubjectGradingResolver::class);

        if (! $resolver->gradingEnabled((int) $request->class_id, (int) $request->subject_id, (int) $request->period_id)) {
            throw ValidationException::withMessages([
                'subject_id' => ['Mata pelajaran ini tidak dinilai untuk kelas/periode ini.'],
            ]);
        }

        if (! $user->hasPermission('kitab_grades.view_all')) {
            $allowed = TeacherAssignment::where('teacher_id', $user->id)
                ->where('class_id', $request->class_id)
                ->where('subject_id', $request->subject_id)
                ->where('period_id', $request->period_id)
                ->exists();
            if (! $allowed) {
                throw ValidationException::withMessages([
                    'subject_id' => ['Anda tidak ditugaskan untuk mengajar mata pelajaran ini di kelas ini.'],
                ]);
            }
        }

        foreach ($request->grades as $gradeData) {
            foreach ($gradeData['components'] as $componentId => $scoreValue) {
                Score::updateOrCreate(
                    [
                        'student_id' => $gradeData['student_id'],
                        'subject_id' => $request->subject_id,
                        'component_id' => (int) $componentId,
                        'period_id' => $request->period_id,
                    ],
                    [
                        'teacher_id' => $user->id,
                        'score' => $scoreValue,
                        'status' => Score::STATUS_SUBMITTED,
                    ]
                );
            }
        }

        $studentIds = collect($request->grades)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        if ($studentIds->isNotEmpty()) {
            $students = Student::query()
                ->whereIn('id', $studentIds->all())
                ->with(['user', 'guardians.user'])
                ->get();
            foreach ($students as $student) {
                $sampleScore = Score::query()
                    ->where('student_id', $student->id)
                    ->where('subject_id', (int) $request->subject_id)
                    ->where('period_id', (int) $request->period_id)
                    ->whereIn('component_id', array_keys($request->grades[0]['components'] ?? []))
                    ->with('subject:id,name')
                    ->latest('id')
                    ->first();
                if (! $sampleScore) {
                    continue;
                }
                if ($student->user) {
                    $student->user->notify(new GradeUpdatedNotification($sampleScore));
                }
                $student->guardians
                    ->pluck('user')
                    ->filter()
                    ->unique('id')
                    ->each(fn ($user) => $user->notify(new GradeUpdatedNotification($sampleScore)));
            }
        }

        $back = route('guru.admin.kitab-grades.input', [
            'academic_period' => $academic_period->id,
            'kitab_subject' => $kitab_subject->id,
            'diniyah_class' => $diniyah_class->id,
        ]);

        return redirect()->to($back)->with('success', 'Nilai berhasil disimpan.');
    }

    protected function resolveDefaultAcademicPeriod(): Semester
    {
        return Semester::query()
            ->withActivePeriodFlag()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->firstOrFail();
    }

    protected function resolvePeriodBySemesterId(int $semesterId): object
    {
        return DB::table('academic_periods')
            ->where('semester_id', $semesterId)
            ->orderByDesc('id')
            ->firstOrFail();
    }
}
