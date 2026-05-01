<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\ClassPromotionRecap;
use App\Models\Diniyyah\ClassPromotionRecapItem;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Semester;
use App\Services\Diniyyah\ClassPromotionRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WaliKelasClassPromotionRecapController extends Controller
{
    public function __construct(
        private readonly ClassPromotionRecommendationService $recommendations
    ) {}

    public function index(Request $request): Response
    {
        $classIds = $this->getMyClassIds($request);
        $classId = (int) $request->integer('class_id');
        $semesterId = (int) $request->integer('semester_id');
        $period = $this->resolvePeriod($semesterId);
        $rows = collect();
        $recap = null;

        if ($period && $classId > 0 && in_array($classId, $classIds, true)) {
            $schoolClass = SchoolClass::query()->find($classId);
            $recap = ClassPromotionRecap::query()
                ->with(['items.student:id,nis,full_name,gender', 'items.targetClass:id,name', 'reviewer:id,name'])
                ->where('source_class_id', $classId)
                ->where('period_id', $period->id)
                ->first();

            if ($recap && $recap->items->isNotEmpty()) {
                $rows = $recap->items->map(fn (ClassPromotionRecapItem $item): array => $this->itemPayload($item));
            } elseif ($schoolClass) {
                $rows = $this->recommendations->buildRows($schoolClass, $period);
            }
        }

        return Inertia::render('wali-kelas/class-promotion-recaps/index', [
            'classes' => SchoolClass::query()->whereIn('id', $classIds)->orderBy('order')->orderBy('name')->get(['id', 'name', 'grade_level_id']),
            'semesters' => Semester::query()->with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']),
            'rows' => $rows->values(),
            'recap' => $recap,
            'filters' => $request->only(['class_id', 'semester_id']),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $classIds = $this->getMyClassIds($request);
        $validated = $request->validate([
            'class_id' => ['required', 'integer', Rule::in($classIds)],
            'semester_id' => ['required', 'exists:semesters,id'],
            'decisions' => ['required', 'array'],
            'decisions.*.student_id' => ['required', 'exists:students,id'],
            'decisions.*.final_decision' => ['required', Rule::in(ClassPromotionRecapItem::DECISIONS)],
            'decisions.*.target_class_id' => ['nullable', 'exists:classes,id'],
            'decisions.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $period = $this->resolvePeriod((int) $validated['semester_id']);
        abort_unless($period !== null, 422, 'Periode akademik untuk semester ini belum tersedia.');
        abort_unless($period->isSemesterTwo(), 422, 'Pengajuan kenaikan kelas hanya bisa dibuat pada semester 2.');

        $existing = ClassPromotionRecap::query()
            ->where('source_class_id', (int) $validated['class_id'])
            ->where('period_id', $period->id)
            ->first();
        abort_unless($existing?->status !== ClassPromotionRecap::STATUS_APPROVED, 422, 'Rekap yang sudah disetujui tidak bisa diajukan ulang.');

        $schoolClass = SchoolClass::query()->findOrFail((int) $validated['class_id']);
        $decisionMap = collect($validated['decisions'])
            ->keyBy(fn (array $row): int => (int) $row['student_id'])
            ->map(fn (array $row): array => [
                'final_decision' => $row['final_decision'],
                'target_class_id' => isset($row['target_class_id']) ? (int) $row['target_class_id'] : null,
                'notes' => $row['notes'] ?? null,
            ])
            ->all();
        $rows = $this->recommendations->buildRows($schoolClass, $period, $decisionMap);

        abort_if($rows->isEmpty(), 422, 'Tidak ada santri aktif pada kelas ini.');
        abort_if($rows->contains(fn (array $row): bool => ! $row['is_complete']), 422, 'Semua santri harus memiliki nilai mapel dan nilai baca kitab sebelum diajukan.');

        DB::transaction(function () use ($existing, $validated, $period, $request, $rows): void {
            $recap = ClassPromotionRecap::query()->updateOrCreate(
                [
                    'source_class_id' => (int) $validated['class_id'],
                    'period_id' => $period->id,
                ],
                [
                    'submitted_by' => $request->user()->id,
                    'reviewed_by' => null,
                    'status' => ClassPromotionRecap::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                    'reviewed_at' => null,
                    'rejection_notes' => null,
                ]
            );

            if ($existing) {
                $recap->items()->delete();
            }

            foreach ($rows as $row) {
                $recap->items()->create([
                    'student_id' => $row['student_id'],
                    'from_class_id' => $row['from_class_id'],
                    'target_class_id' => $row['target_class_id'],
                    'academic_average' => $row['academic_average'],
                    'personality_score' => $row['personality_score'],
                    'kitab_reading_score' => $row['kitab_reading_score'],
                    'weighted_total' => $row['weighted_total'],
                    'system_recommendation' => $row['system_recommendation'],
                    'final_decision' => $row['final_decision'],
                    'notes' => $row['notes'],
                ]);
            }
        });

        return redirect()->route('wali-kelas.class-promotion-recaps.index', [
            'class_id' => (int) $validated['class_id'],
            'semester_id' => (int) $validated['semester_id'],
        ])->with('success', 'Rekap kenaikan kelas berhasil diajukan ke Admin Akademik.');
    }

    private function resolvePeriod(int $semesterId): ?AcademicPeriod
    {
        if ($semesterId <= 0) {
            return null;
        }

        return AcademicPeriod::query()
            ->where('semester_id', $semesterId)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int>
     */
    private function getMyClassIds(Request $request): array
    {
        return $request->user()->homeroomAssignments()->pluck('class_id')->unique()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(ClassPromotionRecapItem $item): array
    {
        return [
            'student_id' => $item->student_id,
            'student' => [
                'id' => $item->student?->id,
                'nis' => $item->student?->nis,
                'full_name' => $item->student?->full_name,
                'gender' => $item->student?->gender,
            ],
            'from_class_id' => $item->from_class_id,
            'target_class_id' => $item->target_class_id,
            'target_class' => $item->targetClass ? [
                'id' => $item->targetClass->id,
                'name' => $item->targetClass->name,
            ] : null,
            'academic_average' => $item->academic_average !== null ? (float) $item->academic_average : null,
            'academic_passed' => $item->academic_average !== null && (float) $item->academic_average > ClassPromotionRecommendationService::MINIMUM_ACADEMIC_AVERAGE,
            'personality_score' => (float) $item->personality_score,
            'kitab_reading_score' => $item->kitab_reading_score !== null ? (float) $item->kitab_reading_score : null,
            'weighted_total' => (float) $item->weighted_total,
            'system_recommendation' => $item->system_recommendation,
            'final_decision' => $item->final_decision,
            'notes' => $item->notes,
            'is_complete' => $item->academic_average !== null && $item->kitab_reading_score !== null,
            'placement_status' => $item->placement_status,
            'placement_message' => $item->placement_message,
        ];
    }
}
