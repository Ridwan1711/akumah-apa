<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCellRequest;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\ScheduleSet;
use App\Models\Diniyyah\TeacherAssignment;
use App\Services\Diniyyah\SubjectTeachingHourResolver;
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

    public function assign(AssignCellRequest $request, ScheduleSet $scheduleSet): RedirectResponse
    {
        $data = $request->validated();
        $pengampu = TeacherAssignment::findOrFail($data['pengampu_id']);

        try {
            $this->matrix->assignCell(
                $scheduleSet,
                $pengampu,
                (int) $data['day'],
                (int) $data['jam_no'],
                (string) $data['action']
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cell jadwal disimpan.');
    }

    public function destroyCell(Request $request, ScheduleSet $scheduleSet, AcademicSchedule $schedule)
    {
        abort_unless((int) $schedule->schedule_set_id === (int) $scheduleSet->id, 404);

        $deleteGroup = (bool) $request->boolean('delete_group', false);

        $snapshotRows = $deleteGroup && $schedule->combined_group_id
            ? AcademicSchedule::query()->where('combined_group_id', $schedule->combined_group_id)->get()
            : collect([$schedule]);
        $snapshot = $this->matrix->snapshotCells($snapshotRows);

        $this->matrix->deleteCell($schedule, $deleteGroup);

        if ($request->wantsJson()) {
            return response()->json([
                'deleted' => count($snapshot),
                'snapshot' => $snapshot,
            ]);
        }

        return back()->with('success', 'Cell jadwal dihapus.');
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

        $result = $this->matrix->bulkDeleteByScope($scheduleSet, $filters);

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

        return response()->json($result);
    }
}
