<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaliWithdrawalController extends Controller
{
    public function __construct(
        private readonly StudentWithdrawalService $withdrawalService,
    ) {}

    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorizeChild($request, $student);

        return response()->json([
            'request' => $this->withdrawalService->findOpenRequest($student)
                ?? $this->withdrawalService->latestForStudent($student),
            'open_request' => $this->withdrawalService->findOpenRequest($student),
            'can_apply' => $student->status === Student::STATUS_ACTIVE,
        ]);
    }

    public function submitChoice(Request $request, Student $student): JsonResponse
    {
        $guardian = $this->authorizeChild($request, $student);
        $validated = $request->validate([
            'choice' => ['required', 'in:withdraw,continue'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $open = $this->withdrawalService->openRequest($student, $request->user(), 'wali');

        $updated = $this->withdrawalService->submitWaliChoice(
            $open['request'],
            $request->user(),
            $guardian,
            $validated['choice'],
            $validated['reason'] ?? null,
            $validated['effective_date'] ?? null,
        );

        return response()->json([
            'message' => 'Keputusan wali tersimpan (meng-override keputusan santri jika berbeda).',
            'request' => $updated,
        ]);
    }

    public function cancel(Request $request, Student $student): JsonResponse
    {
        $this->authorizeChild($request, $student);
        $open = $this->withdrawalService->findOpenRequest($student);
        abort_unless($open, 404, 'Tidak ada permohonan aktif.');

        $updated = $this->withdrawalService->cancel($open, $request->user());

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
