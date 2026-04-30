<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\SubjectClassOverride;
use App\Models\Diniyyah\SubjectLevelSetting;
use App\Models\Semester;
use App\Services\Diniyyah\SubjectTeachingHourResolver;

test('teaching hour resolver prioritizes class override over level default', function () {
    $academicYear = AcademicYear::query()->create([
        'name' => '2025/2026',
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
        'is_active' => true,
    ]);

    $semester = Semester::query()->create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil',
        'start_date' => '2025-07-01',
        'end_date' => '2025-12-31',
        'is_active' => true,
    ]);

    $period = AcademicPeriod::query()->create([
        'academic_year_id' => $academicYear->id,
        'semester_id' => $semester->id,
        'is_active' => true,
    ]);

    $level = GradeLevel::query()->create([
        'name' => 'Ibtida',
        'order' => 1,
    ]);

    $class = SchoolClass::query()->create([
        'name' => 'Ibtida A',
        'grade_level_id' => $level->id,
        'order' => 1,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
    ]);

    $subject = Subject::query()->create([
        'name' => 'Fiqih',
    ]);

    $setting = SubjectLevelSetting::query()->create([
        'level_id' => $level->id,
        'subject_id' => $subject->id,
        'period_id' => $period->id,
        'has_score_default' => true,
        'target_jam_default' => 3,
        'is_mandatory_teaching' => true,
    ]);

    SubjectClassOverride::query()->create([
        'level_subject_default_id' => $setting->id,
        'class_id' => $class->id,
        'override_hours' => 5,
    ]);

    $resolver = app(SubjectTeachingHourResolver::class);

    expect($resolver->resolve($class->id, $subject->id, $period->id))->toBe(5);
});
