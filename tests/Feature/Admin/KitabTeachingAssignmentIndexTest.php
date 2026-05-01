<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Role;
use App\Models\Semester;
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
    AcademicPeriod::query()->create([
        'academic_year_id' => $academicYear->id,
        'semester_id' => $this->semester->id,
        'is_active' => true,
    ]);

    GradeLevel::query()->create([
        'name' => 'Tingkat Uji',
        'order' => 1,
    ]);
});

test('teaching assignments index includes ordered grade levels for filters', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.teaching-assignments.index', [
        'semester_id' => $this->semester->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/teaching-assignments/index')
        ->has('gradeLevels', 1)
        ->where('gradeLevels.0.name', 'Tingkat Uji')
    );
});
