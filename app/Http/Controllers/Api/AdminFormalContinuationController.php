<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FormalContinuationRound;
use App\Models\StudentFormalContinuationRequest;
use App\Services\FormalContinuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFormalContinuationController extends Controller
{
    public function __construct(
        private readonly FormalContinuationService $continuationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sourceYearId = (int) $request->input('source_academic_year_id', 0);

        $query = StudentFormalContinuationRequest::query()
            ->with([
                'student:id,nis,full_name',
                'currentTingkatSekolah:id,name,code',
                'targetAcademicYear:id,name',
            ])
            ->when($request->status && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at');

        return response()->json([
            'requests' => $query->paginate(20),
            'rounds' => FormalContinuationRound::query()
                ->with(['sourceAcademicYear:id,name', 'targetAcademicYear:id,name'])
                ->orderByDesc('sent_at')
                ->limit(10)
                ->get(),
            'academic_years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'end_date']),
            'preview_eligible_count' => $sourceYearId > 0
                ? $this->continuationService->eligibleStudents($sourceYearId)->count()
                : null,
            'existing_round_source_ids' => FormalContinuationRound::query()->pluck('source_academic_year_id'),
        ]);
    }

    public function sendRound(Request $request): JsonResponse
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

        return response()->json([
            'message' => "Undangan terkirim ke {$result['created']} santri.",
            'round' => $result['round'],
            'created' => $result['created'],
        ]);
    }

    public function approve(Request $request, StudentFormalContinuationRequest $studentFormalContinuation): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->continuationService->approve(
            $studentFormalContinuation,
            $request->user(),
            $validated['admin_notes'] ?? null,
        );

        return response()->json(['message' => 'Disetujui.', 'request' => $updated]);
    }

    public function reject(Request $request, StudentFormalContinuationRequest $studentFormalContinuation): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->continuationService->reject(
            $studentFormalContinuation,
            $request->user(),
            $validated['rejection_reason'],
            $validated['admin_notes'] ?? null,
        );

        return response()->json(['message' => 'Ditolak.', 'request' => $updated]);
    }
}
