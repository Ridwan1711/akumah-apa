<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\GradeSubject;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\SubjectClassOverride;
use App\Models\Diniyyah\SubjectLevelSetting;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SubjectSettingController extends Controller
{
    public function mappingIndex(): Response
    {
        return Inertia::render('admin/subject-level-mappings/index', [
            'subjects' => Subject::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'levels' => GradeLevel::query()->orderBy('order')->orderBy('name')->get(['id', 'name', 'order']),
            'gradeSubjects' => GradeSubject::query()->get(['id', 'grade_level_id', 'subject_id']),
        ]);
    }

    public function syncMappings(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'subject_ids' => ['array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'level_ids' => ['array'],
            'level_ids.*' => ['integer', 'exists:grade_levels,id'],
        ]);

        $subjectIds = collect($payload['subject_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $levelIds = collect($payload['level_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        DB::transaction(function () use ($subjectIds, $levelIds): void {
            if ($subjectIds->isEmpty() || $levelIds->isEmpty()) {
                return;
            }

            foreach ($levelIds as $levelId) {
                foreach ($subjectIds as $subjectId) {
                    GradeSubject::query()->updateOrCreate([
                        'grade_level_id' => $levelId,
                        'subject_id' => $subjectId,
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.subject-level-mappings.index')
            ->with('success', 'Pemasangan mapel-ke-tingkat berhasil ditambahkan.');
    }

    public function index(Request $request): Response
    {
        $selectedSemesterId = $request->integer('semester_id');
        if ($selectedSemesterId <= 0) {
            $selectedSemesterId = (int) (AcademicPeriod::query()->where('is_active', true)->value('semester_id') ?? 0);
        }
        if ($selectedSemesterId <= 0) {
            $selectedSemesterId = (int) (AcademicPeriod::query()->orderByDesc('id')->value('semester_id') ?? 0);
        }

        return Inertia::render('admin/subject-settings/index', [
            'subjects' => Subject::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'gradeSubjects' => GradeSubject::query()->get(['id', 'grade_level_id', 'subject_id']),
            'classes' => SchoolClass::query()
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name', 'grade_level_id']),
            'levels' => GradeLevel::query()
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name', 'order']),
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
            'selectedSemesterId' => $selectedSemesterId,
            'levelSettings' => SubjectLevelSetting::query()
                ->with('classOverrides:id,level_subject_default_id,class_id,override_hours')
                ->where('period_id', $this->resolvePeriodIdBySemesterId((int) $selectedSemesterId))
                ->get(['id', 'level_id', 'subject_id', 'period_id', 'has_score_default', 'target_jam_default', 'is_mandatory_teaching']),
        ]);
    }

    public function upsertLevel(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'semester_id' => ['required', 'exists:semesters,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'level_id' => ['required', 'exists:grade_levels,id'],
            'is_taught' => ['required', 'boolean'],
            'default_hours' => ['required', 'integer', 'min:0', 'max:24'],
            'is_assessed' => ['required', 'boolean'],
        ]);
        $this->ensureSubjectMappedToLevel((int) $payload['subject_id'], (int) $payload['level_id']);

        SubjectLevelSetting::query()->updateOrCreate(
            [
                'period_id' => $this->resolvePeriodIdBySemesterId((int) $payload['semester_id']),
                'subject_id' => $payload['subject_id'],
                'level_id' => $payload['level_id'],
            ],
            [
                'is_mandatory_teaching' => $payload['is_taught'],
                'target_jam_default' => $payload['default_hours'],
                'has_score_default' => $payload['is_assessed'],
            ]
        );

        return redirect()
            ->route('admin.subject-settings.index', ['semester_id' => $payload['semester_id']])
            ->with('success', 'Setting level mapel berhasil disimpan.');
    }

    public function upsertClassOverride(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'semester_id' => ['required', 'exists:semesters,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'level_id' => ['required', 'exists:grade_levels,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'override_hours' => ['required', 'integer', 'min:0', 'max:24'],
        ]);
        $this->ensureSubjectMappedToLevel((int) $payload['subject_id'], (int) $payload['level_id']);

        $periodId = $this->resolvePeriodIdBySemesterId((int) $payload['semester_id']);

        $levelSetting = SubjectLevelSetting::query()->firstOrCreate(
            [
                'period_id' => $periodId,
                'subject_id' => $payload['subject_id'],
                'level_id' => $payload['level_id'],
            ],
            [
                'is_mandatory_teaching' => true,
                'target_jam_default' => 0,
                'has_score_default' => true,
            ]
        );

        SubjectClassOverride::query()->updateOrCreate(
            [
                'level_subject_default_id' => $levelSetting->id,
                'class_id' => $payload['class_id'],
            ],
            [
                'override_hours' => $payload['override_hours'],
            ]
        );

        return redirect()
            ->route('admin.subject-settings.index', ['semester_id' => $payload['semester_id']])
            ->with('success', 'Override jam per kelas berhasil disimpan.');
    }

    public function assignSubjectToLevel(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'level_id' => ['required', 'exists:grade_levels,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ]);

        GradeSubject::query()->updateOrCreate([
            'subject_id' => $payload['subject_id'],
            'grade_level_id' => $payload['level_id'],
        ]);

        return redirect()
            ->route('admin.subject-settings.index', ['semester_id' => $payload['semester_id'] ?? null])
            ->with('success', 'Mapel berhasil dipasangkan ke tingkat.');
    }

    public function removeSubjectFromLevel(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'level_id' => ['required', 'exists:grade_levels,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ]);

        $gradeSubject = GradeSubject::query()
            ->where('subject_id', $payload['subject_id'])
            ->where('grade_level_id', $payload['level_id'])
            ->first();

        if ($gradeSubject) {
            $gradeSubject->delete();
        }

        return redirect()
            ->route('admin.subject-settings.index', ['semester_id' => $payload['semester_id'] ?? null])
            ->with('success', 'Pasangan mapel-tingkat berhasil dilepas.');
    }

    public function destroyClassOverride(Request $request, SubjectClassOverride $subjectClassOverride): RedirectResponse
    {
        $semesterId = $request->integer('semester_id');
        $subjectClassOverride->delete();

        return redirect()
            ->route('admin.subject-settings.index', ['semester_id' => $semesterId > 0 ? $semesterId : null])
            ->with('success', 'Override jam per kelas berhasil dihapus.');
    }

    protected function resolvePeriodIdBySemesterId(int $semesterId): int
    {
        return (int) DB::table('academic_periods')
            ->where('semester_id', $semesterId)
            ->value('id');
    }

    protected function ensureSubjectMappedToLevel(int $subjectId, int $levelId): void
    {
        $isMapped = GradeSubject::query()
            ->where('subject_id', $subjectId)
            ->where('grade_level_id', $levelId)
            ->exists();

        if (! $isMapped) {
            abort(422, 'Mapel belum dipasangkan ke tingkat ini.');
        }
    }
}
