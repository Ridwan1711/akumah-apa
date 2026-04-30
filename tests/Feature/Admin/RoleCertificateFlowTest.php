<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\GradeSubject;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Role;
use App\Models\RoleCertificate;
use App\Models\Semester;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $adminRole = Role::query()->where('name', Role::ADMIN_AKADEMIK)->firstOrFail();
    $guruRole = Role::query()->where('name', Role::GURU)->firstOrFail();

    $this->admin = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'must_complete_profile' => false,
        'is_active' => true,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    $this->teacher = User::factory()->create([
        'name' => 'Guru SK',
        'is_active' => true,
    ]);
    $this->teacher->roles()->sync([$guruRole->id]);

    $academicYear = AcademicYear::query()->create([
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->semester = Semester::query()->create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil',
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
    ]);
    $this->period = AcademicPeriod::query()->create([
        'academic_year_id' => $academicYear->id,
        'semester_id' => $this->semester->id,
        'is_active' => true,
    ]);

    $level = GradeLevel::query()->create([
        'name' => 'Salafy SK',
        'order' => 1,
    ]);
    $this->class = SchoolClass::query()->create([
        'name' => 'Kelas SK',
        'grade_level_id' => $level->id,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
        'order' => 1,
    ]);
    $this->subjectA = Subject::query()->create(['name' => 'Nahwu SK']);
    $this->subjectB = Subject::query()->create(['name' => 'Fiqih SK']);
    GradeSubject::query()->create(['grade_level_id' => $level->id, 'subject_id' => $this->subjectA->id]);
    GradeSubject::query()->create(['grade_level_id' => $level->id, 'subject_id' => $this->subjectB->id]);
});

test('teacher assignment auto issues certificate and remains idempotent for same teacher period', function () {
    $this->actingAs($this->admin)->post(route('admin.teaching-assignments.store'), [
        'teacher_id' => $this->teacher->id,
        'class_id' => $this->class->id,
        'subject_id' => $this->subjectA->id,
        'semester_id' => $this->semester->id,
    ])->assertRedirect();

    $this->actingAs($this->admin)->post(route('admin.teaching-assignments.store'), [
        'teacher_id' => $this->teacher->id,
        'class_id' => $this->class->id,
        'subject_id' => $this->subjectB->id,
        'semester_id' => $this->semester->id,
    ])->assertRedirect();

    $certificates = RoleCertificate::query()
        ->where('certificate_type', RoleCertificate::TYPE_TEACHER)
        ->where('user_id', $this->teacher->id)
        ->where('academic_period_id', $this->period->id)
        ->get();

    expect($certificates)->toHaveCount(1);
    expect($certificates->first()->issuance_mode)->toBe(RoleCertificate::MODE_AUTO);
});

test('manual regenerate creates a new issued certificate and marks old one reissued', function () {
    $this->actingAs($this->admin)->post(route('admin.teaching-assignments.store'), [
        'teacher_id' => $this->teacher->id,
        'class_id' => $this->class->id,
        'subject_id' => $this->subjectA->id,
        'semester_id' => $this->semester->id,
    ])->assertRedirect();

    $this->actingAs($this->admin)->post(route('admin.role-certificates.store'), [
        'certificate_type' => RoleCertificate::TYPE_TEACHER,
        'user_id' => $this->teacher->id,
        'academic_period_id' => $this->period->id,
        'action' => 'reissue',
        'notes' => 'Regenerate untuk revisi redaksi.',
    ])->assertRedirect(route('admin.role-certificates.index'));

    $all = RoleCertificate::query()
        ->where('certificate_type', RoleCertificate::TYPE_TEACHER)
        ->where('user_id', $this->teacher->id)
        ->where('academic_period_id', $this->period->id)
        ->orderBy('id')
        ->get();

    expect($all)->toHaveCount(2);
    expect($all->first()->status)->toBe(RoleCertificate::STATUS_REISSUED);
    expect($all->last()->issuance_mode)->toBe(RoleCertificate::MODE_MANUAL);
    expect($all->last()->status)->toBe(RoleCertificate::STATUS_ISSUED);
});

test('cannot assign teacher when subject is not mapped to class level', function () {
    $unmappedSubject = Subject::query()->create(['name' => 'Mantiq SK']);

    $response = $this->actingAs($this->admin)->post(route('admin.teaching-assignments.store'), [
        'teacher_id' => $this->teacher->id,
        'class_id' => $this->class->id,
        'subject_id' => $unmappedSubject->id,
        'semester_id' => $this->semester->id,
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('teacher_assignments', [
        'class_id' => $this->class->id,
        'subject_id' => $unmappedSubject->id,
        'period_id' => $this->period->id,
    ]);
});
