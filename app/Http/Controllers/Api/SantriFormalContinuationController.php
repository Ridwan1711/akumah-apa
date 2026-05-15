<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentFormalContinuationRequest;
use App\Services\FormalContinuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SantriFormalContinuationController extends Controller
{
    public function __construct(
        private readonly FormalContinuationService $continuationService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $open = $this->continuationService->findOpenRequestForStudent($student);

        return response()->json([
            'request' => $open ?? $this->continuationService->latestForStudent($student),
            'open_request' => $open,
            'allowed_choices' => $open
                ? StudentFormalContinuationRequest::allowedChoicesForCode($open->current_tingkat_code)
                : [],
        ]);
    }

    public function submitChoice(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $open = $this->continuationService->findOpenRequestForStudent($student);
        abort_unless($open, 404, 'Tidak ada undangan konfirmasi aktif.');

        $allowed = StudentFormalContinuationRequest::allowedChoicesForCode($open->current_tingkat_code);
        $validated = $request->validate([
            'choice' => ['required', 'in:'.implode(',', $allowed)],
        ]);

        $updated = $this->continuationService->submitSantriChoice(
            $open,
            $request->user(),
            $validated['choice'],
        );

        return response()->json([
            'message' => 'Pilihan santri tersimpan.',
            'request' => $updated,
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $open = $this->continuationService->findOpenRequestForStudent($student);
        abort_unless($open, 404, 'Tidak ada permohonan aktif.');

        $updated = $this->continuationService->cancel($open, $request->user());

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
