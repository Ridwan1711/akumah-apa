<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diniyyah\ClassSubject;
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
        $validLevelTags = SchoolClass::LEVELS;
        $selectedLevelTag = (string) $request->input('level_tag', '');
        if (! in_array($selectedLevelTag, $validLevelTags, true)) {
            $selectedLevelTag = SchoolClass::LEVEL_IBTIDA;
        }

        return Inertia::render('admin/class-subject-rules/index', [
            'classes' => SchoolClass::orderBy('level_order')->orderBy('name')->get(['id', 'name', 'level']),
            'subjects' => Subject::query()
                ->with('fan:id,name')
                ->orderBy('fan_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'fan_id']),
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
                ->get(['id', 'level_tag', 'subject_id', 'period_id', 'has_score_default', 'target_jam_default', 'is_mandatory_teaching']),
            'selectedLevelTag' => $selectedLevelTag,
            'levelTagOptions' => collect($validLevelTags)->map(fn ($tag) => [
                'value' => $tag,
                'label' => $this->labelLevelTag($tag),
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
        $validLevelTags = array_merge(SchoolClass::LEVELS, ['__untagged']);

        $payload = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'mode' => ['required', 'in:include,exclude'],
            'level_tags' => ['array'],
            'level_tags.*' => ['string', 'in:'.implode(',', $validLevelTags)],
        ]);

        $selectedLevelTags = collect($payload['level_tags'] ?? [])
            ->map(fn ($tag) => trim((string) $tag))
            ->filter(fn ($tag) => $tag !== '')
            ->unique()
            ->values();

        $selectedClassIds = SchoolClass::query()
            ->when($selectedLevelTags->contains('__untagged'), function ($query) use ($selectedLevelTags) {
                $nonUntaggedTags = $selectedLevelTags->reject(fn ($tag) => $tag === '__untagged')->values();

                if ($nonUntaggedTags->isEmpty()) {
                    return $query->where(function ($inner) {
                        $inner->whereNull('level')->orWhere('level', '');
                    });
                }

                return $query->where(function ($inner) use ($nonUntaggedTags) {
                    $inner->whereIn('level', $nonUntaggedTags->all())
                        ->orWhereNull('level')
                        ->orWhere('level', '');
                });
            }, fn ($query) => $query->whereIn('level', $selectedLevelTags->all()))
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

    protected function labelLevelTag(string $tag): string
    {
        return match ($tag) {
            SchoolClass::LEVEL_IBTIDA => 'Ibtida',
            SchoolClass::LEVEL_SALAFY1 => 'Salafy 1',
            SchoolClass::LEVEL_SALAFY2 => 'Salafy 2',
            SchoolClass::LEVEL_SALAFY3 => 'Salafy 3',
            SchoolClass::LEVEL_SALAFY4 => 'Salafy 4',
            SchoolClass::LEVEL_SALAFY5 => 'Salafy 5',
            SchoolClass::LEVEL_SALAFY6 => 'Salafy 6',
            SchoolClass::LEVEL_SALAFY7 => 'Salafy 7',
            SchoolClass::LEVEL_SALAFY8 => 'Salafy 8',
            SchoolClass::LEVEL_SALAFY9 => 'Salafy 9',
            default => $tag,
        };
    }
}
