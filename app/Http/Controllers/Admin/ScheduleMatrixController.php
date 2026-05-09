<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCellRequest;
use App\Events\Schedule\CellAssigned;
use App\Events\Schedule\CellDeleted;
use App\Events\Schedule\CellLocked;
use App\Events\Schedule\CellMoved;
use App\Events\Schedule\CellsBulkDeleted;
use App\Events\Schedule\CellsRestored;
use App\Events\Schedule\CellUnlocked;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\ScheduleSet;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\User;
use App\Services\Diniyyah\SubjectTeachingHourResolver;
use App\Services\Schedule\ScheduleLockService;
use App\Services\Schedule\ScheduleMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ScheduleMatrixController extends Controller
{
    public function __construct(
        private ScheduleMatrixService $matrix,
        private ScheduleLockService $locks,
        private SubjectTeachingHourResolver $teachingHourResolver,
    ) {}

    public function edit(ScheduleSet $scheduleSet): Response
    {
        $scheduleSet->load(['period:id,academic_year_id,semester_id,is_active', 'timeSlots']);

        $matrix = $this->matrix->getMatrix($scheduleSet);

        $pengampuList = TeacherAssignment::query()
            ->where('period_id', $scheduleSet->period_id)
            ->with([
                'teacher:id,name',
                'schoolClass:id,name,grade_level_id,order',
                'subject:id,name',
            ])
            ->get()
            ->map(fn (TeacherAssignment $a) => [
                'id' => $a->id,
                'teacher_id' => $a->teacher_id,
                'class_id' => $a->class_id,
                'subject_id' => $a->subject_id,
                'target_jam' => (int) ($a->target_jam ?? 1),
                'target_jam_effective' => $this->teachingHourResolver->resolveForAssignment($a),
                'teacher' => $a->teacher?->only(['id', 'name']),
                'school_class' => $a->schoolClass?->only(['id', 'name', 'grade_level_id', 'order']),
                'subject' => $a->subject?->only(['id', 'name']),
            ])
            ->values();

        return Inertia::render('admin/schedules/editor', [
            'scheduleSet' => [
                'id' => $scheduleSet->id,
                'name' => $scheduleSet->name,
                'period_id' => $scheduleSet->period_id,
                'jam_count' => $scheduleSet->jam_count,
                'day_count' => $scheduleSet->day_count,
                'is_active' => $scheduleSet->is_active,
                'period' => $scheduleSet->period?->only(['id', 'academic_year_id', 'semester_id', 'is_active']),
            ],
            'matrix' => $matrix,
            'pengampuList' => $pengampuList,
        ]);
    }

    public function preflight(Request $request, ScheduleSet $scheduleSet): JsonResponse
    {
        $allowedDays = AcademicSchedule::matrixCalendarDays((int) $scheduleSet->day_count);
        $data = $request->validate([
            'pengampu_id' => ['required', 'exists:teacher_assignments,id'],
            'day' => ['required', 'integer', Rule::in($allowedDays)],
            'jam_no' => ['required', 'integer', 'min:1'],
        ]);

        $pengampu = TeacherAssignment::findOrFail($data['pengampu_id']);
        $result = $this->matrix->checkConflict($scheduleSet, $pengampu, (int) $data['day'], (int) $data['jam_no']);

        return response()->json($result);
    }

    public function assign(AssignCellRequest $request, ScheduleSet $scheduleSet): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $pengampu = TeacherAssignment::findOrFail($data['pengampu_id']);
        $token = $this->extractLockToken($request);

        $lockIssue = $this->checkSlotMutationAllowed(
            request: $request,
            scheduleSetId: (int) $scheduleSet->id,
            classId: (int) $pengampu->class_id,
            day: (int) $data['day'],
            jamNo: (int) $data['jam_no'],
            token: $token,
        );
        if ($lockIssue) {
            if ($request->wantsJson()) {
                return response()->json($lockIssue, (int) $lockIssue['status']);
            }

            return back()->with('error', (string) $lockIssue['message']);
        }

        try {
            $created = $this->matrix->assignCell(
                $scheduleSet,
                $pengampu,
                (int) $data['day'],
                (int) $data['jam_no'],
                (string) $data['action']
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        CellAssigned::dispatch(
            scheduleSetId: (int) $scheduleSet->id,
            cell: $this->cellPayload($created->fresh(['teacher:id,name', 'subject:id,name'])),
            by: $this->actor($request),
        );

        return back()->with('success', 'Cell jadwal disimpan.');
    }

    public function destroyCell(Request $request, ScheduleSet $scheduleSet, AcademicSchedule $schedule)
    {
        abort_unless((int) $schedule->schedule_set_id === (int) $scheduleSet->id, 404);

        $token = $this->extractLockToken($request);
        $lockIssue = $this->checkSlotMutationAllowed(
            request: $request,
            scheduleSetId: (int) $scheduleSet->id,
            classId: (int) $schedule->class_id,
            day: (int) $schedule->day,
            jamNo: (int) $schedule->jam_no,
            token: $token,
        );
        if ($lockIssue) {
            return response()->json($lockIssue, (int) $lockIssue['status']);
        }

        $deleteGroup = (bool) $request->boolean('delete_group', false);

        $snapshotRows = $deleteGroup && $schedule->combined_group_id
            ? AcademicSchedule::query()->where('combined_group_id', $schedule->combined_group_id)->get()
            : collect([$schedule]);
        $snapshot = $this->matrix->snapshotCells($snapshotRows);

        $this->matrix->deleteCell($schedule, $deleteGroup);

        foreach ($snapshot as $item) {
            CellDeleted::dispatch(
                scheduleSetId: (int) $scheduleSet->id,
                cell: $item,
                by: $this->actor($request),
            );
        }

        if ($request->wantsJson()) {
            return response()->json([
                'deleted' => count($snapshot),
                'snapshot' => $snapshot,
            ]);
        }

        return back()->with('success', 'Cell jadwal dihapus.');
    }

    public function moveCell(Request $request, ScheduleSet $scheduleSet): JsonResponse
    {
        $allowedDays = AcademicSchedule::matrixCalendarDays((int) $scheduleSet->day_count);
        $data = $request->validate([
            'source_schedule_id' => ['required', 'integer', 'exists:schedules,id'],
            'target_class_id' => ['required', 'integer', 'exists:classes,id'],
            'target_day' => ['required', 'integer', Rule::in($allowedDays)],
            'target_jam_no' => ['required', 'integer', 'min:1'],
            'mode' => ['nullable', Rule::in(['auto', 'swap', 'replace'])],
        ]);

        /** @var AcademicSchedule|null $source */
        $source = AcademicSchedule::query()
            ->where('schedule_set_id', $scheduleSet->id)
            ->whereKey($data['source_schedule_id'])
            ->first();
        abort_unless((bool) $source, 404, 'Cell sumber tidak ditemukan pada schedule set ini.');
        $token = $this->extractLockToken($request);

        $sourceIssue = $this->checkSlotMutationAllowed(
            request: $request,
            scheduleSetId: (int) $scheduleSet->id,
            classId: (int) $source->class_id,
            day: (int) $source->day,
            jamNo: (int) $source->jam_no,
            token: $token,
        );
        if ($sourceIssue) {
            $sourceIssue['which'] = 'source';

            return response()->json($sourceIssue, (int) $sourceIssue['status']);
        }

        $targetIssue = $this->checkSlotMutationAllowed(
            request: $request,
            scheduleSetId: (int) $scheduleSet->id,
            classId: (int) $data['target_class_id'],
            day: (int) $data['target_day'],
            jamNo: (int) $data['target_jam_no'],
            token: $token,
            requireTokenForOwner: false,
        );
        if ($targetIssue && (int) ($targetIssue['holder']['user_id'] ?? 0) !== (int) $request->user()->id) {
            $targetIssue['which'] = 'target';

            return response()->json($targetIssue, (int) $targetIssue['status']);
        }

        try {
            $result = $this->matrix->moveCell(
                $scheduleSet,
                $source,
                (int) $data['target_class_id'],
                (int) $data['target_day'],
                (int) $data['target_jam_no'],
                (string) ($data['mode'] ?? 'auto'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $sourceSnapshot = collect($result['source_snapshot'] ?? []);
        $targetSnapshot = collect($result['target_snapshot'] ?? []);
        $sourceNew = AcademicSchedule::query()->find($result['new_source_id'] ?? null);
        $targetNew = AcademicSchedule::query()->find($result['new_target_id'] ?? null);

        CellMoved::dispatch(
            scheduleSetId: (int) $scheduleSet->id,
            mode: (string) ($result['mode'] ?? 'move'),
            sourceOld: $sourceSnapshot->first(),
            sourceNew: $sourceNew ? $this->cellPayload($sourceNew->fresh(['teacher:id,name', 'subject:id,name'])) : null,
            targetOld: $targetSnapshot->first(),
            targetNew: $targetNew ? $this->cellPayload($targetNew->fresh(['teacher:id,name', 'subject:id,name'])) : null,
            by: $this->actor($request),
        );

        return response()->json($result);
    }

    public function acquireLock(Request $request, ScheduleSet $scheduleSet): JsonResponse
    {
        $allowedDays = AcademicSchedule::matrixCalendarDays((int) $scheduleSet->day_count);
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'day' => ['required', 'integer', Rule::in($allowedDays)],
            'jam_no' => ['required', 'integer', 'min:1'],
            'ttl_seconds' => ['nullable', 'integer', 'min:3', 'max:30'],
        ]);

        $result = $this->locks->acquire(
            scheduleSetId: (int) $scheduleSet->id,
            classId: (int) $data['class_id'],
            day: (int) $data['day'],
            jamNo: (int) $data['jam_no'],
            user: $request->user(),
            ttlSeconds: (int) ($data['ttl_seconds'] ?? ScheduleLockService::DEFAULT_TTL_SECONDS),
        );

        if (! ($result['acquired'] ?? false)) {
            return response()->json([
                'message' => 'Slot sedang dipegang admin lain.',
                'holder' => $result['lock'] ?? null,
            ], 423);
        }

        CellLocked::dispatch(
            scheduleSetId: (int) $scheduleSet->id,
            lock: $result['lock'],
            by: $this->actor($request),
        );

        return response()->json([
            'token' => $result['lock']['token'],
            'expires_at' => $result['lock']['expires_at'],
            'lock' => $result['lock'],
        ]);
    }

    public function heartbeatLock(Request $request, ScheduleSet $scheduleSet): JsonResponse
    {
        $allowedDays = AcademicSchedule::matrixCalendarDays((int) $scheduleSet->day_count);
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'day' => ['required', 'integer', Rule::in($allowedDays)],
            'jam_no' => ['required', 'integer', 'min:1'],
            'token' => ['required', 'string', 'min:8'],
            'ttl_seconds' => ['nullable', 'integer', 'min:3', 'max:30'],
        ]);

        $ok = $this->locks->extend(
            scheduleSetId: (int) $scheduleSet->id,
            classId: (int) $data['class_id'],
            day: (int) $data['day'],
            jamNo: (int) $data['jam_no'],
            token: (string) $data['token'],
            ttlSeconds: (int) ($data['ttl_seconds'] ?? ScheduleLockService::DEFAULT_TTL_SECONDS),
        );

        if (! $ok) {
            return response()->json(['message' => 'Lock tidak valid atau sudah berakhir.'], 409);
        }

        $lock = $this->locks->peek(
            scheduleSetId: (int) $scheduleSet->id,
            classId: (int) $data['class_id'],
            day: (int) $data['day'],
            jamNo: (int) $data['jam_no'],
        );
        if ($lock) {
            CellLocked::dispatch(
                scheduleSetId: (int) $scheduleSet->id,
                lock: $lock,
                by: $this->actor($request),
            );
        }

        return response()->json(['ok' => true]);
    }

    public function releaseLock(Request $request, ScheduleSet $scheduleSet): JsonResponse
    {
        $allowedDays = AcademicSchedule::matrixCalendarDays((int) $scheduleSet->day_count);
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'day' => ['required', 'integer', Rule::in($allowedDays)],
            'jam_no' => ['required', 'integer', 'min:1'],
            'token' => ['required', 'string', 'min:8'],
        ]);

        $ok = $this->locks->release(
            scheduleSetId: (int) $scheduleSet->id,
            classId: (int) $data['class_id'],
            day: (int) $data['day'],
            jamNo: (int) $data['jam_no'],
            token: (string) $data['token'],
        );

        if ($ok) {
            CellUnlocked::dispatch(
                scheduleSetId: (int) $scheduleSet->id,
                slot: [
                    'class_id' => (int) $data['class_id'],
                    'day' => (int) $data['day'],
                    'jam_no' => (int) $data['jam_no'],
                ],
                by: $this->actor($request),
            );
        }

        return response()->json(['released' => $ok]);
    }

    public function releaseAllLocks(Request $request, ScheduleSet $scheduleSet): JsonResponse
    {
        $released = $this->locks->releaseAllForUser((int) $scheduleSet->id, (int) $request->user()->id);

        return response()->json(['released' => $released]);
    }

    public function bulkDelete(Request $request, ScheduleSet $scheduleSet): JsonResponse
    {
        $allowedDays = AcademicSchedule::matrixCalendarDays((int) $scheduleSet->day_count);
        $data = $request->validate([
            'scope' => ['required', Rule::in(['day', 'jam', 'class', 'day_jam'])],
            'day' => ['nullable', 'integer', Rule::in($allowedDays)],
            'jam_no' => ['nullable', 'integer', 'min:1'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
        ]);

        $filters = [];
        $scope = (string) $data['scope'];
        if ($scope === 'day' || $scope === 'day_jam') {
            abort_unless(! empty($data['day']), 422, 'Parameter day wajib untuk scope ini.');
            $filters['day'] = (int) $data['day'];
        }
        if ($scope === 'jam' || $scope === 'day_jam') {
            abort_unless(! empty($data['jam_no']), 422, 'Parameter jam_no wajib untuk scope ini.');
            $filters['jam_no'] = (int) $data['jam_no'];
        }
        if ($scope === 'class') {
            abort_unless(! empty($data['class_id']), 422, 'Parameter class_id wajib untuk scope ini.');
            $filters['class_id'] = (int) $data['class_id'];
        }

        $locked = AcademicSchedule::query()
            ->where('schedule_set_id', $scheduleSet->id)
            ->when(isset($filters['day']), fn ($q) => $q->where('day', $filters['day']))
            ->when(isset($filters['jam_no']), fn ($q) => $q->where('jam_no', $filters['jam_no']))
            ->when(isset($filters['class_id']), fn ($q) => $q->where('class_id', $filters['class_id']))
            ->get(['class_id', 'day', 'jam_no'])
            ->unique(fn ($row) => "{$row->class_id}:{$row->day}:{$row->jam_no}")
            ->map(function ($row) use ($request, $scheduleSet) {
                $lock = $this->locks->isLockedByOther(
                    scheduleSetId: (int) $scheduleSet->id,
                    classId: (int) $row->class_id,
                    day: (int) $row->day,
                    jamNo: (int) $row->jam_no,
                    currentUserId: (int) $request->user()->id,
                );
                if (! $lock) {
                    return null;
                }

                return [
                    'class_id' => (int) $row->class_id,
                    'day' => (int) $row->day,
                    'jam_no' => (int) $row->jam_no,
                    'holder' => $lock,
                ];
            })
            ->filter()
            ->values();
        if ($locked->isNotEmpty()) {
            return response()->json([
                'status' => 423,
                'message' => 'Sebagian slot dalam scope ini sedang dipegang admin lain.',
                'locked' => $locked->all(),
            ], 423);
        }

        $result = $this->matrix->bulkDeleteByScope($scheduleSet, $filters);

        CellsBulkDeleted::dispatch(
            scheduleSetId: (int) $scheduleSet->id,
            scope: [
                'scope' => $scope,
                'filters' => $filters,
            ],
            count: (int) ($result['deleted'] ?? 0),
            by: $this->actor($request),
        );

        return response()->json($result);
    }

    public function restoreCells(Request $request, ScheduleSet $scheduleSet): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.class_id' => ['required', 'integer', 'exists:classes,id'],
            'items.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'items.*.teacher_id' => ['required', 'integer', 'exists:users,id'],
            'items.*.day' => ['required', 'integer', 'min:1', 'max:7'],
            'items.*.jam_no' => ['required', 'integer', 'min:1'],
            'items.*.time_start' => ['nullable', 'string'],
            'items.*.time_end' => ['nullable', 'string'],
            'items.*.combined_group_id' => ['nullable', 'string'],
        ]);

        try {
            $result = $this->matrix->restoreCells($scheduleSet, $data['items']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        CellsRestored::dispatch(
            scheduleSetId: (int) $scheduleSet->id,
            restored: (int) ($result['restored'] ?? 0),
            skipped: (int) ($result['skipped'] ?? 0),
            by: $this->actor($request),
        );

        return response()->json($result);
    }

    private function actor(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        return [
            'id' => (int) $user->id,
            'name' => (string) ($user->name ?? "User #{$user->id}"),
        ];
    }

    private function cellPayload(AcademicSchedule $row): array
    {
        return [
            'schedule_id' => (int) $row->id,
            'class_id' => (int) $row->class_id,
            'day' => (int) $row->day,
            'jam_no' => (int) $row->jam_no,
            'teacher_id' => (int) $row->teacher_id,
            'teacher_name' => $row->teacher?->name,
            'subject_id' => (int) $row->subject_id,
            'subject_name' => $row->subject?->name,
            'combined_group_id' => $row->combined_group_id,
        ];
    }

    private function extractLockToken(Request $request): ?string
    {
        $token = $request->header('X-Schedule-Lock-Token')
            ?? $request->input('lock_token');

        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        return trim($token);
    }

    private function checkSlotMutationAllowed(
        Request $request,
        int $scheduleSetId,
        int $classId,
        int $day,
        int $jamNo,
        ?string $token,
        bool $requireTokenForOwner = true,
    ): ?array {
        $lock = $this->locks->peek($scheduleSetId, $classId, $day, $jamNo);
        if (! $lock) {
            return null;
        }

        $holderId = (int) ($lock['user_id'] ?? 0);
        $currentUserId = (int) $request->user()->id;
        if ($holderId !== $currentUserId) {
            return [
                'status' => 423,
                'message' => 'Slot sedang dipegang admin lain.',
                'holder' => $lock,
            ];
        }

        if ($requireTokenForOwner && (! $token || ($lock['token'] ?? '') !== $token)) {
            return [
                'status' => 409,
                'message' => 'Lock token tidak valid atau sudah berubah.',
                'holder' => $lock,
            ];
        }

        return null;
    }
}
