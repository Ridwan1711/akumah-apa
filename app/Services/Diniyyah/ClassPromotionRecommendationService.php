<?php

namespace App\Services\Diniyyah;

use App\Models\AcademicPeriod;
use App\Models\Diniyyah\ClassPromotionRecapItem;
use App\Models\Diniyyah\KitabReadingAssessment;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Score;
use App\Models\Student;
use App\Models\StudentViolation;
use Illuminate\Support\Collection;

class ClassPromotionRecommendationService
{
    public const ACADEMIC_WEIGHT = 0.35;

    public const PERSONALITY_WEIGHT = 0.50;

    public const KITAB_READING_WEIGHT = 0.15;

    public const MINIMUM_WEIGHTED_TOTAL = 70.0;

    public const MINIMUM_ACADEMIC_AVERAGE = 60.0;

    /**
     * @param  array<int, array{final_decision?: string|null, notes?: string|null, target_class_id?: int|null}>  $decisions
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function buildRows(SchoolClass $schoolClass, AcademicPeriod $period, array $decisions = []): Collection
    {
        $students = Student::query()
            ->where('current_class_id', $schoolClass->id)
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->get(['id', 'nis', 'full_name', 'gender', 'current_class_id']);

        $studentIds = $students->pluck('id')->all();
        $academicAverages = $this->academicAverages($studentIds, $period);
        $personalityScores = $this->personalityScores($studentIds, $period);
        $kitabReadingScores = $this->kitabReadingScores($studentIds, $period);

        return $students->map(function (Student $student) use ($schoolClass, $academicAverages, $personalityScores, $kitabReadingScores, $decisions): array {
            $academicAverage = $academicAverages->get($student->id);
            $personalityScore = (float) ($personalityScores->get($student->id) ?? 100.0);
            $kitabReadingScore = $kitabReadingScores->get($student->id);
            $weightedTotal = $this->weightedTotal($academicAverage, $personalityScore, $kitabReadingScore);
            $academicPassed = $academicAverage !== null && $academicAverage > self::MINIMUM_ACADEMIC_AVERAGE;
            $systemRecommendation = ($academicPassed && $weightedTotal >= self::MINIMUM_WEIGHTED_TOTAL)
                ? ClassPromotionRecapItem::RECOMMENDATION_PROMOTE
                : ClassPromotionRecapItem::RECOMMENDATION_STAY;
            $decision = $decisions[$student->id] ?? [];

            return [
                'student_id' => $student->id,
                'student' => [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'full_name' => $student->full_name,
                    'gender' => $student->gender,
                ],
                'from_class_id' => $schoolClass->id,
                'academic_average' => $academicAverage,
                'academic_passed' => $academicPassed,
                'personality_score' => round($personalityScore, 2),
                'kitab_reading_score' => $kitabReadingScore,
                'weighted_total' => $weightedTotal,
                'system_recommendation' => $systemRecommendation,
                'final_decision' => $decision['final_decision'] ?? $systemRecommendation,
                'target_class_id' => $decision['target_class_id'] ?? null,
                'notes' => $decision['notes'] ?? null,
                'is_complete' => $academicAverage !== null && $kitabReadingScore !== null,
            ];
        });
    }

    /**
     * @param  array<int>  $studentIds
     * @return \Illuminate\Support\Collection<int, float>
     */
    private function academicAverages(array $studentIds, AcademicPeriod $period): Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        return Score::query()
            ->where('period_id', $period->id)
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', [Score::STATUS_SUBMITTED, Score::STATUS_FINALIZED])
            ->whereNotNull('score')
            ->get(['student_id', 'subject_id', 'score'])
            ->groupBy('student_id')
            ->map(function (Collection $studentScores): float {
                $subjectAverages = $studentScores
                    ->groupBy('subject_id')
                    ->map(fn (Collection $subjectScores): float => (float) $subjectScores->avg('score'));

                return round((float) $subjectAverages->avg(), 2);
            });
    }

    /**
     * @param  array<int>  $studentIds
     * @return \Illuminate\Support\Collection<int, float>
     */
    private function personalityScores(array $studentIds, AcademicPeriod $period): Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        $period->loadMissing('semester');
        $semester = $period->semester;

        $query = StudentViolation::query()
            ->join('violation_types', 'student_violations.violation_type_id', '=', 'violation_types.id')
            ->whereIn('student_violations.student_id', $studentIds);

        if ($semester?->start_date && $semester?->end_date) {
            $query->whereBetween('student_violations.date', [$semester->start_date, $semester->end_date]);
        }

        return $query
            ->selectRaw('student_violations.student_id, COALESCE(SUM(violation_types.points), 0) as total_points')
            ->groupBy('student_violations.student_id')
            ->pluck('total_points', 'student_id')
            ->map(fn ($points): float => max(0.0, 100.0 - (float) $points));
    }

    /**
     * @param  array<int>  $studentIds
     * @return \Illuminate\Support\Collection<int, float>
     */
    private function kitabReadingScores(array $studentIds, AcademicPeriod $period): Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        return KitabReadingAssessment::query()
            ->where('period_id', $period->id)
            ->whereIn('student_id', $studentIds)
            ->pluck('score', 'student_id')
            ->map(fn ($score): float => round((float) $score, 2));
    }

    private function weightedTotal(?float $academicAverage, float $personalityScore, ?float $kitabReadingScore): float
    {
        return round(
            (($academicAverage ?? 0.0) * self::ACADEMIC_WEIGHT)
            + ($personalityScore * self::PERSONALITY_WEIGHT)
            + (($kitabReadingScore ?? 0.0) * self::KITAB_READING_WEIGHT),
            2
        );
    }
}
