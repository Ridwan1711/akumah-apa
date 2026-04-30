<?php

use App\Models\Role;
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
});

test('teacher management index only shows users with guru role', function () {
    $guruRole = Role::query()->where('name', Role::GURU)->firstOrFail();
    $nonGuruRole = Role::query()->where('name', Role::ADMIN_KEUANGAN)->firstOrFail();

    $guru = User::factory()->create(['name' => 'Guru A']);
    $guru->roles()->sync([$guruRole->id]);

    $nonGuru = User::factory()->create(['name' => 'Admin Keu']);
    $nonGuru->roles()->sync([$nonGuruRole->id]);

    $response = $this->actingAs($this->admin)->get(route('admin.teachers.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('admin/teachers/index')
            ->where('teachers.data', fn ($rows) => $rows->count() === 1)
            ->where('teachers.data.0.name', 'Guru A')
    );
});

test('admin akademik can create teacher and role guru is attached', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.teachers.store'), [
        'mode' => 'create',
        'name' => 'Ustadz Baru',
        'username' => 'ustadz.baru',
        'email' => 'ustadz-baru@example.test',
        'password' => 'password123',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.teachers.index'));
    $teacher = User::query()->where('email', 'ustadz-baru@example.test')->first();

    expect($teacher)->not->toBeNull();
    expect($teacher?->hasRole(Role::GURU))->toBeTrue();
});

test('admin akademik can assign existing user to guru role', function () {
    $existingUser = User::factory()->create([
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.teachers.store'), [
        'mode' => 'assign',
        'existing_user_id' => $existingUser->id,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.teachers.index'));
    $existingUser->refresh();

    expect($existingUser->hasRole(Role::GURU))->toBeTrue();
});
