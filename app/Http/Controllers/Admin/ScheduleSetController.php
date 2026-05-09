<?php

namespace App\Http\Controllers\Admin;

use App\Events\Schedule\TimeSlotsUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTimeSlotsRequest;
use App\Http\Requests\Admin\StoreScheduleSetRequest;
use App\Http\Requests\Admin\UpdateScheduleSetRequest;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\ScheduleSet;
use App\Models\Diniyyah\ScheduleTimeSlot;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\Semester;
use App\Services\Diniyyah\SubjectTeachingHourResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleSetController extends Controller
{
    public function __construct(private SubjectTeachingHourResolver $teachingHourResolver) {}

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

        $sets = ScheduleSet::query()
            ->when($selectedPeriodId > 0, fn ($q) => $q->where('period_id', $selectedPeriodId))
            ->withCount('schedules as cells_count')
            ->with(['period:id,academic_year_id,semester_id,is_active', 'creator:id,name'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
        $coverageBySetId = $this->buildCoverageBySetId($sets, $selectedPeriodId);
        $sets = $sets->map(function (ScheduleSet $set) use ($coverageBySetId): ScheduleSet {
            $coverage = $coverageBySetId[$set->id] ?? ['unmet_pengampu_count' => 0, 'unmet_jam_total' => 0];
            $set->setAttribute('unmet_pengampu_count', (int) $coverage['unmet_pengampu_count']);
            $set->setAttribute('unmet_jam_total', (int) $coverage['unmet_jam_total']);

            return $set;
        });

        return Inertia::render('admin/schedules/sets-index', [
            'sets' => $sets,
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

    public function store(StoreScheduleSetRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        $set = DB::transaction(function () use ($payload, $request) {
            if (! empty($payload['is_active'])) {
                ScheduleSet::query()
                    ->where('period_id', $this->resolvePeriodIdBySemesterId((int) $payload['semester_id']))
                    ->update(['is_active' => false]);
            }

            $set = ScheduleSet::create([
                'period_id' => $this->resolvePeriodIdBySemesterId((int) $payload['semester_id']),
                'name' => $payload['name'],
                'jam_count' => $payload['jam_count'] ?? 6,
                'day_count' => $payload['day_count'] ?? 6,
                'is_active' => (bool) ($payload['is_active'] ?? false),
                'created_by' => $request->user()?->id,
            ]);

            if (! empty($payload['copy_from_id'])) {
                $this->copyFromSet((int) $payload['copy_from_id'], $set);
            }

            return $set;
        });

        return redirect()
            ->route('admin.schedule-sets.editor', $set)
            ->with('success', 'Schedule set berhasil dibuat.');
    }

    public function update(UpdateScheduleSetRequest $request, ScheduleSet $scheduleSet): RedirectResponse
    {
        $payload = $request->validated();

        $scheduleSet->update([
            'name' => $payload['name'],
            'jam_count' => $payload['jam_count'] ?? $scheduleSet->jam_count,
            'day_count' => $payload['day_count'] ?? $scheduleSet->day_count,
        ]);

        return back()->with('success', 'Schedule set berhasil diperbarui.');
    }

    public function activate(ScheduleSet $scheduleSet): RedirectResponse
    {
        DB::transaction(function () use ($scheduleSet) {
            ScheduleSet::query()
                ->where('period_id', $scheduleSet->period_id)
                ->update(['is_active' => false]);
            $scheduleSet->update(['is_active' => true]);
        });

        return back()->with('success', 'Schedule set ditandai aktif.');
    }

    public function destroy(ScheduleSet $scheduleSet): RedirectResponse
    {
        $semesterId = $this->resolveSemesterIdByPeriodId((int) $scheduleSet->period_id);
        $scheduleSet->delete();

        return redirect()
            ->route('admin.schedule-sets.index', ['semester_id' => $semesterId])
            ->with('success', 'Schedule set dihapus.');
    }

    public function saveTimeSlots(SaveTimeSlotsRequest $request, ScheduleSet $scheduleSet): RedirectResponse
    {
        $payload = $request->validated();

        DB::transaction(function () use ($payload, $scheduleSet) {
            $scheduleSet->update([
                'jam_count' => $payload['jam_count'],
                'day_count' => $payload['day_count'],
            ]);

            $incomingByJam = collect($payload['slots'])->keyBy('jam_no');
            $existing = $scheduleSet->timeSlots()->get()->keyBy('jam_no');

            foreach ($incomingByJam as $jamNo => $slot) {
                if ($existing->has($jamNo)) {
                    $existing->get($jamNo)->update([
                        'time_start' => $slot['time_start'],
                        'time_end' => $slot['time_end'],
                    ]);
                } else {
                    ScheduleTimeSlot::create([
                        'schedule_set_id' => $scheduleSet->id,
                        'jam_no' => $jamNo,
                        'time_start' => $slot['time_start'],
                        'time_end' => $slot['time_end'],
                    ]);
                }
            }

            $removed = $existing->keys()->diff($incomingByJam->keys());
            if ($removed->isNotEmpty()) {
                ScheduleTimeSlot::query()
                    ->where('schedule_set_id', $scheduleSet->id)
                    ->whereIn('jam_no', $removed)
                    ->delete();

                AcademicSchedule::query()
                    ->where('schedule_set_id', $scheduleSet->id)
                    ->whereIn('jam_no', $removed)
                    ->delete();
            }

            foreach ($incomingByJam as $jamNo => $slot) {
                AcademicSchedule::query()
                    ->where('schedule_set_id', $scheduleSet->id)
                    ->where('jam_no', $jamNo)
                    ->update([
                        'time_start' => $slot['time_start'],
                        'time_end' => $slot['time_end'],
                    ]);
            }
        });

        TimeSlotsUpdated::dispatch(
            scheduleSetId: (int) $scheduleSet->id,
            slots: collect($payload['slots'])
                ->map(fn (array $slot) => [
                    'jam_no' => (int) $slot['jam_no'],
                    'time_start' => (string) $slot['time_start'],
                    'time_end' => (string) $slot['time_end'],
                ])
                ->values()
                ->all(),
            dayCount: (int) $payload['day_count'],
            by: [
                'id' => (int) $request->user()->id,
                'name' => (string) ($request->user()->name ?? "User #{$request->user()->id}"),
            ],
        );

        return back()->with('success', 'Pengaturan jam berhasil disimpan.');
    }

    private function copyFromSet(int $sourceId, ScheduleSet $target): void
    {
        $source = ScheduleSet::with(['timeSlots', 'schedules'])->find($sourceId);
        if (! $source) {
            return;
        }

        $target->update([
            'jam_count' => $source->jam_count,
            'day_count' => $source->day_count,
        ]);

        foreach ($source->timeSlots as $slot) {
            ScheduleTimeSlot::create([
                'schedule_set_id' => $target->id,
                'jam_no' => $slot->jam_no,
                'time_start' => $slot->time_start,
                'time_end' => $slot->time_end,
            ]);
        }

        foreach ($source->schedules as $row) {
            AcademicSchedule::create([
                'schedule_set_id' => $target->id,
                'class_id' => $row->class_id,
                'subject_id' => $row->subject_id,
                'teacher_id' => $row->teacher_id,
                'period_id' => $target->period_id,
                'day' => $row->day,
                'jam_no' => $row->jam_no,
                'time_start' => $row->time_start,
                'time_end' => $row->time_end,
                'combined_group_id' => $row->combined_group_id,
            ]);
        }
    }

    private function resolvePeriodIdBySemesterId(int $semesterId): int
    {
        return (int) DB::table('academic_periods')
            ->where('semester_id', $semesterId)
            ->value('id');
    }

    private function resolveSemesterIdByPeriodId(int $periodId): int
    {
        return (int) DB::table('academic_periods')
            ->where('id', $periodId)
            ->value('semester_id');
    }

    /**
     * @return array<int, array{unmet_pengampu_count: int, unmet_jam_total: int}>
     */
    private function buildCoverageBySetId(Collection $sets, int $periodId): array
    {
        $setIds = $sets->pluck('id')->map(fn ($id): int => (int) $id)->values();
        if ($periodId <= 0 || $setIds->isEmpty()) {
            return [];
        }

        $assignments = TeacherAssignment::query()
            ->where('period_id', $periodId)
            ->get(['id', 'teacher_id', 'class_id', 'subject_id', 'period_id', 'target_jam']);

        if ($assignments->isEmpty()) {
            return $setIds->mapWithKeys(fn (int $setId): array => [
                $setId => ['unmet_pengampu_count' => 0, 'unmet_jam_total' => 0],
            ])->all();
        }

        $allocationRows = AcademicSchedule::query()
            ->selectRaw('schedule_set_id, teacher_id, class_id, subject_id, COUNT(*) as allocated')
            ->whereIn('schedule_set_id', $setIds)
            ->groupBy('schedule_set_id', 'teacher_id', 'class_id', 'subject_id')
            ->get();

        $allocationMap = [];
        foreach ($allocationRows as $row) {
            $key = sprintf(
                '%d:%d:%d:%d',
                (int) $row->schedule_set_id,
                (int) $row->teacher_id,
                (int) $row->class_id,
                (int) $row->subject_id
            );
            $allocationMap[$key] = (int) $row->allocated;
        }

        $coverageBySetId = [];
        foreach ($setIds as $setId) {
            $unmetPengampuCount = 0;
            $unmetJamTotal = 0;

            foreach ($assignments as $assignment) {
                /** @var TeacherAssignment $assignment */
                $targetJam = $this->teachingHourResolver->resolveForAssignment($assignment);
                $allocationKey = sprintf(
                    '%d:%d:%d:%d',
                    $setId,
                    (int) $assignment->teacher_id,
                    (int) $assignment->class_id,
                    (int) $assignment->subject_id
                );
                $allocated = $allocationMap[$allocationKey] ?? 0;
                $remaining = max(0, $targetJam - $allocated);

                if ($remaining > 0) {
                    $unmetPengampuCount++;
                    $unmetJamTotal += $remaining;
                }
            }

            $coverageBySetId[$setId] = [
                'unmet_pengampu_count' => $unmetPengampuCount,
                'unmet_jam_total' => $unmetJamTotal,
            ];
        }

        return $coverageBySetId;
    }
}
