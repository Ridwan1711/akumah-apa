<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleCertificateRequest;
use App\Http\Requests\Admin\UpdateRoleCertificateRequest;
use App\Models\AcademicPeriod;
use App\Models\Role;
use App\Models\RoleCertificate;
use App\Models\StudentPosition;
use App\Models\User;
use App\Services\RoleCertificateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View as ViewResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class RoleCertificateController extends Controller
{
    public function __construct(private RoleCertificateService $certificateService) {}

    public function index(Request $request): Response
    {
        $query = RoleCertificate::query()
            ->with([
                'user:id,name',
                'studentPosition:id,student_id,position_type,division_code',
                'studentPosition.student:id,full_name',
                'period:id,academic_year_id',
                'period.academicYear:id,name',
            ])
            ->when($request->filled('search'), function ($builder) use ($request) {
                $search = (string) $request->string('search');
                $builder->where(function ($inner) use ($search) {
                    $inner->where('certificate_number', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'ilike', "%{$search}%"))
                        ->orWhereHas('studentPosition.student', fn ($studentQuery) => $studentQuery->where('full_name', 'ilike', "%{$search}%"));
                });
            })
            ->when($request->filled('certificate_type'), fn ($builder) => $builder->where('certificate_type', (string) $request->string('certificate_type')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', (string) $request->string('status')))
            ->orderByDesc('issued_at')
            ->orderByDesc('id');

        return Inertia::render('admin/role-certificates/index', [
            'certificates' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'certificate_type', 'status']),
            'teachers' => User::query()
                ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', Role::GURU))
                ->orderBy('name')
                ->get(['id', 'name']),
            'positions' => StudentPosition::query()
                ->with('student:id,full_name')
                ->orderByDesc('id')
                ->get(['id', 'student_id', 'position_type', 'division_code']),
            'periods' => AcademicPeriod::query()
                ->with('academicYear:id,name')
                ->orderByDesc('id')
                ->get(['id', 'academic_year_id', 'semester_id', 'is_active']),
        ]);
    }

    public function store(StoreRoleCertificateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $periodId = isset($validated['academic_period_id']) ? (int) $validated['academic_period_id'] : null;
        $certificateType = (string) $validated['certificate_type'];
        $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;
        $studentPositionId = isset($validated['student_position_id']) ? (int) $validated['student_position_id'] : null;
        $stampPath = $request->file('stamp')?->store('role-certificate-stamps', 'public');

        $sourceKey = sprintf(
            '%s:%s:period:%s',
            $certificateType,
            $userId ?? $studentPositionId ?? 'na',
            $periodId ?? 'na'
        );

        $payload = [];
        if ($certificateType === RoleCertificate::TYPE_TEACHER && $userId) {
            $payload['teacher_name'] = User::query()->whereKey($userId)->value('name');
        }
        if ($certificateType === RoleCertificate::TYPE_STUDENT_POSITION && $studentPositionId) {
            $position = StudentPosition::query()->with('student:id,full_name')->find($studentPositionId);
            $payload['student_name'] = $position?->student?->full_name;
            $payload['position_type'] = $position?->position_type;
            $payload['division_code'] = $position?->division_code;
        }

        $this->certificateService->issueManual([
            'certificate_type' => $certificateType,
            'source_key' => $sourceKey,
            'user_id' => $userId,
            'student_position_id' => $studentPositionId,
            'academic_period_id' => $periodId,
            'valid_from' => $validated['valid_from'],
            'valid_until' => $validated['valid_until'],
            'principal_name' => $validated['principal_name'],
            'principal_title' => $validated['principal_title'] ?? 'Kepala Sekolah / Pimpinan',
            'stamp_path' => $stampPath,
            'notes' => $validated['notes'] ?? null,
            'payload' => $payload,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('admin.role-certificates.index')
            ->with('success', 'Surat keterangan berhasil diterbitkan ulang.');
    }

    public function update(UpdateRoleCertificateRequest $request, RoleCertificate $roleCertificate): RedirectResponse
    {
        $validated = $request->validated();
        $stampPath = $request->file('stamp')?->store('role-certificate-stamps', 'public');

        $roleCertificate->update([
            'valid_from' => $validated['valid_from'],
            'valid_until' => $validated['valid_until'],
            'principal_name' => $validated['principal_name'],
            'principal_title' => $validated['principal_title'] ?? 'Kepala Sekolah / Pimpinan',
            'stamp_path' => $stampPath ?? $roleCertificate->stamp_path,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.role-certificates.index')
            ->with('success', 'Surat keterangan berhasil diperbarui.');
    }

    public function preview(RoleCertificate $roleCertificate): ViewResponse
    {
        $this->loadCertificatePreviewData($roleCertificate);

        return view('pdf.role-certificate', [
            'certificate' => $roleCertificate,
        ]);
    }

    public function download(RoleCertificate $roleCertificate): BaseResponse
    {
        $this->loadCertificatePreviewData($roleCertificate);
        $pdf = Pdf::loadView('pdf.role-certificate', [
            'certificate' => $roleCertificate,
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf('surat-keterangan-%s.pdf', $roleCertificate->certificate_number);

        return $pdf->download($fileName);
    }

    private function loadCertificatePreviewData(RoleCertificate $roleCertificate): void
    {
        $roleCertificate->load([
            'user:id,name',
            'studentPosition:id,student_id,position_type,division_code,started_at,ended_at',
            'studentPosition.student:id,full_name',
            'period:id,academic_year_id',
            'period.academicYear:id,name',
        ]);
    }
}
