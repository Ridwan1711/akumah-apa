<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleFormRequest;
use App\Models\Diniyyah\AcademicSchedule;
use App\Services\Schedule\ScheduleMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminScheduleController extends Controller
{
    /** @var list<string> */
    private const SCHEDULE_RELATIONS = [
        'schoolClass:id,name,student_gender,grade_level_id',
        'schoolClass.gradeLevel:id,name',
        'subject:id,name',
        'teacher:id,name',
        'period:id,academic_year_id,semester_id,is_active',
    ];

    public function __construct(private ScheduleMatrixService $matrix) {}

    public function index(Request $request): JsonResponse
    {
        $query = AcademicSchedule::query()
            ->with(self::SCHEDULE_RELATIONS)
            ->orderBy('day')
            ->orderBy('time_start');

        if ($request->filled('class_id')) {
            $classId = (int) $request->class_id;
            $query->where('class_id', $classId);
        }

        if ($request->filled('teacher_id')) {
            $teacherId = (int) $request->teacher_id;
            $query->where('teacher_id', $teacherId);
        }

        if ($request->filled('day_of_week')) {
            $query->where('day', (int) $request->day_of_week);
        }

        $schedules = $query->paginate(50);

        return response()->json($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(ScheduleFormRequest::scheduleRules());
        $data = array_merge(
            $data,
            $this->matrix->resolveLegacyAttachment(
                (int) $data['period_id'],
                (string) $data['time_start'],
                (string) $data['time_end'],
            )
        );

        $schedule = AcademicSchedule::create($data);

        return response()->json([
            'message' => 'Jadwal berhasil dibuat.',
            'schedule' => $schedule->load(self::SCHEDULE_RELATIONS),
        ], 201);
    }

    public function update(Request $request, AcademicSchedule $schedule): JsonResponse
    {
        $data = $request->validate(ScheduleFormRequest::scheduleRules());
        $data = array_merge(
            $data,
            $this->matrix->resolveLegacyAttachment(
                (int) $data['period_id'],
                (string) $data['time_start'],
                (string) $data['time_end'],
            )
        );

        $schedule->update($data);

        return response()->json([
            'message' => 'Jadwal berhasil diperbarui.',
            'schedule' => $schedule->load(self::SCHEDULE_RELATIONS),
        ]);
    }

    public function destroy(AcademicSchedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json([
            'message' => 'Jadwal berhasil dihapus.',
        ]);
    }
}
