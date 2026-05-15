<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FormalContinuationRound;
use App\Models\StudentFormalContinuationRequest;
use App\Services\FormalContinuationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormalContinuationController extends Controller
{
    public function __construct(
        private readonly FormalContinuationService $continuationService,
    ) {}

    public function index(Request $request): Response
    {
        $sourceYearId = (int) $request->input('source_academic_year_id', 0);
        $previewCount = null;
        if ($sourceYearId > 0) {
            $previewCount = $this->continuationService->eligibleStudents($sourceYearId)->count();
        }

        $query = StudentFormalContinuationRequest::query()
            ->with([
                'student:id,nis,full_name,status',
                'currentTingkatSekolah:id,name,code',
                'sourceAcademicYear:id,name',
                'targetAcademicYear:id,name',
                'round:id,trigger_type,sent_at',
            ])
            ->when($request->status && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->search, function ($q, $search) {
                $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%"));
            })
            ->orderByDesc('created_at');

        return Inertia::render('admin/formal-continuation/index', [
            'requests' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['status', 'search']),
            'rounds' => FormalContinuationRound::query()
                ->with(['sourceAcademicYear:id,name,end_date', 'targetAcademicYear:id,name', 'sender:id,name'])
                ->orderByDesc('sent_at')
                ->limit(10)
                ->get(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->orderByDesc('id')->get(['id', 'name', 'start_date', 'end_date']),
            'previewEligibleCount' => $previewCount,
            'previewSourceYearId' => $sourceYearId > 0 ? $sourceYearId : null,
            'existingRoundSourceIds' => FormalContinuationRound::query()->pluck('source_academic_year_id'),
        ]);
    }

    public function sendRound(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_academic_year_id' => ['required', 'exists:academic_years,id'],
            'target_academic_year_id' => ['required', 'exists:academic_years,id', 'different:source_academic_year_id'],
        ]);

        $result = $this->continuationService->sendRound(
            (int) $validated['source_academic_year_id'],
            (int) $validated['target_academic_year_id'],
            FormalContinuationRound::TRIGGER_MANUAL,
            $request->user(),
        );

        return redirect()
            ->route('admin.formal-continuation.index')
            ->with('success', "Undangan terkirim ke {$result['created']} santri (MTs 9 / MA 12).");
    }

    public function approve(Request $request, StudentFormalContinuationRequest $studentFormalContinuation): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->continuationService->approve(
            $studentFormalContinuation,
            $request->user(),
            $validated['admin_notes'] ?? null,
        );

        return redirect()->back()->with('success', 'Konfirmasi lanjut formal disetujui. Enrollment TA tujuan diperbarui.');
    }

    public function reject(Request $request, StudentFormalContinuationRequest $studentFormalContinuation): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->continuationService->reject(
            $studentFormalContinuation,
            $request->user(),
            $validated['rejection_reason'],
            $validated['admin_notes'] ?? null,
        );

        return redirect()->back()->with('success', 'Konfirmasi lanjut formal ditolak.');
    }
}
