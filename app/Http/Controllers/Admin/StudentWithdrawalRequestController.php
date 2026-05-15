<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentWithdrawalRequest;
use App\Services\StudentWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentWithdrawalRequestController extends Controller
{
    public function __construct(
        private readonly StudentWithdrawalService $withdrawalService,
    ) {}

    public function index(Request $request): Response
    {
        $query = StudentWithdrawalRequest::query()
            ->with([
                'student:id,nis,full_name,status',
                'santriUser:id,name',
                'waliUser:id,name',
                'reviewer:id,name',
            ])
            ->when($request->status && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->search, function ($q, $search) {
                $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%"));
            })
            ->orderByDesc('created_at');

        return Inertia::render('admin/student-withdrawals/index', [
            'requests' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function approve(Request $request, StudentWithdrawalRequest $studentWithdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->withdrawalService->approve(
            $studentWithdrawal,
            $request->user(),
            $validated['admin_notes'] ?? null,
        );

        return redirect()->back()->with('success', 'Permohonan keluar pesantren disetujui. Status santri diperbarui.');
    }

    public function reject(Request $request, StudentWithdrawalRequest $studentWithdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->withdrawalService->reject(
            $studentWithdrawal,
            $request->user(),
            $validated['rejection_reason'],
            $validated['admin_notes'] ?? null,
        );

        return redirect()->back()->with('success', 'Permohonan keluar pesantren ditolak.');
    }
}
