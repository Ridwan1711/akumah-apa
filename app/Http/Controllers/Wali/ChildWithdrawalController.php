<?php

namespace App\Http\Controllers\Wali;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChildWithdrawalController extends Controller
{
    public function __construct(
        private readonly StudentWithdrawalService $withdrawalService,
    ) {}

    public function show(Request $request, Student $student): Response
    {
        $this->authorizeChild($request, $student);

        return Inertia::render('wali/child-withdrawal', [
            'student' => $student->only(['id', 'full_name', 'nis', 'status']),
            'request' => $this->withdrawalService->findOpenRequest($student)
                ?? $this->withdrawalService->latestForStudent($student),
            'openRequest' => $this->withdrawalService->findOpenRequest($student),
            'canApply' => $student->status === Student::STATUS_ACTIVE,
        ]);
    }

    public function submitChoice(Request $request, Student $student): RedirectResponse
    {
        $guardian = $this->authorizeChild($request, $student);
        $validated = $request->validate([
            'choice' => ['required', 'in:withdraw,continue'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $open = $this->withdrawalService->openRequest($student, $request->user(), 'wali');
        $this->withdrawalService->submitWaliChoice(
            $open['request'],
            $request->user(),
            $guardian,
            $validated['choice'],
            $validated['reason'] ?? null,
            $validated['effective_date'] ?? null,
        );

        return redirect()->route('wali.children.withdrawal', $student)
            ->with('success', 'Keputusan wali tersimpan. Keputusan wali meng-override keputusan santri.');
    }

    public function cancel(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeChild($request, $student);
        $open = $this->withdrawalService->findOpenRequest($student);
        abort_unless($open, 404, 'Tidak ada permohonan aktif.');

        $this->withdrawalService->cancel($open, $request->user());

        return redirect()->route('wali.children.withdrawal', $student)
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
