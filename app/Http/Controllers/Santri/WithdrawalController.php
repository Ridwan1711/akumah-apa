<?php

namespace App\Http\Controllers\Santri;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly StudentWithdrawalService $withdrawalService,
    ) {}

    public function show(Request $request): Response
    {
        $student = $this->student($request);

        return Inertia::render('santri/withdrawal', [
            'request' => $this->withdrawalService->findOpenRequest($student)
                ?? $this->withdrawalService->latestForStudent($student),
            'openRequest' => $this->withdrawalService->findOpenRequest($student),
            'canApply' => $student->status === Student::STATUS_ACTIVE,
        ]);
    }

    public function submitChoice(Request $request): RedirectResponse
    {
        $student = $this->student($request);
        $validated = $request->validate([
            'choice' => ['required', 'in:withdraw,continue'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $open = $this->withdrawalService->openRequest($student, $request->user(), 'santri');
        $this->withdrawalService->submitSantriChoice(
            $open['request'],
            $request->user(),
            $validated['choice'],
            $validated['reason'] ?? null,
            $validated['effective_date'] ?? null,
        );

        return redirect()->route('santri.withdrawal.show')
            ->with('success', 'Keputusan Anda telah disimpan. Menunggu konfirmasi wali.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $student = $this->student($request);
        $open = $this->withdrawalService->findOpenRequest($student);
        abort_unless($open, 404, 'Tidak ada permohonan aktif.');

        $this->withdrawalService->cancel($open, $request->user());

        return redirect()->route('santri.withdrawal.show')
            ->with('success', 'Permohonan dibatalkan.');
    }

    private function student(Request $request): Student
    {
        $student = $request->user()->student;
        abort_unless($student, 404, 'Data santri tidak ditemukan.');

        return $student;
    }
}
