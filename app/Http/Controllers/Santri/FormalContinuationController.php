<?php

namespace App\Http\Controllers\Santri;

use App\Http\Controllers\Controller;
use App\Models\Student;
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

    public function show(Request $request): Response
    {
        $student = $this->student($request);
        $open = $this->continuationService->findOpenRequestForStudent($student);

        return Inertia::render('santri/formal-continuation', [
            'request' => $open ?? $this->continuationService->latestForStudent($student),
            'openRequest' => $open,
            'allowedChoices' => $open
                ? StudentFormalContinuationRequest::allowedChoicesForCode($open->current_tingkat_code)
                : [],
        ]);
    }

    public function submitChoice(Request $request): RedirectResponse
    {
        $student = $this->student($request);
        $open = $this->continuationService->findOpenRequestForStudent($student);
        abort_unless($open, 404, 'Tidak ada undangan konfirmasi aktif.');

        $allowed = StudentFormalContinuationRequest::allowedChoicesForCode($open->current_tingkat_code);
        $validated = $request->validate([
            'choice' => ['required', 'in:'.implode(',', $allowed)],
        ]);

        $this->continuationService->submitSantriChoice($open, $request->user(), $validated['choice']);

        return redirect()->route('santri.formal-continuation.show')
            ->with('success', 'Pilihan santri tersimpan. Menunggu konfirmasi wali.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $student = $this->student($request);
        $open = $this->continuationService->findOpenRequestForStudent($student);
        abort_unless($open, 404, 'Tidak ada permohonan aktif.');

        $this->continuationService->cancel($open, $request->user());

        return redirect()->route('santri.formal-continuation.show')
            ->with('success', 'Permohonan dibatalkan.');
    }

    private function student(Request $request): Student
    {
        $student = $request->user()->student;
        abort_unless($student, 404, 'Data santri tidak ditemukan.');

        return $student;
    }
}
