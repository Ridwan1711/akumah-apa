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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
    $this->classB = SchoolClass::query()->create([
        'name' => 'Kelas SK B',
        'grade_level_id' => $level->id,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
        'order' => 2,
    ]);
    $this->subjectA = Subject::query()->create(['name' => 'Nahwu SK']);
    $this->subjectB = Subject::query()->create(['name' => 'Fiqih SK']);
    GradeSubject::query()->create(['grade_level_id' => $level->id, 'subject_id' => $this->subjectA->id]);
    GradeSubject::query()->create(['grade_level_id' => $level->id, 'subject_id' => $this->subjectB->id]);
});

test('teacher assignment auto issues certificate and remains idempotent for same teacher period', function () {
    Storage::fake('public');
    Storage::disk('public')->put(
        'default-stamps/stempel-default.png',
        UploadedFile::fake()->image('stempel-default.png')->getContent()
    );
    config()->set('role_certificate.defaults.principal_name', 'KH. Default Pimpinan');
    config()->set('role_certificate.defaults.principal_title', 'Pimpinan Pesantren');
    config()->set('role_certificate.defaults.stamp_path', 'default-stamps/stempel-default.png');

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

    $this->actingAs($this->admin)->post(route('admin.teaching-assignments.store'), [
        'teacher_id' => $this->teacher->id,
        'class_id' => $this->classB->id,
        'subject_id' => $this->subjectA->id,
        'semester_id' => $this->semester->id,
    ])->assertRedirect();

    $certificates = RoleCertificate::query()
        ->where('certificate_type', RoleCertificate::TYPE_TEACHER)
        ->where('user_id', $this->teacher->id)
        ->where('academic_period_id', $this->period->id)
        ->get();

    expect($certificates)->toHaveCount(1);
    expect($certificates->first()->issuance_mode)->toBe(RoleCertificate::MODE_AUTO);
    expect($certificates->first()->valid_from?->toDateString())->toBe('2026-07-01');
    expect($certificates->first()->valid_until?->toDateString())->toBe('2026-12-31');
    expect($certificates->first()->principal_name)->toBe('KH. Default Pimpinan');
    expect($certificates->first()->principal_title)->toBe('Pimpinan Pesantren');
    expect($certificates->first()->stamp_path)->toBe('default-stamps/stempel-default.png');
    expect($certificates->first()->payload['class_subject_assignments'])->toHaveCount(2);
    expect($certificates->first()->payload['class_subject_assignments'][0])->toMatchArray([
        'subject_name' => 'Nahwu SK',
        'class_names' => 'Kelas SK, Kelas SK B',
        'total_jam' => 2,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.role-certificates.preview', $certificates->first()))
        ->assertOk()
        ->assertSee('Nahwu SK')
        ->assertSee('Kelas SK, Kelas SK B')
        ->assertSee('2 Jam')
        ->assertSee('Fiqih SK')
        ->assertSee('KH. Default Pimpinan')
        ->assertSee('data:image/png;base64', false);
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
        'valid_from' => '2026-07-01',
        'valid_until' => '2026-12-31',
        'principal_name' => 'KH. Kepala SK',
        'principal_title' => 'Kepala Sekolah',
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
    expect($all->last()->principal_name)->toBe('KH. Kepala SK');
});

test('manual regenerate requires validity dates and principal name', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.role-certificates.store'), [
        'certificate_type' => RoleCertificate::TYPE_TEACHER,
        'user_id' => $this->teacher->id,
        'academic_period_id' => $this->period->id,
        'action' => 'reissue',
    ]);

    $response->assertSessionHasErrors(['valid_from', 'valid_until', 'principal_name']);
});

test('admin can preview role certificate in browser without downloading pdf', function () {
    $this->actingAs($this->admin)->post(route('admin.teaching-assignments.store'), [
        'teacher_id' => $this->teacher->id,
        'class_id' => $this->class->id,
        'subject_id' => $this->subjectA->id,
        'semester_id' => $this->semester->id,
    ])->assertRedirect();

    $certificate = RoleCertificate::query()
        ->where('certificate_type', RoleCertificate::TYPE_TEACHER)
        ->where('user_id', $this->teacher->id)
        ->firstOrFail();

    $this->actingAs($this->admin)
        ->get(route('admin.role-certificates.preview', $certificate))
        ->assertOk()
        ->assertSee('Surat Keterangan')
        ->assertSee($certificate->certificate_number)
        ->assertSee('Guru SK');
});

test('preview shows principal snapshot and stamp image', function () {
    Storage::fake('public');

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
        'valid_from' => '2026-07-01',
        'valid_until' => '2026-12-31',
        'principal_name' => 'KH. Snapshot Kepala',
        'principal_title' => 'Pimpinan Pesantren',
        'stamp' => UploadedFile::fake()->image('stempel.png'),
        'action' => 'reissue',
    ])->assertRedirect(route('admin.role-certificates.index'));

    $certificate = RoleCertificate::query()
        ->where('certificate_type', RoleCertificate::TYPE_TEACHER)
        ->where('user_id', $this->teacher->id)
        ->latest('id')
        ->firstOrFail();

    expect($certificate->stamp_path)->not->toBeNull();
    Storage::disk('public')->assertExists($certificate->stamp_path);

    $this->actingAs($this->admin)
        ->get(route('admin.role-certificates.preview', $certificate))
        ->assertOk()
        ->assertSee('KH. Snapshot Kepala')
        ->assertSee('Pimpinan Pesantren')
        ->assertSee('data:image/png;base64', false);
});

test('admin can edit issued certificate signature snapshot', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(route('admin.teaching-assignments.store'), [
        'teacher_id' => $this->teacher->id,
        'class_id' => $this->class->id,
        'subject_id' => $this->subjectA->id,
        'semester_id' => $this->semester->id,
    ])->assertRedirect();

    $certificate = RoleCertificate::query()
        ->where('certificate_type', RoleCertificate::TYPE_TEACHER)
        ->where('user_id', $this->teacher->id)
        ->firstOrFail();

    $this->actingAs($this->admin)->post(route('admin.role-certificates.update', $certificate), [
        '_method' => 'put',
        'valid_from' => '2026-08-01',
        'valid_until' => '2026-12-15',
        'principal_name' => 'KH. Edited Pimpinan',
        'principal_title' => 'Mudir Pesantren',
        'stamp' => UploadedFile::fake()->image('stempel-edit.png'),
        'notes' => 'Snapshot tanda tangan diedit.',
    ])->assertRedirect(route('admin.role-certificates.index'));

    $certificate->refresh();

    expect($certificate->valid_from?->toDateString())->toBe('2026-08-01');
    expect($certificate->valid_until?->toDateString())->toBe('2026-12-15');
    expect($certificate->principal_name)->toBe('KH. Edited Pimpinan');
    expect($certificate->principal_title)->toBe('Mudir Pesantren');
    expect($certificate->notes)->toBe('Snapshot tanda tangan diedit.');
    expect($certificate->stamp_path)->not->toBeNull();
    Storage::disk('public')->assertExists($certificate->stamp_path);

    $this->actingAs($this->admin)
        ->get(route('admin.role-certificates.preview', $certificate))
        ->assertOk()
        ->assertSee('KH. Edited Pimpinan')
        ->assertSee('Mudir Pesantren')
        ->assertSee('data:image/png;base64', false);
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
