<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkStudentEnrollmentRequest;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\StudentClassEnrollment;
use App\Models\ImportRun;
use App\Models\Role;
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
        $selectedPeriodId = (int) $request->input('period_id', AcademicPeriod::query()->where('is_active', true)->value('id'));

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
            'classes' => SchoolClass::query()->orderBy('order')->orderBy('name')->get(['id', 'name', 'grade_level_id']),
            'academicPeriods' => AcademicPeriod::query()->orderByDesc('id')->with('academicYear:id,name', 'semester:id,name')->get(['id', 'academic_year_id', 'semester_id', 'is_active']),
            'selectedPeriodId' => $selectedPeriodId,
            'isSuperAdmin' => $request->user()?->hasRole(Role::SUPER_ADMIN) ?? false,
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
        $periodId = (int) $data['period_id'];
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

    /**
     * Hapus seluruh baris enrollment santri × kelas (semua periode). Hanya super admin.
     */
    public function clearAllEnrollments(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(Role::SUPER_ADMIN), 403);

        $request->validate([
            'acknowledge' => ['required', 'in:1,true'],
        ]);

        $deleted = (int) DB::transaction(function (): int {
            $studentIds = StudentClassEnrollment::query()
                ->distinct()
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();

            $count = StudentClassEnrollment::query()->count();
            StudentClassEnrollment::query()->delete();

            if ($studentIds !== []) {
                Student::query()->whereIn('id', $studentIds)->update(['current_class_id' => null]);
            }

            return $count;
        });

        return redirect()
            ->route('admin.student-enrollments.index')
            ->with('success', "Semua enrollment kelas diniyah telah dikosongkan ({$deleted} baris). Kolom kelas saat ini santri yang terdaftar di enrollment tersebut diset kosong.");
    }

    protected function handleBulk(
        BulkStudentEnrollmentRequest $request,
        StudentEnrollmentService $service,
        string $forcedMode
    ): RedirectResponse {
        $data = $request->validated();
        $periodId = (int) $data['period_id'];
        $summary = $service->execute(
            $data['student_ids'],
            isset($data['class_id']) ? (int) $data['class_id'] : null,
            $periodId,
            $forcedMode,
            true,
        );

        return redirect()
            ->route('admin.student-enrollments.index', [
                'period_id' => $periodId,
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'class_id' => $request->input('class_id'),
            ])
            ->with('success', "Bulk {$forcedMode} selesai. Created: {$summary['created']}, Updated: {$summary['updated']}, Cleared: {$summary['cleared']}, Skipped: {$summary['skipped']}, Failed: {$summary['failed']}");
    }

}

