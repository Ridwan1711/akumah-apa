<?php

namespace App\Services\Diniyyah;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\ClassPromotion;
use App\Models\Diniyyah\ClassPromotionRecap;
use App\Models\Diniyyah\ClassPromotionRecapItem;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\StudentClassEnrollment;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class ClassPromotionPlacementService
{
    public function approve(ClassPromotionRecap $recap, int $reviewerId): void
    {
        $recap->loadMissing('period.academicYear', 'items.student.currentClass', 'sourceClass.gradeLevel');
        $targetPeriod = $this->resolveNextAcademicYearPeriod($recap->period);

        DB::transaction(function () use ($recap, $reviewerId, $targetPeriod): void {
            foreach ($recap->items as $item) {
                $this->applyItem($item, $recap, $reviewerId, $targetPeriod);
            }

            $recap->update([
                'status' => ClassPromotionRecap::STATUS_APPROVED,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'rejection_notes' => null,
            ]);
        });
    }

    public function reject(ClassPromotionRecap $recap, int $reviewerId, ?string $notes): void
    {
        $recap->update([
            'status' => ClassPromotionRecap::STATUS_REJECTED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'rejection_notes' => $notes,
        ]);
    }

    private function applyItem(
        ClassPromotionRecapItem $item,
        ClassPromotionRecap $recap,
        int $reviewerId,
        ?AcademicPeriod $targetPeriod,
    ): void {
        $student = $item->student;
        if (! $student || $student->status !== Student::STATUS_ACTIVE) {
            $this->blockItem($item, 'Santri tidak aktif atau tidak ditemukan.');

            return;
        }

        if ($item->final_decision === ClassPromotionRecapItem::DECISION_GRADUATE) {
            $student->update([
                'status' => Student::STATUS_ALUMNI,
                'current_class_id' => null,
            ]);
            $item->update([
                'placement_status' => ClassPromotionRecapItem::PLACEMENT_APPLIED,
                'placement_message' => 'Santri ditandai sebagai alumni.',
                'applied_class_id' => null,
            ]);

            return;
        }

        if (! $targetPeriod) {
            $this->blockItem($item, 'Periode tahun ajaran berikutnya belum tersedia.');

            return;
        }

        $targetClass = $this->resolveTargetClass($item, $recap, $student);
        if (! $targetClass) {
            $this->blockItem($item, 'Kelas tujuan sesuai tingkat dan gender belum tersedia.');

            return;
        }

        StudentClassEnrollment::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'period_id' => $targetPeriod->id,
            ],
            [
                'class_id' => $targetClass->id,
            ]
        );

        ClassPromotion::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'period_id' => $recap->period_id,
            ],
            [
                'from_class_id' => $item->from_class_id,
                'to_class_id' => $targetClass->id,
                'status' => ClassPromotion::STATUS_APPROVED,
                'approved_by' => $reviewerId,
                'notes' => $item->notes,
            ]
        );

        $student->update(['current_class_id' => $targetClass->id]);
        $item->update([
            'target_class_id' => $targetClass->id,
            'applied_class_id' => $targetClass->id,
            'placement_status' => ClassPromotionRecapItem::PLACEMENT_APPLIED,
            'placement_message' => 'Santri ditempatkan ke '.$targetClass->name.'.',
        ]);
    }

    private function resolveTargetClass(
        ClassPromotionRecapItem $item,
        ClassPromotionRecap $recap,
        Student $student,
    ): ?SchoolClass {
        if ($item->target_class_id) {
            $targetClass = SchoolClass::query()->find($item->target_class_id);

            return $targetClass?->acceptsStudentGender($student->gender) ? $targetClass : null;
        }

        $sourceClass = $recap->sourceClass;
        $sourceClass->loadMissing('gradeLevel');
        $targetGradeOrder = $sourceClass->gradeLevel?->order;

        if ($targetGradeOrder === null) {
            return null;
        }

        if ($item->final_decision === ClassPromotionRecapItem::DECISION_PROMOTE) {
            $targetGradeOrder++;
        }

        return SchoolClass::query()
            ->whereHas('gradeLevel', fn ($query) => $query->where('order', $targetGradeOrder))
            ->where('student_gender', $student->gender)
            ->inRandomOrder()
            ->first();
    }

    private function resolveNextAcademicYearPeriod(AcademicPeriod $period): ?AcademicPeriod
    {
        $period->loadMissing('academicYear');
        $currentYear = $period->academicYear;

        if (! $currentYear) {
            return null;
        }

        $nextYear = AcademicYear::query()
            ->where('start_date', '>', $currentYear->start_date)
            ->orderBy('start_date')
            ->orderBy('id')
            ->first();

        if (! $nextYear) {
            $nextYear = AcademicYear::query()
                ->where('id', '>', $currentYear->id)
                ->orderBy('id')
                ->first();
        }

        if (! $nextYear) {
            return null;
        }

        return AcademicPeriod::query()
            ->where('academic_year_id', $nextYear->id)
            ->orderBy('semester_id')
            ->orderBy('id')
            ->first();
    }

    private function blockItem(ClassPromotionRecapItem $item, string $message): void
    {
        $item->update([
            'placement_status' => ClassPromotionRecapItem::PLACEMENT_BLOCKED,
            'placement_message' => $message,
        ]);
    }
}
