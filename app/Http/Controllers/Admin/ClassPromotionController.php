<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diniyyah\ClassPromotionRecap;
use App\Services\Diniyyah\ClassPromotionPlacementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassPromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->input('status', ClassPromotionRecap::STATUS_SUBMITTED);
        if (! in_array($status, ClassPromotionRecap::STATUSES, true) && $status !== 'all') {
            $status = ClassPromotionRecap::STATUS_SUBMITTED;
        }

        $recaps = ClassPromotionRecap::query()
            ->with([
                'sourceClass:id,name',
                'period:id,academic_year_id,semester_id',
                'period.academicYear:id,name',
                'period.semester:id,name',
                'submitter:id,name',
                'reviewer:id,name',
            ])
            ->withCount('items')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $selectedRecap = null;
        if ($request->filled('recap_id')) {
            $selectedRecap = ClassPromotionRecap::query()
                ->with([
                    'sourceClass:id,name',
                    'period:id,academic_year_id,semester_id',
                    'period.academicYear:id,name',
                    'period.semester:id,name',
                    'submitter:id,name',
                    'reviewer:id,name',
                    'items.student:id,nis,full_name,gender',
                    'items.targetClass:id,name',
                    'items.appliedClass:id,name',
                ])
                ->find((int) $request->integer('recap_id'));
        }

        return Inertia::render('admin/class-promotion/index', [
            'recaps' => $recaps,
            'selectedRecap' => $selectedRecap,
            'filters' => ['status' => $status],
            'statusOptions' => [
                'all',
                ...ClassPromotionRecap::STATUSES,
            ],
        ]);
    }

    public function approve(
        ClassPromotionRecap $classPromotionRecap,
        Request $request,
        ClassPromotionPlacementService $placementService,
    ): RedirectResponse {
        abort_unless($classPromotionRecap->status === ClassPromotionRecap::STATUS_SUBMITTED, 422, 'Hanya rekap submitted yang bisa disetujui.');

        $placementService->approve($classPromotionRecap, $request->user()->id);

        return redirect()->route('admin.class-promotion.index', [
            'status' => ClassPromotionRecap::STATUS_APPROVED,
            'recap_id' => $classPromotionRecap->id,
        ])->with('success', 'Rekap kenaikan kelas disetujui dan penempatan santri diproses.');
    }

    public function reject(ClassPromotionRecap $classPromotionRecap, Request $request, ClassPromotionPlacementService $placementService): RedirectResponse
    {
        abort_unless($classPromotionRecap->status === ClassPromotionRecap::STATUS_SUBMITTED, 422, 'Hanya rekap submitted yang bisa ditolak.');

        $validated = $request->validate([
            'rejection_notes' => ['required', 'string', 'max:1000'],
        ]);

        $placementService->reject($classPromotionRecap, $request->user()->id, $validated['rejection_notes']);

        return redirect()->route('admin.class-promotion.index', [
            'status' => ClassPromotionRecap::STATUS_REJECTED,
            'recap_id' => $classPromotionRecap->id,
        ])->with('success', 'Rekap kenaikan kelas ditolak.');
    }
}
