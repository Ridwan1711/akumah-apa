<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\AssessmentComponent;
use App\Models\Diniyyah\ClassPromotion;
use App\Models\Diniyyah\ClassPromotionRecap;
use App\Models\Diniyyah\ClassPromotionRecapItem;
use App\Models\Diniyyah\ClassSubject;
use App\Models\Diniyyah\ClassWali;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\KitabReadingAssessment;
use App\Models\Diniyyah\KitabReadingExaminerAssignment;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\StudentClassEnrollment;
use App\Models\Diniyyah\Subject;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentViolation;
use App\Models\User;
use App\Models\ViolationType;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $this->admin = User::factory()->create([
        'email_verified_at' => now(),
        'is_active' => true,
        'must_change_password' => false,
        'must_complete_profile' => false,
    ]);
    $this->admin->roles()->sync([Role::query()->where('name', Role::ADMIN_AKADEMIK)->value('id')]);

    $this->wali = User::factory()->create([
        'email_verified_at' => now(),
        'is_active' => true,
        'must_change_password' => false,
        'must_complete_profile' => false,
    ]);
    $this->wali->roles()->sync([Role::query()->where('name', Role::GURU)->value('id')]);

    $this->examiner = User::factory()->create([
        'email_verified_at' => now(),
        'is_active' => true,
        'must_change_password' => false,
        'must_complete_profile' => false,
    ]);
    $this->examiner->roles()->sync([Role::query()->where('name', Role::GURU)->value('id')]);

    $this->academicYear = AcademicYear::query()->create([
        'name' => '2026/2027-promotion',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    Semester::query()->create([
        'academic_year_id' => $this->academicYear->id,
        'name' => 'Ganjil Promotion',
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
    ]);
    $this->semester = Semester::query()->create([
        'academic_year_id' => $this->academicYear->id,
        'name' => 'Genap Promotion',
        'start_date' => '2027-01-01',
        'end_date' => '2027-06-30',
    ]);
    $this->period = AcademicPeriod::query()->create([
        'academic_year_id' => $this->academicYear->id,
        'semester_id' => $this->semester->id,
        'is_active' => true,
    ]);

    $this->nextAcademicYear = AcademicYear::query()->create([
        'name' => '2027/2028-promotion',
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $nextSemester = Semester::query()->create([
        'academic_year_id' => $this->nextAcademicYear->id,
        'name' => 'Ganjil Next Promotion',
        'start_date' => '2027-07-01',
        'end_date' => '2027-12-31',
    ]);
    $this->nextPeriod = AcademicPeriod::query()->create([
        'academic_year_id' => $this->nextAcademicYear->id,
        'semester_id' => $nextSemester->id,
        'is_active' => false,
    ]);

    $this->levelOne = GradeLevel::query()->create(['name' => 'Level Promotion 1', 'order' => 101]);
    $this->levelTwo = GradeLevel::query()->create(['name' => 'Level Promotion 2', 'order' => 102]);
    $this->sourceClass = SchoolClass::query()->create([
        'name' => 'Kelas Promotion 1A',
        'grade_level_id' => $this->levelOne->id,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
        'order' => 101,
    ]);
    $this->targetClass = SchoolClass::query()->create([
        'name' => 'Kelas Promotion 2A',
        'grade_level_id' => $this->levelTwo->id,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
        'order' => 102,
    ]);
    $this->femaleTargetClass = SchoolClass::query()->create([
        'name' => 'Kelas Promotion 2B Putri',
        'grade_level_id' => $this->levelTwo->id,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYAH,
        'order' => 103,
    ]);

    $this->student = Student::query()->create([
        'nis' => 'PROMO-001',
        'full_name' => 'Santri Promotion',
        'gender' => Student::GENDER_MALE,
        'status' => Student::STATUS_ACTIVE,
        'admission_year' => 2026,
        'current_class_id' => $this->sourceClass->id,
    ]);

    ClassWali::query()->create([
        'class_id' => $this->sourceClass->id,
        'teacher_id' => $this->wali->id,
        'period_id' => $this->period->id,
    ]);

    $subject = Subject::query()->create(['name' => 'Fiqih Promotion']);
    ClassSubject::query()->create([
        'class_id' => $this->sourceClass->id,
        'subject_id' => $subject->id,
        'period_id' => $this->period->id,
        'has_score' => true,
        'is_active' => true,
    ]);
    $component = AssessmentComponent::query()->create([
        'name' => 'UAS Promotion',
        'type' => AssessmentComponent::TYPE_EXAM,
        'is_core_required' => true,
    ]);
    Score::query()->create([
        'student_id' => $this->student->id,
        'subject_id' => $subject->id,
        'component_id' => $component->id,
        'teacher_id' => $this->examiner->id,
        'period_id' => $this->period->id,
        'score' => 80,
        'status' => Score::STATUS_FINALIZED,
    ]);

    $violationType = ViolationType::query()->create([
        'name' => 'Terlambat Promotion',
        'points' => 10,
        'category' => ViolationType::CATEGORY_RINGAN,
    ]);
    StudentViolation::query()->create([
        'student_id' => $this->student->id,
        'violation_type_id' => $violationType->id,
        'date' => '2027-02-01',
        'handled_by' => $this->admin->id,
        'status' => StudentViolation::STATUS_OPEN,
    ]);
});

test('admin can assign a dedicated kitab reading examiner', function () {
    $this->actingAs($this->admin)->post('/admin/kitab-reading-examiners', [
        'examiner_id' => $this->examiner->id,
        'class_id' => $this->sourceClass->id,
        'semester_id' => $this->semester->id,
    ])->assertRedirect();

    expect(KitabReadingExaminerAssignment::query()
        ->where('examiner_id', $this->examiner->id)
        ->where('class_id', $this->sourceClass->id)
        ->where('period_id', $this->period->id)
        ->exists())->toBeTrue();
});

test('examiner can store kitab reading assessment per student and semester', function () {
    KitabReadingExaminerAssignment::query()->create([
        'examiner_id' => $this->examiner->id,
        'class_id' => $this->sourceClass->id,
        'period_id' => $this->period->id,
    ]);

    $this->actingAs($this->examiner)->post('/admin/kitab-reading-assessments', [
        'class_id' => $this->sourceClass->id,
        'semester_id' => $this->semester->id,
        'assessments' => [[
            'student_id' => $this->student->id,
            'score' => 90,
            'notes' => 'Lancar membaca.',
        ]],
    ])->assertRedirect();

    $assessment = KitabReadingAssessment::query()->where('student_id', $this->student->id)->first();

    expect($assessment)->not->toBeNull()
        ->and((float) $assessment->score)->toBe(90.0)
        ->and($assessment->examiner_id)->toBe($this->examiner->id);
});

test('unassigned user cannot store kitab reading assessment', function () {
    $this->actingAs($this->examiner)->post('/admin/kitab-reading-assessments', [
        'class_id' => $this->sourceClass->id,
        'semester_id' => $this->semester->id,
        'assessments' => [[
            'student_id' => $this->student->id,
            'score' => 90,
        ]],
    ])->assertForbidden();

    expect(KitabReadingAssessment::query()->where('student_id', $this->student->id)->exists())->toBeFalse();
});

test('wali kelas can submit weighted promotion recap', function () {
    KitabReadingAssessment::query()->create([
        'student_id' => $this->student->id,
        'class_id' => $this->sourceClass->id,
        'period_id' => $this->period->id,
        'examiner_id' => $this->examiner->id,
        'score' => 90,
        'assessed_at' => now(),
    ]);

    $this->actingAs($this->wali)->post('/wali-kelas/class-promotion-recaps', [
        'class_id' => $this->sourceClass->id,
        'semester_id' => $this->semester->id,
        'decisions' => [[
            'student_id' => $this->student->id,
            'final_decision' => ClassPromotionRecapItem::DECISION_PROMOTE,
        ]],
    ])->assertRedirect();

    $item = ClassPromotionRecapItem::query()->firstOrFail();

    expect((float) $item->academic_average)->toBe(80.0)
        ->and((float) $item->personality_score)->toBe(90.0)
        ->and((float) $item->kitab_reading_score)->toBe(90.0)
        ->and((float) $item->weighted_total)->toBe(86.5)
        ->and($item->system_recommendation)->toBe(ClassPromotionRecapItem::RECOMMENDATION_PROMOTE);
});

test('admin approval places promoted student into next grade class with matching gender', function () {
    KitabReadingAssessment::query()->create([
        'student_id' => $this->student->id,
        'class_id' => $this->sourceClass->id,
        'period_id' => $this->period->id,
        'examiner_id' => $this->examiner->id,
        'score' => 90,
        'assessed_at' => now(),
    ]);

    $this->actingAs($this->wali)->post('/wali-kelas/class-promotion-recaps', [
        'class_id' => $this->sourceClass->id,
        'semester_id' => $this->semester->id,
        'decisions' => [[
            'student_id' => $this->student->id,
            'final_decision' => ClassPromotionRecapItem::DECISION_PROMOTE,
        ]],
    ])->assertRedirect();

    $recap = ClassPromotionRecap::query()->firstOrFail();

    $this->actingAs($this->admin)->post("/admin/class-promotion/{$recap->id}/approve")->assertRedirect();

    $this->student->refresh();
    $recapItem = ClassPromotionRecapItem::query()->firstOrFail();

    expect($this->student->current_class_id)->toBe($this->targetClass->id)
        ->and($recapItem->placement_status)->toBe(ClassPromotionRecapItem::PLACEMENT_APPLIED)
        ->and(StudentClassEnrollment::query()
            ->where('student_id', $this->student->id)
            ->where('period_id', $this->nextPeriod->id)
            ->where('class_id', $this->targetClass->id)
            ->exists())->toBeTrue()
        ->and(ClassPromotion::query()
            ->where('student_id', $this->student->id)
            ->where('to_class_id', $this->targetClass->id)
            ->exists())->toBeTrue();
});

test('admin approval blocks placement when no matching gender class exists', function () {
    $this->targetClass->delete();
    KitabReadingAssessment::query()->create([
        'student_id' => $this->student->id,
        'class_id' => $this->sourceClass->id,
        'period_id' => $this->period->id,
        'examiner_id' => $this->examiner->id,
        'score' => 90,
        'assessed_at' => now(),
    ]);

    $this->actingAs($this->wali)->post('/wali-kelas/class-promotion-recaps', [
        'class_id' => $this->sourceClass->id,
        'semester_id' => $this->semester->id,
        'decisions' => [[
            'student_id' => $this->student->id,
            'final_decision' => ClassPromotionRecapItem::DECISION_PROMOTE,
        ]],
    ])->assertRedirect();

    $recap = ClassPromotionRecap::query()->firstOrFail();

    $this->actingAs($this->admin)->post("/admin/class-promotion/{$recap->id}/approve")->assertRedirect();

    $this->student->refresh();
    $recapItem = ClassPromotionRecapItem::query()->firstOrFail();

    expect($this->student->current_class_id)->toBe($this->sourceClass->id)
        ->and($recapItem->placement_status)->toBe(ClassPromotionRecapItem::PLACEMENT_BLOCKED)
        ->and($recapItem->placement_message)->toContain('Kelas tujuan');
});
