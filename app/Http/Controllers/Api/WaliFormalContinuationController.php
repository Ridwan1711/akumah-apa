<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentFormalContinuationRequest;
use App\Services\FormalContinuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaliFormalContinuationController extends Controller
{
    public function __construct(
        private readonly FormalContinuationService $continuationService,
    ) {}

    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorizeChild($request, $student);
        $open = $this->continuationService->findOpenRequestForStudent($student);

        return response()->json([
            'request' => $open ?? $this->continuationService->latestForStudent($student),
            'open_request' => $open,
            'allowed_choices' => $open
                ? StudentFormalContinuationRequest::allowedChoicesForCode($open->current_tingkat_code)
                : [],
        ]);
    }

    public function submitChoice(Request $request, Student $student): JsonResponse
    {
        $guardian = $this->authorizeChild($request, $student);
        $open = $this->continuationService->findOpenRequestForStudent($student);
        abort_unless($open, 404, 'Tidak ada undangan konfirmasi aktif.');

        $allowed = StudentFormalContinuationRequest::allowedChoicesForCode($open->current_tingkat_code);
        $validated = $request->validate([
            'choice' => ['required', 'in:'.implode(',', $allowed)],
        ]);

        $updated = $this->continuationService->submitWaliChoice(
            $open,
            $request->user(),
            $guardian,
            $validated['choice'],
        );

        return response()->json([
            'message' => 'Pilihan wali tersimpan.',
            'request' => $updated,
        ]);
    }

    public function cancel(Request $request, Student $student): JsonResponse
    {
        $this->authorizeChild($request, $student);
        $open = $this->continuationService->findOpenRequestForStudent($student);
        abort_unless($open, 404, 'Tidak ada permohonan aktif.');

        $updated = $this->continuationService->cancel($open, $request->user());

        return response()->json([
            'message' => 'Permohonan dibatalkan.',
            'request' => $updated,
        ]);
    }

    private function authorizeChild(Request $request, Student $student)
    {
        $guardian = $request->user()->primaryGuardian();
        abort_unless($guardian !== null, 404, 'Data wali tidak ditemukan.');
        abort_unless(
            $guardian->students()->where('students.id', $student->id)->exists(),
            403,
            'Anda tidak memiliki akses ke data santri ini.',
        );

        return $guardian;
    }
}
