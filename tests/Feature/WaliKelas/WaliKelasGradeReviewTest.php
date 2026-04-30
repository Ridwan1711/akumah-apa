<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\AssessmentComponent;
use App\Models\Diniyyah\ClassSubject;
use App\Models\Diniyyah\ClassWali;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\KitabGradeSession;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\Subject;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $academicYear = AcademicYear::query()->create([
        'name' => '2026/2027-review-wali',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->semester = Semester::query()->create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil-review-wali',
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
    ]);
    $this->period = AcademicPeriod::query()->create([
        'academic_year_id' => $academicYear->id,
        'semester_id' => $this->semester->id,
        'is_active' => true,
    ]);

    $level = GradeLevel::query()->create(['name' => 'Salafy Review', 'order' => 1]);
    $this->class = SchoolClass::query()->create([
        'name' => 'Kelas Review Wali',
        'grade_level_id' => $level->id,
        'order' => 1,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
    ]);
    $this->student = Student::query()->create([
        'nis' => 'NIS-REVIEW-001',
        'full_name' => 'Santri Review',
        'gender' => 'L',
        'status' => Student::STATUS_ACTIVE,
        'admission_year' => 2026,
        'current_class_id' => $this->class->id,
    ]);

    $this->subjectA = Subject::query()->create(['name' => 'Fiqih Review']);
    $this->subjectB = Subject::query()->create(['name' => 'Hadits Review']);
    ClassSubject::query()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subjectA->id,
        'period_id' => $this->period->id,
        'has_score' => true,
        'is_active' => true,
    ]);
    ClassSubject::query()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subjectB->id,
        'period_id' => $this->period->id,
        'has_score' => true,
        'is_active' => true,
    ]);

    $guruRole = Role::query()->where('name', Role::GURU)->firstOrFail();
    $this->teacher = User::factory()->create(['is_active' => true, 'must_change_password' => false, 'must_complete_profile' => false]);
    $this->teacher->roles()->sync([$guruRole->id]);
    $this->wali = User::factory()->create(['is_active' => true, 'must_change_password' => false, 'must_complete_profile' => false]);
    $this->wali->roles()->sync([$guruRole->id]);

    ClassWali::query()->create([
        'class_id' => $this->class->id,
        'teacher_id' => $this->wali->id,
        'period_id' => $this->period->id,
    ]);

    $component = AssessmentComponent::query()->create([
        'name' => 'UAS Review',
        'type' => AssessmentComponent::TYPE_EXAM,
        'is_core_required' => true,
    ]);

    Score::query()->create([
        'student_id' => $this->student->id,
        'subject_id' => $this->subjectA->id,
        'component_id' => $component->id,
        'teacher_id' => $this->teacher->id,
        'period_id' => $this->period->id,
        'score' => 86,
        'status' => Score::STATUS_SUBMITTED,
    ]);
    Score::query()->create([
        'student_id' => $this->student->id,
        'subject_id' => $this->subjectB->id,
        'component_id' => $component->id,
        'teacher_id' => $this->teacher->id,
        'period_id' => $this->period->id,
        'score' => 91,
        'status' => Score::STATUS_SUBMITTED,
    ]);

    KitabGradeSession::query()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => $this->subjectA->id,
        'class_id' => $this->class->id,
        'period_id' => $this->period->id,
        'active_component_ids' => [$component->id],
        'status' => KitabGradeSession::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);
    KitabGradeSession::query()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => $this->subjectB->id,
        'class_id' => $this->class->id,
        'period_id' => $this->period->id,
        'active_component_ids' => [$component->id],
        'status' => KitabGradeSession::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);
});

test('wali kelas can view class recap matrix of mandatory scored subjects', function () {
    $response = $this->actingAs($this->wali)->get('/wali-kelas/grade-reviews?class_id='.$this->class->id.'&semester_id='.$this->semester->id);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('wali-kelas/grade-reviews/index')
        ->where('students.0.id', $this->student->id)
        ->where('subjects.0.id', $this->subjectA->id)
        ->where('subjects.1.id', $this->subjectB->id)
    );
});

test('wali kelas can finalize submitted scores and mark sessions reviewed', function () {
    $this->actingAs($this->wali)->post('/wali-kelas/grade-reviews/review', [
        'class_id' => $this->class->id,
        'semester_id' => $this->semester->id,
    ])->assertRedirect();

    expect(
        Score::query()
            ->where('period_id', $this->period->id)
            ->where('student_id', $this->student->id)
            ->whereIn('subject_id', [$this->subjectA->id, $this->subjectB->id])
            ->pluck('status')
            ->unique()
            ->all()
    )->toEqual([Score::STATUS_FINALIZED]);

    expect(
        KitabGradeSession::query()
            ->where('class_id', $this->class->id)
            ->where('period_id', $this->period->id)
            ->pluck('status')
            ->unique()
            ->all()
    )->toEqual([KitabGradeSession::STATUS_REVIEWED]);
});
