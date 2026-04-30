<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\AssessmentComponent;
use App\Models\Diniyyah\ClassSubject;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\KitabGradeSession;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $adminRole = Role::query()->where('name', Role::ADMIN_AKADEMIK)->firstOrFail();
    $this->admin = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'must_complete_profile' => false,
        'is_active' => true,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    $academicYear = AcademicYear::query()->create([
        'name' => '2026/2027-komponen',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->semester = Semester::query()->create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil-komponen',
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
    ]);
    $this->period = AcademicPeriod::query()->create([
        'academic_year_id' => $academicYear->id,
        'semester_id' => $this->semester->id,
        'is_active' => true,
    ]);

    $level = GradeLevel::query()->create(['name' => 'Salafy Komponen', 'order' => 1]);
    $this->class = SchoolClass::query()->create([
        'name' => 'Kelas Komponen',
        'grade_level_id' => $level->id,
        'order' => 1,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
    ]);
    $this->subject = Subject::query()->create(['name' => 'Hadits Komponen']);
    ClassSubject::query()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'period_id' => $this->period->id,
        'has_score' => true,
        'is_active' => true,
    ]);
    $this->student = Student::query()->create([
        'nis' => 'NIS-KOMP-001',
        'full_name' => 'Santri Komponen',
        'gender' => 'L',
        'status' => Student::STATUS_ACTIVE,
        'admission_year' => 2026,
        'current_class_id' => $this->class->id,
    ]);

    $this->componentCore = AssessmentComponent::query()->create([
        'name' => 'UTS Inti',
        'type' => AssessmentComponent::TYPE_EXAM,
        'is_core_required' => true,
    ]);
    $this->componentDaily = AssessmentComponent::query()->create([
        'name' => 'Tugas Harian',
        'type' => AssessmentComponent::TYPE_DAILY,
        'is_core_required' => false,
    ]);

    TeacherAssignment::query()->create([
        'teacher_id' => $this->admin->id,
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'period_id' => $this->period->id,
        'target_jam' => 1,
    ]);
});

test('store uses locked session components and saves only those scores', function () {
    KitabGradeSession::query()->create([
        'teacher_id' => $this->admin->id,
        'subject_id' => $this->subject->id,
        'class_id' => $this->class->id,
        'period_id' => $this->period->id,
        'active_component_ids' => [$this->componentCore->id],
        'status' => KitabGradeSession::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);

    $endpoint = route('guru.admin.kitab-grades.store', [
        'academic_period' => $this->semester->id,
        'kitab_subject' => $this->subject->id,
        'diniyah_class' => $this->class->id,
    ]);

    $response = $this->actingAs($this->admin)->post($endpoint, [
        'grades' => [[
            'student_id' => $this->student->id,
            'components' => [
                (string) $this->componentCore->id => 88,
                (string) $this->componentDaily->id => 71,
            ],
        ]],
    ]);
    $response->assertRedirect();

    expect(
        Score::query()
            ->where('student_id', $this->student->id)
            ->where('subject_id', $this->subject->id)
            ->where('period_id', $this->period->id)
            ->where('component_id', $this->componentCore->id)
            ->value('score')
    )->toEqual('88.00');
    expect(
        Score::query()
            ->where('student_id', $this->student->id)
            ->where('subject_id', $this->subject->id)
            ->where('period_id', $this->period->id)
            ->where('component_id', $this->componentDaily->id)
            ->exists()
    )->toBeFalse();
});

test('store rejects when session setting has not been created', function () {
    $response = $this->actingAs($this->admin)->post(route('guru.admin.kitab-grades.store', [
        'academic_period' => $this->semester->id,
        'kitab_subject' => $this->subject->id,
        'diniyah_class' => $this->class->id,
    ]), [
        'grades' => [[
            'student_id' => $this->student->id,
            'components' => [
                (string) $this->componentCore->id => 80,
            ],
        ]],
    ]);

    $response->assertSessionHasErrors('grades');
});

test('setting endpoint locks components once and cannot be changed in same semester', function () {
    $endpoint = route('guru.admin.kitab-grades.setting.store', [
        'academic_period' => $this->semester->id,
        'kitab_subject' => $this->subject->id,
        'diniyah_class' => $this->class->id,
    ]);

    $this->actingAs($this->admin)->post($endpoint, [
        'active_component_ids' => [$this->componentCore->id, $this->componentDaily->id],
    ])->assertRedirect();

    $response = $this->actingAs($this->admin)->post($endpoint, [
        'active_component_ids' => [$this->componentCore->id],
    ]);
    $response->assertSessionHasErrors('active_component_ids');
    expect(
        KitabGradeSession::query()
            ->where('teacher_id', $this->admin->id)
            ->where('subject_id', $this->subject->id)
            ->where('class_id', $this->class->id)
            ->where('period_id', $this->period->id)
            ->value('active_component_ids')
    )->toEqual([$this->componentCore->id, $this->componentDaily->id]);
});
