<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkStudentEnrollmentRequest;
use App\Models\AcademicPeriod;
use App\Models\Semester;
use App\Models\Diniyyah\SchoolClass;
use App\Models\ImportRun;
use App\Models\Student;
use App\Services\Diniyyah\StudentEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentEnrollmentController extends Controller
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
        $selectedSemesterId = (int) (DB::table('academic_periods')
            ->where('id', $selectedPeriodId)
            ->value('semester_id') ?? 0);

        $students = Student::query()
            ->with([
                'currentClass:id,name',
                'classEnrollments' => fn ($query) => $query
                    ->where('period_id', $selectedPeriodId)
                    ->with('schoolClass:id,name')
                    ->select(['id', 'student_id', 'class_id', 'period_id']),
            ])
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%");
            }))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->class_id, fn ($q, $classId) => $q->where('current_class_id', $classId))
            ->orderBy('full_name')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/student-enrollments/index', [
            'students' => $students,
            'classes' => SchoolClass::orderBy('level_order')->orderBy('name')->get(['id', 'name', 'level']),
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
            'filters' => $request->only(['search', 'status', 'class_id']),
            'importRuns' => ImportRun::query()
                ->with('requestedBy:id,name')
                ->where('type', ImportRun::TYPE_ENROLLMENTS)
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function preview(BulkStudentEnrollmentRequest $request, StudentEnrollmentService $service): JsonResponse
    {
        $data = $request->validated();
        $periodId = $this->resolvePeriodIdBySemesterId((int) $data['semester_id']);
        $summary = $service->preview(
            $data['student_ids'],
            isset($data['class_id']) ? (int) $data['class_id'] : null,
            $periodId,
            $data['mode'],
        );

        return response()->json($summary);
    }

    public function bulkAssign(BulkStudentEnrollmentRequest $request, StudentEnrollmentService $service): RedirectResponse
    {
        return $this->handleBulk($request, $service, StudentEnrollmentService::MODE_ASSIGN);
    }

    public function bulkMove(BulkStudentEnrollmentRequest $request, StudentEnrollmentService $service): RedirectResponse
    {
        return $this->handleBulk($request, $service, StudentEnrollmentService::MODE_MOVE);
    }

    public function bulkClear(BulkStudentEnrollmentRequest $request, StudentEnrollmentService $service): RedirectResponse
    {
        return $this->handleBulk($request, $service, StudentEnrollmentService::MODE_CLEAR);
    }

    protected function handleBulk(
        BulkStudentEnrollmentRequest $request,
        StudentEnrollmentService $service,
        string $forcedMode
    ): RedirectResponse {
        $data = $request->validated();
        $periodId = $this->resolvePeriodIdBySemesterId((int) $data['semester_id']);
        $summary = $service->execute(
            $data['student_ids'],
            isset($data['class_id']) ? (int) $data['class_id'] : null,
            $periodId,
            $forcedMode,
            true,
        );

        return redirect()->route('admin.student-enrollments.index', ['semester_id' => (int) $data['semester_id']])
            ->with('success', "Bulk {$forcedMode} selesai. Created: {$summary['created']}, Updated: {$summary['updated']}, Cleared: {$summary['cleared']}, Skipped: {$summary['skipped']}, Failed: {$summary['failed']}");
    }

    protected function resolvePeriodIdBySemesterId(int $semesterId): int
    {
        $periodId = (int) DB::table('academic_periods')
            ->where('semester_id', $semesterId)
            ->value('id');

        if ($periodId > 0) {
            return $periodId;
        }

        $semester = Semester::query()->findOrFail($semesterId);
        $normalizedName = strtolower($semester->name);
        $isSemesterTwo = str_contains($normalizedName, 'genap')
            || str_contains($normalizedName, '2')
            || str_contains($normalizedName, 'ii');

        $period = AcademicPeriod::query()->create([
            'name' => $semester->name,
            'type' => $isSemesterTwo ? AcademicPeriod::TYPE_SEMESTER_2 : AcademicPeriod::TYPE_SEMESTER_1,
            'is_active' => (bool) $semester->is_active,
            'semester_id' => $semester->id,
        ]);

        return (int) $period->id;
    }
}

