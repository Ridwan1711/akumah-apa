<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diniyyah\ClassSubject;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\LevelSubjectDefault;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClassSubjectRuleController extends Controller
{
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
        $gradeLevels = GradeLevel::query()
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $validLevelIds = $gradeLevels->pluck('id')->map(fn ($id) => (string) $id)->all();
        $selectedLevelId = (string) $request->input('level_id', '');
        if (! in_array($selectedLevelId, $validLevelIds, true)) {
            $selectedLevelId = (string) ($validLevelIds[0] ?? '');
        }

        return Inertia::render('admin/class-subject-rules/index', [
            'classes' => SchoolClass::query()
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name', 'grade_level_id']),
            'subjects' => Subject::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'semesters' => Semester::query()
                ->with('academicYear:id,name')
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->get(['id', 'name', 'academic_year_id', 'is_active'])
                ->map(fn (Semester $semester) => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'academic_year_name' => $semester->academicYear?->name,
                    'is_active' => $semester->is_active,
                ]),
            'selectedPeriodId' => $selectedPeriodId,
            'selectedSemesterId' => $selectedSemesterId,
            'rules' => ClassSubject::with(['schoolClass:id,name', 'subject:id,name'])
                ->when($selectedPeriodId > 0, fn ($query) => $query->where('period_id', $selectedPeriodId))
                ->get(['id', 'class_id', 'subject_id', 'period_id', 'has_score', 'is_active']),
            'levelDefaults' => LevelSubjectDefault::query()
                ->when($selectedPeriodId > 0, fn ($query) => $query->where('period_id', $selectedPeriodId))
                ->get(['id', 'level_id', 'subject_id', 'period_id', 'has_score_default', 'target_jam_default', 'is_mandatory_teaching']),
            'selectedLevelId' => $selectedLevelId,
            'levelOptions' => $gradeLevels->map(fn (GradeLevel $level) => [
                'value' => (string) $level->id,
                'label' => $level->name,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'has_score' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
        $periodId = $this->resolvePeriodIdBySemesterId((int) $payload['semester_id']);

        ClassSubject::updateOrCreate(
            [
                'class_id' => $payload['class_id'],
                'subject_id' => $payload['subject_id'],
                'period_id' => $periodId,
            ],
            [
                'has_score' => $payload['has_score'],
                'is_active' => $payload['is_active'],
            ]
        );

        return redirect()->route('admin.class-subject-rules.index', ['semester_id' => $payload['semester_id']])
            ->with('success', 'Rule penilaian berhasil disimpan.');
    }

    public function destroy(Request $request, ClassSubject $classSubjectRule): RedirectResponse
    {
        $semesterId = $request->integer('semester_id');
        $classSubjectRule->delete();

        return redirect()->route('admin.class-subject-rules.index', ['semester_id' => $semesterId > 0 ? $semesterId : null])
            ->with('success', 'Rule penilaian berhasil dihapus.');
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $validLevelIds = GradeLevel::query()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $payload = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'mode' => ['required', 'in:include,exclude'],
            'level_ids' => ['array'],
            'level_ids.*' => ['string', 'in:'.implode(',', $validLevelIds)],
        ]);

        $selectedLevelIds = collect($payload['level_ids'] ?? [])
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->values();

        $selectedClassIds = SchoolClass::query()
            ->when($selectedLevelIds->isNotEmpty(), fn ($query) => $query->whereIn('grade_level_id', $selectedLevelIds->all()))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $allClassIds = SchoolClass::query()->pluck('id')->map(fn ($id) => (int) $id);
        $selectedLookup = array_flip($selectedClassIds->all());
        $isIncludeMode = $payload['mode'] === 'include';
        DB::transaction(function () use ($allClassIds, $selectedLookup, $isIncludeMode, $payload) {
            foreach ($allClassIds as $classId) {
                $isSelected = isset($selectedLookup[$classId]);
                $hasScore = $isIncludeMode ? $isSelected : ! $isSelected;

                ClassSubject::updateOrCreate(
                    [
                        'class_id' => $classId,
                        'subject_id' => $payload['subject_id'],
                        'period_id' => $this->resolvePeriodIdBySemesterId((int) $payload['semester_id']),
                    ],
                    [
                        'has_score' => $hasScore,
                        'is_active' => true,
                    ]
                );
            }
        });

        return redirect()
            ->route('admin.class-subject-rules.index', ['semester_id' => $payload['semester_id']])
            ->with('success', 'Rule penilaian batch berhasil disimpan.');
    }

    protected function resolvePeriodIdBySemesterId(int $semesterId): int
    {
        return (int) DB::table('academic_periods')
            ->where('semester_id', $semesterId)
            ->value('id');
    }

}
