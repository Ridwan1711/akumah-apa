<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\ClassSubject;
use App\Models\Diniyyah\KitabGradeSession;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Score;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Diniyyah\ScoreWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaliKelasGradeReviewController extends Controller
{
    public function __construct(
        private readonly ScoreWorkflow $scoreWorkflow
    ) {}

    public function index(Request $request): Response
    {
        $classIds = $this->getMyClassIds($request);
        if (empty($classIds)) {
            return Inertia::render('wali-kelas/grade-reviews/index', [
                'classes' => [],
                'semesters' => [],
                'subjects' => [],
                'students' => [],
                'matrix' => [],
                'reviewStats' => [
                    'total_students' => 0,
                    'total_subjects' => 0,
                    'submitted_cells' => 0,
                    'finalized_cells' => 0,
                ],
                'filters' => [],
            ]);
        }

        $semesterId = (int) $request->integer('semester_id');
        $classId = (int) $request->integer('class_id');
        $period = $semesterId > 0 ? AcademicPeriod::query()->where('semester_id', $semesterId)->latest('id')->first() : null;
        $subjects = collect();
        $students = collect();
        $matrix = [];

        if ($period && $classId > 0 && in_array($classId, $classIds, true)) {
            $subjects = ClassSubject::query()
                ->with('subject:id,name')
                ->where('class_id', $classId)
                ->where('period_id', $period->id)
                ->where('is_active', true)
                ->where('has_score', true)
                ->orderBy('id')
                ->get()
                ->pluck('subject')
                ->filter()
                ->unique('id')
                ->values()
                ->map(fn ($subject) => ['id' => (int) $subject->id, 'name' => $subject->name]);

            $students = Student::query()
                ->where('current_class_id', $classId)
                ->where('status', Student::STATUS_ACTIVE)
                ->orderBy('full_name')
                ->get(['id', 'nis', 'full_name']);

            $subjectIds = $subjects->pluck('id');
            $studentIds = $students->pluck('id');
            $scores = collect();
            if ($subjectIds->isNotEmpty() && $studentIds->isNotEmpty()) {
                $scores = Score::query()
                    ->where('period_id', $period->id)
                    ->whereIn('subject_id', $subjectIds->all())
                    ->whereIn('student_id', $studentIds->all())
                    ->whereIn('status', [Score::STATUS_SUBMITTED, Score::STATUS_FINALIZED])
                    ->get(['student_id', 'subject_id', 'score', 'status']);
            }
            $grouped = $scores->groupBy(fn (Score $score) => $score->student_id.'-'.$score->subject_id);
            foreach ($students as $student) {
                foreach ($subjects as $subject) {
                    $cell = $grouped->get($student->id.'-'.$subject['id'], collect());
                    $avg = $cell->count() > 0 ? round((float) $cell->avg('score'), 2) : null;
                    $status = $cell->contains(fn (Score $score) => $score->status === Score::STATUS_FINALIZED)
                        ? Score::STATUS_FINALIZED
                        : ($cell->isNotEmpty() ? Score::STATUS_SUBMITTED : null);
                    $matrix[$student->id][$subject['id']] = [
                        'average' => $avg,
                        'status' => $status,
                    ];
                }
            }
        }

        $submittedCells = 0;
        $finalizedCells = 0;
        foreach ($matrix as $bySubject) {
            foreach ($bySubject as $cell) {
                if (($cell['status'] ?? null) === Score::STATUS_SUBMITTED) {
                    $submittedCells++;
                }
                if (($cell['status'] ?? null) === Score::STATUS_FINALIZED) {
                    $finalizedCells++;
                }
            }
        }

        return Inertia::render('wali-kelas/grade-reviews/index', [
            'classes' => SchoolClass::query()->whereIn('id', $classIds)->orderBy('order')->orderBy('name')->get(['id', 'name', 'grade_level_id']),
            'semesters' => Semester::with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']),
            'subjects' => $subjects,
            'students' => $students,
            'matrix' => $matrix,
            'reviewStats' => [
                'total_students' => $students->count(),
                'total_subjects' => $subjects->count(),
                'submitted_cells' => $submittedCells,
                'finalized_cells' => $finalizedCells,
            ],
            'filters' => $request->only(['class_id', 'semester_id']),
        ]);
    }

    public function review(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
        ]);
        $classIds = $this->getMyClassIds($request);
        $classId = (int) $data['class_id'];
        if (! in_array($classId, $classIds, true)) {
            abort(403, 'Anda tidak memiliki akses review untuk kelas ini.');
        }

        $period = AcademicPeriod::query()
            ->where('semester_id', (int) $data['semester_id'])
            ->latest('id')
            ->firstOrFail();
        $subjectIds = ClassSubject::query()
            ->where('class_id', $classId)
            ->where('period_id', $period->id)
            ->where('is_active', true)
            ->where('has_score', true)
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $studentIds = Student::query()
            ->where('current_class_id', $classId)
            ->where('status', Student::STATUS_ACTIVE)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if (empty($studentIds) || $subjectIds->isEmpty()) {
            return back()->with('success', 'Tidak ada nilai yang perlu direview.');
        }

        $scores = Score::query()
            ->where('period_id', $period->id)
            ->whereIn('subject_id', $subjectIds->all())
            ->whereIn('student_id', $studentIds)
            ->where('status', Score::STATUS_SUBMITTED)
            ->get();
        /** @var Score $score */
        foreach ($scores as $score) {
            $this->scoreWorkflow->assertTransition((string) $score->status, Score::STATUS_FINALIZED);
            $score->status = Score::STATUS_FINALIZED;
            $score->save();
        }

        KitabGradeSession::query()
            ->where('period_id', $period->id)
            ->where('class_id', $classId)
            ->whereIn('subject_id', $subjectIds->all())
            ->where('status', KitabGradeSession::STATUS_SUBMITTED)
            ->update([
                'status' => KitabGradeSession::STATUS_REVIEWED,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()?->id,
            ]);

        return back()->with('success', 'Rekap nilai berhasil direview dan difinalisasi.');
    }

    private function getMyClassIds(Request $request): array
    {
        return $request->user()->homeroomAssignments()->pluck('class_id')->unique()->values()->all();
    }
}
