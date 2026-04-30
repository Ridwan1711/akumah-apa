<?php

namespace App\Services\Schedule;

use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\ScheduleSet;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\TeacherAssignment;
use App\Services\Diniyyah\SubjectTeachingHourResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ScheduleMatrixService
{
    public function __construct(private SubjectTeachingHourResolver $teachingHourResolver) {}

    public const CONFLICT_NONE = 'none';

    public const CONFLICT_OCCUPIED = 'occupied';

    public const CONFLICT_SAME_SUBJECT_OTHER_CLASS = 'same_subject_other_class';

    public const CONFLICT_DIFFERENT_SUBJECT_OTHER_CLASS = 'different_subject_other_class';

    public const CONFLICT_TARGET_REACHED = 'target_reached';

    public const ACTION_ASSIGN = 'assign';

    public const ACTION_MERGE = 'merge';

    public const ACTION_REPLACE = 'replace_across_classes';

    public const ACTION_REPLACE_CELL = 'replace_cell';

    /**
     * Build full matrix payload used by the editor.
     *
     * @return array{
     *     classes: array<int, array<string, mixed>>,
     *     slots: array<int, array<string, mixed>>,
     *     days: array<int, int>,
     *     cells: array<string, array<string, mixed>>
     * }
     */
    public function getMatrix(ScheduleSet $set): array
    {
        $set->loadMissing('timeSlots');

        $classIds = TeacherAssignment::query()
            ->where('period_id', $set->period_id)
            ->distinct()
            ->pluck('class_id')
            ->merge(
                AcademicSchedule::query()
                    ->where('schedule_set_id', $set->id)
                    ->distinct()
                    ->pluck('class_id')
            )
            ->unique()
            ->values();

        $classes = SchoolClass::query()
            ->when($classIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $classIds))
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'grade_level_id', 'order'])
            ->map(fn (SchoolClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'grade_level_id' => $c->grade_level_id,
                'order' => $c->order,
            ])
            ->values()
            ->all();

        $slots = $set->timeSlots
            ->map(fn ($s) => [
                'id' => $s->id,
                'jam_no' => $s->jam_no,
                'time_start' => substr((string) $s->time_start, 0, 5),
                'time_end' => substr((string) $s->time_end, 0, 5),
            ])
            ->values()
            ->all();

        $days = range(1, (int) $set->day_count);

        $cellsRaw = AcademicSchedule::query()
            ->where('schedule_set_id', $set->id)
            ->with([
                'teacher:id,name',
                'subject:id,name',
            ])
            ->get();

        $cells = [];
        foreach ($cellsRaw as $row) {
            $key = $this->cellKey((int) $row->day, (int) $row->jam_no, (int) $row->class_id);
            $cells[$key] = [
                'schedule_id' => $row->id,
                'class_id' => $row->class_id,
                'day' => (int) $row->day,
                'jam_no' => (int) $row->jam_no,
                'teacher_id' => $row->teacher_id,
                'teacher_name' => $row->teacher?->name,
                'subject_id' => $row->subject_id,
                'subject_name' => $row->subject?->name,
                'combined_group_id' => $row->combined_group_id,
            ];
        }

        return [
            'classes' => $classes,
            'slots' => $slots,
            'days' => $days,
            'cells' => $cells,
        ];
    }

    /**
     * Check conflict for an attempted assignment.
     *
     * @return array{type: string, cell?: array<string, mixed>, conflicts?: array<int, array<string, mixed>>}
     */
    public function checkConflict(ScheduleSet $set, TeacherAssignment $pengampu, int $day, int $jamNo): array
    {
        $this->assertPengampuMatchesSet($set, $pengampu);
        $this->assertDayAndJam($set, $day, $jamNo);
        $allocation = $this->currentAllocation($set, $pengampu);
        $targetJam = $this->teachingHourResolver->resolveForAssignment($pengampu);
        if ($allocation >= $targetJam) {
            return [
                'type' => self::CONFLICT_TARGET_REACHED,
                'allocation' => $allocation,
                'target_jam' => $targetJam,
            ];
        }

        $target = AcademicSchedule::query()
            ->where('schedule_set_id', $set->id)
            ->where('class_id', $pengampu->class_id)
            ->where('day', $day)
            ->where('jam_no', $jamNo)
            ->first();

        $others = AcademicSchedule::query()
            ->where('schedule_set_id', $set->id)
            ->where('teacher_id', $pengampu->teacher_id)
            ->where('day', $day)
            ->where('jam_no', $jamNo)
            ->where('class_id', '!=', $pengampu->class_id)
            ->with(['subject:id,name', 'schoolClass:id,name'])
            ->get();

        if ($others->isEmpty() && $target === null) {
            return ['type' => self::CONFLICT_NONE];
        }

        if ($others->isEmpty() && $target !== null) {
            if ((int) $target->teacher_id === (int) $pengampu->teacher_id
                && (int) $target->subject_id === (int) $pengampu->subject_id) {
                return ['type' => self::CONFLICT_NONE];
            }

            return [
                'type' => self::CONFLICT_OCCUPIED,
                'cell' => [
                    'schedule_id' => $target->id,
                    'teacher_id' => $target->teacher_id,
                    'subject_id' => $target->subject_id,
                ],
            ];
        }

        $sameSubject = $others->every(fn ($o) => (int) $o->subject_id === (int) $pengampu->subject_id);

        if ($sameSubject) {
            return [
                'type' => self::CONFLICT_SAME_SUBJECT_OTHER_CLASS,
                'conflicts' => $others->map(fn ($o) => [
                    'schedule_id' => $o->id,
                    'class_id' => $o->class_id,
                    'class_name' => $o->schoolClass?->name,
                    'subject_id' => $o->subject_id,
                    'subject_name' => $o->subject?->name,
                    'combined_group_id' => $o->combined_group_id,
                ])->all(),
            ];
        }

        return [
            'type' => self::CONFLICT_DIFFERENT_SUBJECT_OTHER_CLASS,
            'conflicts' => $others->map(fn ($o) => [
                'schedule_id' => $o->id,
                'class_id' => $o->class_id,
                'class_name' => $o->schoolClass?->name,
                'subject_id' => $o->subject_id,
                'subject_name' => $o->subject?->name,
            ])->all(),
        ];
    }

    /**
     * Assign a cell according to the given action. Returns the new schedule row id.
     */
    public function assignCell(ScheduleSet $set, TeacherAssignment $pengampu, int $day, int $jamNo, string $action): AcademicSchedule
    {
        $this->assertPengampuMatchesSet($set, $pengampu);
        $this->assertDayAndJam($set, $day, $jamNo);

        return DB::transaction(function () use ($set, $pengampu, $day, $jamNo, $action) {
            $conflict = $this->checkConflict($set, $pengampu, $day, $jamNo);
            if ($conflict['type'] === self::CONFLICT_TARGET_REACHED) {
                throw new InvalidArgumentException('Target jam untuk pengampu ini sudah terpenuhi.');
            }

            $existingTargetCell = AcademicSchedule::query()
                ->where('schedule_set_id', $set->id)
                ->where('class_id', $pengampu->class_id)
                ->where('day', $day)
                ->where('jam_no', $jamNo)
                ->first();

            $slotTimes = $this->resolveSlotTimes($set, $jamNo);

            switch ($action) {
                case self::ACTION_ASSIGN:
                    if ($conflict['type'] !== self::CONFLICT_NONE) {
                        throw new InvalidArgumentException('Konflik terdeteksi, harus memilih tindakan merge/replace.');
                    }

                    return $this->createCell($set, $pengampu, $day, $jamNo, $slotTimes, null);

                case self::ACTION_REPLACE_CELL:
                    if ($existingTargetCell) {
                        $this->deleteScheduleWithCleanup($existingTargetCell);
                    }

                    return $this->createCell($set, $pengampu, $day, $jamNo, $slotTimes, null);

                case self::ACTION_MERGE:
                    if ($conflict['type'] !== self::CONFLICT_SAME_SUBJECT_OTHER_CLASS) {
                        throw new InvalidArgumentException('Aksi merge hanya berlaku saat pelajaran sama di kelas lain.');
                    }

                    $groupId = $this->ensureGroupForMerge($set, $pengampu, $day, $jamNo);

                    if ($existingTargetCell) {
                        $this->deleteScheduleWithCleanup($existingTargetCell);
                    }

                    return $this->createCell($set, $pengampu, $day, $jamNo, $slotTimes, $groupId);

                case self::ACTION_REPLACE:
                    AcademicSchedule::query()
                        ->where('schedule_set_id', $set->id)
                        ->where('teacher_id', $pengampu->teacher_id)
                        ->where('day', $day)
                        ->where('jam_no', $jamNo)
                        ->get()
                        ->each(fn ($row) => $this->deleteScheduleWithCleanup($row));

                    if ($existingTargetCell && $existingTargetCell->exists) {
                        $fresh = $existingTargetCell->fresh();
                        if ($fresh) {
                            $this->deleteScheduleWithCleanup($fresh);
                        }
                    }

                    return $this->createCell($set, $pengampu, $day, $jamNo, $slotTimes, null);

                default:
                    throw new InvalidArgumentException("Aksi tidak dikenal: {$action}");
            }
        });
    }

    public function deleteCell(AcademicSchedule $schedule, bool $deleteGroup = false): int
    {
        return DB::transaction(function () use ($schedule, $deleteGroup) {
            if ($deleteGroup && $schedule->combined_group_id) {
                $count = AcademicSchedule::query()
                    ->where('combined_group_id', $schedule->combined_group_id)
                    ->delete();

                return $count;
            }

            $this->deleteScheduleWithCleanup($schedule);

            return 1;
        });
    }

    public function cellKey(int $day, int $jamNo, int $classId): string
    {
        return "{$day}:{$jamNo}:{$classId}";
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        // Accept formats: H:M, HH:MM, HH:MM:SS, or Carbon-like strings; keep HH:MM:SS
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $value, $m)) {
            $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mm = $m[2];
            $ss = $m[3] ?? '00';

            return "{$h}:{$mm}:{$ss}";
        }

        return $value;
    }

    /**
     * Resolve the active schedule set and jam_no for a legacy write.
     * If no active set exists for the period, create a default one.
     * If the (time_start, time_end) slot is unknown for the set, create a new jam_no.
     *
     * @return array{schedule_set_id: int, jam_no: int}
     */
    public function resolveLegacyAttachment(int $periodId, string $timeStart, string $timeEnd): array
    {
        return DB::transaction(function () use ($periodId, $timeStart, $timeEnd) {
            $set = ScheduleSet::query()
                ->where('period_id', $periodId)
                ->where('is_active', true)
                ->first();

            if (! $set) {
                $set = ScheduleSet::query()
                    ->where('period_id', $periodId)
                    ->orderBy('id')
                    ->first();
            }

            if (! $set) {
                $set = ScheduleSet::create([
                    'period_id' => $periodId,
                    'name' => 'Jadwal Default',
                    'jam_count' => 6,
                    'day_count' => 6,
                    'is_active' => true,
                ]);
            }

            $timeStart = substr((string) $timeStart, 0, 8);
            $timeEnd = substr((string) $timeEnd, 0, 8);

            $normalize = fn (string $v) => strlen($v) === 5 ? $v.':00' : $v;
            $normalized = [
                'time_start' => $normalize($timeStart),
                'time_end' => $normalize($timeEnd),
            ];

            $slot = $set->timeSlots->first(function ($s) use ($normalized) {
                $existingStart = $this->normalizeTime((string) $s->time_start);
                $existingEnd = $this->normalizeTime((string) $s->time_end);

                return $existingStart === $normalized['time_start']
                    && $existingEnd === $normalized['time_end'];
            });
            if (! $slot) {
                $slot = $set->timeSlots()->get()->first(function ($s) use ($normalized) {
                    $existingStart = $this->normalizeTime((string) $s->time_start);
                    $existingEnd = $this->normalizeTime((string) $s->time_end);

                    return $existingStart === $normalized['time_start']
                        && $existingEnd === $normalized['time_end'];
                });
            }

            if (! $slot) {
                $nextJam = ($set->timeSlots()->max('jam_no') ?? 0) + 1;
                $slot = $set->timeSlots()->create([
                    'jam_no' => $nextJam,
                    'time_start' => $normalized['time_start'],
                    'time_end' => $normalized['time_end'],
                ]);
                if ($nextJam > (int) $set->jam_count) {
                    $set->update(['jam_count' => $nextJam]);
                }
            }

            return [
                'schedule_set_id' => (int) $set->id,
                'jam_no' => (int) $slot->jam_no,
            ];
        });
    }

    private function createCell(ScheduleSet $set, TeacherAssignment $pengampu, int $day, int $jamNo, array $slotTimes, ?string $groupId): AcademicSchedule
    {
        return AcademicSchedule::create([
            'schedule_set_id' => $set->id,
            'class_id' => $pengampu->class_id,
            'subject_id' => $pengampu->subject_id,
            'teacher_id' => $pengampu->teacher_id,
            'period_id' => $set->period_id,
            'day' => $day,
            'jam_no' => $jamNo,
            'time_start' => $slotTimes['time_start'],
            'time_end' => $slotTimes['time_end'],
            'combined_group_id' => $groupId,
        ]);
    }

    private function ensureGroupForMerge(ScheduleSet $set, TeacherAssignment $pengampu, int $day, int $jamNo): string
    {
        $existingGroup = AcademicSchedule::query()
            ->where('schedule_set_id', $set->id)
            ->where('teacher_id', $pengampu->teacher_id)
            ->where('subject_id', $pengampu->subject_id)
            ->where('day', $day)
            ->where('jam_no', $jamNo)
            ->whereNotNull('combined_group_id')
            ->value('combined_group_id');

        if ($existingGroup) {
            return (string) $existingGroup;
        }

        $groupId = (string) Str::uuid();

        AcademicSchedule::query()
            ->where('schedule_set_id', $set->id)
            ->where('teacher_id', $pengampu->teacher_id)
            ->where('subject_id', $pengampu->subject_id)
            ->where('day', $day)
            ->where('jam_no', $jamNo)
            ->update(['combined_group_id' => $groupId]);

        return $groupId;
    }

    private function deleteScheduleWithCleanup(AcademicSchedule $schedule): void
    {
        $groupId = $schedule->combined_group_id;
        $schedule->delete();

        if (! $groupId) {
            return;
        }

        $remaining = AcademicSchedule::query()
            ->where('combined_group_id', $groupId)
            ->count();

        if ($remaining <= 1) {
            AcademicSchedule::query()
                ->where('combined_group_id', $groupId)
                ->update(['combined_group_id' => null]);
        }
    }

    /**
     * @return array{time_start: string, time_end: string}
     */
    private function resolveSlotTimes(ScheduleSet $set, int $jamNo): array
    {
        $slot = $set->timeSlots()->where('jam_no', $jamNo)->first();
        if (! $slot) {
            throw new RuntimeException("Slot jam {$jamNo} belum dikonfigurasi pada schedule set ini.");
        }

        return [
            'time_start' => (string) $slot->time_start,
            'time_end' => (string) $slot->time_end,
        ];
    }

    private function assertPengampuMatchesSet(ScheduleSet $set, TeacherAssignment $pengampu): void
    {
        if ((int) $pengampu->period_id !== (int) $set->period_id) {
            throw new InvalidArgumentException('Pengampu tidak berada pada periode yang sama dengan schedule set.');
        }
    }

    private function assertDayAndJam(ScheduleSet $set, int $day, int $jamNo): void
    {
        if ($day < 1 || $day > (int) $set->day_count) {
            throw new InvalidArgumentException("Hari {$day} di luar rentang schedule set (1-{$set->day_count}).");
        }
        if ($jamNo < 1 || $jamNo > (int) $set->jam_count) {
            throw new InvalidArgumentException("Jam {$jamNo} di luar rentang schedule set (1-{$set->jam_count}).");
        }
    }

    private function currentAllocation(ScheduleSet $set, TeacherAssignment $pengampu): int
    {
        return AcademicSchedule::query()
            ->where('schedule_set_id', $set->id)
            ->where('class_id', $pengampu->class_id)
            ->where('subject_id', $pengampu->subject_id)
            ->where('teacher_id', $pengampu->teacher_id)
            ->count();
    }
}
