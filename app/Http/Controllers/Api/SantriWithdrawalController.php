<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SantriWithdrawalController extends Controller
{
    public function __construct(
        private readonly StudentWithdrawalService $withdrawalService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $student = $this->student($request);

        return response()->json([
            'request' => $this->withdrawalService->findOpenRequest($student)
                ?? $this->withdrawalService->latestForStudent($student),
            'open_request' => $this->withdrawalService->findOpenRequest($student),
            'can_apply' => $student->status === Student::STATUS_ACTIVE,
        ]);
    }

    public function submitChoice(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $validated = $request->validate([
            'choice' => ['required', 'in:withdraw,continue'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $open = $this->withdrawalService->openRequest($student, $request->user(), 'santri');

        $updated = $this->withdrawalService->submitSantriChoice(
            $open['request'],
            $request->user(),
            $validated['choice'],
            $validated['reason'] ?? null,
            $validated['effective_date'] ?? null,
        );

        return response()->json([
            'message' => 'Keputusan santri tersimpan.',
            'request' => $updated,
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $open = $this->withdrawalService->findOpenRequest($student);
        abort_unless($open, 404, 'Tidak ada permohonan aktif.');

        $updated = $this->withdrawalService->cancel($open, $request->user());

        return response()->json([
            'message' => 'Permohonan dibatalkan.',
            'request' => $updated,
        ]);
    }

    private function student(Request $request): Student
    {
        $student = $request->user()->student;
        abort_unless($student, 404, 'Data santri tidak ditemukan.');

        return $student;
    }
}
