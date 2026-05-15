<?php

namespace App\Http\Controllers\Wali;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentFormalContinuationRequest;
use App\Services\FormalContinuationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChildFormalContinuationController extends Controller
{
    public function __construct(
        private readonly FormalContinuationService $continuationService,
    ) {}

    public function show(Request $request, Student $student): Response
    {
        $this->authorizeChild($request, $student);
        $open = $this->continuationService->findOpenRequestForStudent($student);

        return Inertia::render('wali/child-formal-continuation', [
            'student' => $student->only(['id', 'full_name', 'nis', 'status']),
            'request' => $open ?? $this->continuationService->latestForStudent($student),
            'openRequest' => $open,
            'allowedChoices' => $open
                ? StudentFormalContinuationRequest::allowedChoicesForCode($open->current_tingkat_code)
                : [],
        ]);
    }

    public function submitChoice(Request $request, Student $student): RedirectResponse
    {
        $guardian = $this->authorizeChild($request, $student);
        $open = $this->continuationService->findOpenRequestForStudent($student);
        abort_unless($open, 404, 'Tidak ada undangan konfirmasi aktif.');

        $allowed = StudentFormalContinuationRequest::allowedChoicesForCode($open->current_tingkat_code);
        $validated = $request->validate([
            'choice' => ['required', 'in:'.implode(',', $allowed)],
        ]);

        $this->continuationService->submitWaliChoice(
            $open,
            $request->user(),
            $guardian,
            $validated['choice'],
        );

        return redirect()->route('wali.children.formal-continuation', $student)
            ->with('success', 'Pilihan wali tersimpan. Keputusan wali meng-override keputusan santri.');
    }

    public function cancel(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeChild($request, $student);
        $open = $this->continuationService->findOpenRequestForStudent($student);
        abort_unless($open, 404, 'Tidak ada permohonan aktif.');

        $this->continuationService->cancel($open, $request->user());

        return redirect()->route('wali.children.formal-continuation', $student)
            ->with('success', 'Permohonan dibatalkan.');
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
