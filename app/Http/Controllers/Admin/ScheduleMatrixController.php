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
        $data = $request->validate([
            'pengampu_id' => ['required', 'exists:teacher_assignments,id'],
            'day' => ['required', 'integer', 'between:1,7'],
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

    public function destroyCell(Request $request, ScheduleSet $scheduleSet, AcademicSchedule $schedule): RedirectResponse
    {
        abort_unless((int) $schedule->schedule_set_id === (int) $scheduleSet->id, 404);

        $deleteGroup = (bool) $request->boolean('delete_group', false);
        $this->matrix->deleteCell($schedule, $deleteGroup);

        return back()->with('success', 'Cell jadwal dihapus.');
    }
}
