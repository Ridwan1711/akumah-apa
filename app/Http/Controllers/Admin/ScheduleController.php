<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleFormRequest;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use App\Services\Diniyyah\SubjectTeachingHourResolver;
use App\Services\Schedule\ScheduleMatrixService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        private ScheduleMatrixService $matrix,
        private SubjectTeachingHourResolver $teachingHourResolver,
    ) {}

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

        $query = AcademicSchedule::query()
            ->whereIn('day', AcademicSchedule::TEACHING_DAYS)
            ->with([
                'schoolClass:id,name,grade_level_id',
                'subject:id,name',
                'teacher:id,name',
                'period:id,academic_year_id,semester_id,is_active',
            ])
            ->when($selectedPeriodId > 0, fn ($q) => $q->where('period_id', $selectedPeriodId))
            ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', (int) $request->class_id))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', (int) $request->teacher_id))
            ->when(
                $request->filled('day_of_week') && in_array((int) $request->day_of_week, AcademicSchedule::TEACHING_DAYS, true),
                fn ($q) => $q->where('day', (int) $request->day_of_week)
            )
            ->orderBy('day')
            ->orderBy('time_start');

        return Inertia::render('admin/schedules/index', [
            'schedules' => $query->paginate(25)->withQueryString(),
            'classes' => SchoolClass::query()->orderBy('order')->orderBy('name')->get(['id', 'name', 'grade_level_id']),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'teachers' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereNotIn('name', [Role::SANTRI]))
                ->orderBy('name')
                ->get(['id', 'name']),
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
            'filters' => $request->only(['class_id', 'teacher_id', 'day_of_week']),
        ]);
    }

    public function store(ScheduleFormRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $periodId = $this->resolvePeriodIdBySemesterId((int) $payload['semester_id']);
        unset($payload['semester_id']);
        $payload['period_id'] = $periodId;
        $payload = array_merge(
            $payload,
            $this->matrix->resolveLegacyAttachment(
                (int) $payload['period_id'],
                (string) $payload['time_start'],
                (string) $payload['time_end'],
            )
        );
        $this->assertLegacyAllocationLimit($payload, null);
        AcademicSchedule::create($payload);

        return redirect()->route('admin.schedules.index', [
            'semester_id' => $this->resolveSemesterIdByPeriodId((int) $payload['period_id']),
        ])->with('success', 'Jadwal berhasil ditambahkan.');
    }

    public function update(ScheduleFormRequest $request, AcademicSchedule $schedule): RedirectResponse
    {
        $payload = $request->validated();
        $periodId = $this->resolvePeriodIdBySemesterId((int) $payload['semester_id']);
        unset($payload['semester_id']);
        $payload['period_id'] = $periodId;
        $payload = array_merge(
            $payload,
            $this->matrix->resolveLegacyAttachment(
                (int) $payload['period_id'],
                (string) $payload['time_start'],
                (string) $payload['time_end'],
            )
        );
        $this->assertLegacyAllocationLimit($payload, $schedule);
        $schedule->update($payload);

        return redirect()->route('admin.schedules.index', array_filter(
            array_merge(
                ['semester_id' => $this->resolveSemesterIdByPeriodId((int) $payload['period_id'])],
                $request->only(['class_id', 'teacher_id', 'day_of_week'])
            ),
            fn ($v) => $v !== null && $v !== ''
        ))->with('success', 'Jadwal berhasil diperbarui.');
    }

    public function destroy(Request $request, AcademicSchedule $schedule): RedirectResponse
    {
        $semesterId = (int) $request->input('semester_id', $this->resolveSemesterIdByPeriodId((int) $schedule->period_id));
        $schedule->delete();

        return redirect()->route('admin.schedules.index', [
            'semester_id' => $semesterId > 0 ? $semesterId : null,
        ])->with('success', 'Jadwal berhasil dihapus.');
    }

    protected function resolvePeriodIdBySemesterId(int $semesterId): int
    {
        return (int) DB::table('academic_periods')
            ->where('semester_id', $semesterId)
            ->value('id');
    }

    protected function resolveSemesterIdByPeriodId(int $periodId): int
    {
        return (int) DB::table('academic_periods')
            ->where('id', $periodId)
            ->value('semester_id');
    }

    /**
     * Ensure legacy schedule CRUD cannot exceed target teaching hours.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function assertLegacyAllocationLimit(array $payload, ?AcademicSchedule $ignoreSchedule): void
    {
        $assignment = TeacherAssignment::query()
            ->where('period_id', (int) $payload['period_id'])
            ->where('class_id', (int) $payload['class_id'])
            ->where('subject_id', (int) $payload['subject_id'])
            ->where('teacher_id', (int) $payload['teacher_id'])
            ->first();

        if (! $assignment) {
            abort(422, 'Pengampu tidak ditemukan. Silakan pasangkan guru dulu di Penugasan Guru.');
        }

        $targetJam = $this->teachingHourResolver->resolveForAssignment($assignment);
        $allocation = AcademicSchedule::query()
            ->where('schedule_set_id', (int) $payload['schedule_set_id'])
            ->where('class_id', (int) $payload['class_id'])
            ->where('subject_id', (int) $payload['subject_id'])
            ->where('teacher_id', (int) $payload['teacher_id'])
            ->when($ignoreSchedule !== null, fn ($query) => $query->where('id', '!=', $ignoreSchedule->id))
            ->count();

        if ($allocation >= $targetJam) {
            abort(422, 'Target jam pengampu sudah terpenuhi untuk schedule set ini.');
        }
    }
}
