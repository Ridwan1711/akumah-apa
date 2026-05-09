<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\GradeSubject;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\ScheduleSet;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\SubjectClassOverride;
use App\Models\Diniyyah\SubjectLevelSetting;
use App\Models\Diniyyah\TeacherAssignment;
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

    /**
     * Hapus satu pasangan (level, mapel) sekaligus membersihkan default per-level
     * dan override per-kelas yang menempel pada pasangan tsb.
     */
    public function destroyMappingPair(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
        ]);

        $report = DB::transaction(fn () => $this->detachMappingPairs([
            ['level_id' => (int) $payload['level_id'], 'subject_id' => (int) $payload['subject_id']],
        ]));

        return redirect()
            ->route('admin.subject-level-mappings.index')
            ->with('success', $this->buildDetachMessage($report));
    }

    /**
     * Hapus banyak pasangan sekaligus.
     *
     * Dua mode dukungan:
     *  - "matrix": kirim level_ids[] & subject_ids[] → cross-product semua pasangan.
     *  - "explicit": kirim pairs[] = [{level_id, subject_id}, ...].
     */
    public function bulkDestroyMappings(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'level_ids' => ['array'],
            'level_ids.*' => ['integer', 'exists:grade_levels,id'],
            'subject_ids' => ['array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'pairs' => ['array'],
            'pairs.*.level_id' => ['required_with:pairs', 'integer', 'exists:grade_levels,id'],
            'pairs.*.subject_id' => ['required_with:pairs', 'integer', 'exists:subjects,id'],
        ]);

        $pairs = [];

        if (! empty($payload['pairs'])) {
            foreach ($payload['pairs'] as $row) {
                $pairs[] = [
                    'level_id' => (int) $row['level_id'],
                    'subject_id' => (int) $row['subject_id'],
                ];
            }
        }

        $levelIds = collect($payload['level_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $subjectIds = collect($payload['subject_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        if ($levelIds->isNotEmpty() && $subjectIds->isNotEmpty()) {
            foreach ($levelIds as $levelId) {
                foreach ($subjectIds as $subjectId) {
                    $pairs[] = ['level_id' => $levelId, 'subject_id' => $subjectId];
                }
            }
        }

        $pairs = collect($pairs)
            ->unique(fn ($pair) => $pair['level_id'].':'.$pair['subject_id'])
            ->values()
            ->all();

        if (empty($pairs)) {
            return redirect()
                ->route('admin.subject-level-mappings.index')
                ->with('error', 'Tidak ada pasangan yang dipilih untuk dihapus.');
        }

        $report = DB::transaction(fn () => $this->detachMappingPairs($pairs));

        return redirect()
            ->route('admin.subject-level-mappings.index')
            ->with('success', $this->buildDetachMessage($report));
    }

    /**
     * Lakukan pelepasan pasangan beserta cascade default & override.
     *
     * @param  array<int, array{level_id: int, subject_id: int}>  $pairs
     * @return array{detached: int, level_settings_deleted: int, overrides_deleted: int, teacher_assignments_orphaned: int}
     */
    private function detachMappingPairs(array $pairs): array
    {
        $detached = 0;
        $levelSettingsDeleted = 0;
        $overridesDeleted = 0;
        $teacherAssignmentsOrphaned = 0;

        foreach ($pairs as $pair) {
            $levelId = (int) $pair['level_id'];
            $subjectId = (int) $pair['subject_id'];

            $deletedRow = GradeSubject::query()
                ->where('grade_level_id', $levelId)
                ->where('subject_id', $subjectId)
                ->delete();

            if ($deletedRow === 0) {
                continue;
            }

            $detached += $deletedRow;

            $levelSettingIds = SubjectLevelSetting::query()
                ->where('level_id', $levelId)
                ->where('subject_id', $subjectId)
                ->pluck('id');

            if ($levelSettingIds->isNotEmpty()) {
                $overridesDeleted += SubjectClassOverride::query()
                    ->whereIn('level_subject_default_id', $levelSettingIds)
                    ->delete();

                $levelSettingsDeleted += SubjectLevelSetting::query()
                    ->whereIn('id', $levelSettingIds)
                    ->delete();
            }

            $teacherAssignmentsOrphaned += TeacherAssignment::query()
                ->where('subject_id', $subjectId)
                ->whereIn(
                    'class_id',
                    SchoolClass::query()->where('grade_level_id', $levelId)->pluck('id')
                )
                ->count();
        }

        return [
            'detached' => $detached,
            'level_settings_deleted' => $levelSettingsDeleted,
            'overrides_deleted' => $overridesDeleted,
            'teacher_assignments_orphaned' => $teacherAssignmentsOrphaned,
        ];
    }

    /**
     * @param  array{detached: int, level_settings_deleted: int, overrides_deleted: int, teacher_assignments_orphaned: int}  $report
     */
    private function buildDetachMessage(array $report): string
    {
        if ($report['detached'] === 0) {
            return 'Tidak ada pasangan yang terhapus (mungkin sudah tidak terpasang).';
        }

        $msg = "{$report['detached']} pasangan mapel-tingkat dilepas.";
        if ($report['level_settings_deleted'] > 0) {
            $msg .= " {$report['level_settings_deleted']} default per-level ikut terhapus.";
        }
        if ($report['overrides_deleted'] > 0) {
            $msg .= " {$report['overrides_deleted']} override per-kelas ikut terhapus.";
        }
        if ($report['teacher_assignments_orphaned'] > 0) {
            $msg .= " Perhatian: {$report['teacher_assignments_orphaned']} penugasan guru masih merujuk pasangan ini — periksa di menu Teaching Assignments.";
        }

        return $msg;
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

        $periodId = $this->resolvePeriodIdBySemesterId((int) $payload['semester_id']);

        SubjectLevelSetting::query()->updateOrCreate(
            [
                'period_id' => $periodId,
                'subject_id' => $payload['subject_id'],
                'level_id' => $payload['level_id'],
            ],
            [
                'is_mandatory_teaching' => $payload['is_taught'],
                'target_jam_default' => $payload['default_hours'],
                'has_score_default' => $payload['is_assessed'],
            ]
        );

        // Sync TeacherAssignment.target_jam for all draft schedule classes in this level
        $sync = $this->syncTargetJamByLevel($periodId, (int) $payload['subject_id'], (int) $payload['level_id']);
        $message = 'Setting level mapel berhasil disimpan.';
        $message .= $this->buildSyncMessage($sync);

        return redirect()
            ->route('admin.subject-settings.index', ['semester_id' => $payload['semester_id']])
            ->with('success', $message);
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

        // Sync TeacherAssignment.target_jam for this specific class
        $sync = $this->syncTargetJamForClass(
            $periodId,
            (int) $payload['subject_id'],
            (int) $payload['class_id'],
            (int) $payload['override_hours'],
        );
        $message = 'Override jam per kelas berhasil disimpan.';
        $message .= $this->buildSyncMessage($sync);

        return redirect()
            ->route('admin.subject-settings.index', ['semester_id' => $payload['semester_id']])
            ->with('success', $message);
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

        // Load relation data before deletion
        $subjectClassOverride->load('levelSetting');
        $levelSetting = $subjectClassOverride->levelSetting;
        $classId = $subjectClassOverride->class_id;

        $subjectClassOverride->delete();

        // Revert TeacherAssignment.target_jam back to level default for this class
        $sync = ['synced' => 0, 'locked' => false];
        if ($levelSetting) {
            $sync = $this->syncTargetJamForClass(
                $levelSetting->period_id,
                $levelSetting->subject_id,
                $classId,
                $levelSetting->target_jam_default,
            );
        }

        $message = 'Override jam per kelas berhasil dihapus.';
        if ($levelSetting && $sync['synced'] > 0) {
            $message .= " Target jam guru dikembalikan ke default ({$levelSetting->target_jam_default} jam/minggu).";
        } elseif ($sync['locked']) {
            $message .= ' Penugasan guru tidak disinkronkan karena jadwal pada periode ini sudah dipublish.';
        }

        return redirect()
            ->route('admin.subject-settings.index', ['semester_id' => $semesterId > 0 ? $semesterId : null])
            ->with('success', $message);
    }

    // -------------------------------------------------------------------------
    // Sync helpers
    // -------------------------------------------------------------------------

    /**
     * Sync TeacherAssignment.target_jam for ALL classes in a level.
     *
     * Classes with a SubjectClassOverride get their override_hours;
     * classes without an override get the level's target_jam_default.
     * Only syncs when the period has NO published (is_active=true) ScheduleSet.
     *
     * @return array{synced: int, locked: bool}
     */
    private function syncTargetJamByLevel(int $periodId, int $subjectId, int $levelId): array
    {
        if ($this->isPeriodLocked($periodId)) {
            return ['synced' => 0, 'locked' => true];
        }

        $levelSetting = SubjectLevelSetting::query()
            ->where(['period_id' => $periodId, 'subject_id' => $subjectId, 'level_id' => $levelId])
            ->first();

        if (! $levelSetting) {
            return ['synced' => 0, 'locked' => false];
        }

        // Map class_id => override_hours for this level setting
        $overrides = SubjectClassOverride::query()
            ->where('level_subject_default_id', $levelSetting->id)
            ->pluck('override_hours', 'class_id');

        $classIds = SchoolClass::query()
            ->where('grade_level_id', $levelId)
            ->pluck('id');

        $synced = 0;
        foreach ($classIds as $classId) {
            $targetJam = $overrides->has($classId)
                ? (int) $overrides->get($classId)
                : $levelSetting->target_jam_default;

            $rows = TeacherAssignment::query()
                ->where(['period_id' => $periodId, 'subject_id' => $subjectId, 'class_id' => $classId])
                ->update(['target_jam' => $targetJam]);

            $synced += $rows;
        }

        return ['synced' => $synced, 'locked' => false];
    }

    /**
     * Sync TeacherAssignment.target_jam for a single class.
     * Only syncs when the period has NO published ScheduleSet.
     *
     * @return array{synced: int, locked: bool}
     */
    private function syncTargetJamForClass(int $periodId, int $subjectId, int $classId, int $targetJam): array
    {
        if ($this->isPeriodLocked($periodId)) {
            return ['synced' => 0, 'locked' => true];
        }

        $rows = TeacherAssignment::query()
            ->where(['period_id' => $periodId, 'subject_id' => $subjectId, 'class_id' => $classId])
            ->update(['target_jam' => $targetJam]);

        return ['synced' => $rows, 'locked' => false];
    }

    /**
     * A period is "locked" when it has at least one published (is_active=true) ScheduleSet.
     * Published schedules are immutable — no auto-sync allowed.
     */
    private function isPeriodLocked(int $periodId): bool
    {
        return ScheduleSet::query()
            ->where('period_id', $periodId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Build a human-readable append to flash messages about the sync result.
     *
     * @param  array{synced: int, locked: bool}  $sync
     */
    private function buildSyncMessage(array $sync): string
    {
        if ($sync['locked']) {
            return ' Penugasan guru tidak disinkronkan — jadwal pada periode ini sudah dipublish (terkunci).';
        }

        if ($sync['synced'] > 0) {
            return " {$sync['synced']} penugasan guru berhasil disinkronkan.";
        }

        return '';
    }

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

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
