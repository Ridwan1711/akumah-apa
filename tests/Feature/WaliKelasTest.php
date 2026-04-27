<?php

use App\Models\AcademicYear;
use App\Models\DiniyahClass;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $academicYear = AcademicYear::create([
        'name' => '2024/2025',
        'start_date' => '2024-07-01',
        'end_date' => '2025-06-30',
        'is_active' => true,
    ]);
    Semester::create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil',
        'start_date' => '2024-07-01',
        'end_date' => '2024-12-31',
        'is_active' => true,
    ]);

    $guruRole = Role::where('name', Role::GURU)->first();
    $this->waliKelas = User::factory()->create([
        'username' => 'walikelas_test',
        'email' => 'wali@test.test',
        'is_active' => true,
    ]);
    $this->waliKelas->roles()->sync([$guruRole->id]);

    $this->class = DiniyahClass::create([
        'name' => '1 Salafy A',
        'level' => '1salafy',
        'academic_year_id' => $academicYear->id,
        'wali_kelas_id' => $this->waliKelas->id,
    ]);
});

test('wali kelas can access report cards index', function () {
    $this->actingAs($this->waliKelas);

    $response = $this->get('/wali-kelas/report-cards');

    $response->assertOk();
});

test('user without wali kelas record cannot access wali kelas report cards', function () {
    $guruRole = Role::where('name', Role::GURU)->first();
    $userNoClass = User::factory()->create([
        'username' => 'guru_noclass',
        'is_active' => true,
    ]);
    $userNoClass->roles()->sync([$guruRole->id]);

    $this->actingAs($userNoClass);

    $response = $this->get('/wali-kelas/report-cards');

    $response->assertForbidden();
});

test('guru with wali kelas assignment can access wali kelas report cards', function () {
    $guruRole = Role::where('name', Role::GURU)->first();
    $guruWaliKelas = User::factory()->create([
        'username' => 'guru_walikelas',
        'is_active' => true,
    ]);
    $guruWaliKelas->roles()->sync([$guruRole->id]);

    $this->class->update(['wali_kelas_id' => $guruWaliKelas->id]);

    $this->actingAs($guruWaliKelas);

    $response = $this->get('/wali-kelas/report-cards');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('wali-kelas/report-cards/index')
        ->has('classes')
        ->where('classes.0.name', '1 Salafy A')
    );
});

test('non wali kelas cannot access wali kelas report cards', function () {
    $adminRole = Role::where('name', Role::SUPER_ADMIN)->first();
    $admin = User::factory()->create([
        'username' => 'admin_test',
        'is_active' => true,
    ]);
    $admin->roles()->sync([$adminRole->id]);

    $this->actingAs($admin);

    $response = $this->get('/wali-kelas/report-cards');

    $response->assertForbidden();
});
