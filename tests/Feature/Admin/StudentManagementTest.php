<?php

use App\Models\Role;
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
});

test('admin akademik can create student linked to existing user', function () {
    $alumniRole = Role::query()->where('name', Role::ALUMNI)->firstOrFail();
    $existingUser = User::factory()->create();
    $existingUser->roles()->sync([$alumniRole->id]);

    $response = $this->actingAs($this->admin)->post(route('admin.students.store'), [
        'user_id' => $existingUser->id,
        'nis' => 'S0001',
        'full_name' => 'Santri Alumni',
        'admission_year' => 2020,
    ]);

    $response->assertRedirect(route('admin.students.index'));

    $student = Student::query()->where('nis', 'S0001')->first();
    expect($student)->not->toBeNull();
    expect($student?->user_id)->toBe($existingUser->id);
});

test('eligible users endpoint only returns santri or alumni users without student', function () {
    $santriRole = Role::query()->where('name', Role::SANTRI)->firstOrFail();
    $alumniRole = Role::query()->where('name', Role::ALUMNI)->firstOrFail();
    $adminKeuanganRole = Role::query()->where('name', Role::ADMIN_KEUANGAN)->firstOrFail();

    $eligible = User::factory()->create(['name' => 'Eligible Santri']);
    $eligible->roles()->sync([$santriRole->id]);

    $adminUser = User::factory()->create(['name' => 'Admin Keu']);
    $adminUser->roles()->sync([$adminKeuanganRole->id]);

    $attached = User::factory()->create(['name' => 'Already Attached']);
    $attached->roles()->sync([$alumniRole->id]);
    Student::query()->create([
        'user_id' => $attached->id,
        'nis' => 'S9999',
        'full_name' => 'Already Attached',
        'gender' => Student::GENDER_MALE,
        'status' => Student::STATUS_ACTIVE,
        'admission_year' => 2020,
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('admin.students.eligible-users'));

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($eligible->id);
    expect($ids)->not->toContain($adminUser->id);
    expect($ids)->not->toContain($attached->id);
});

test('admin akademik can assign and clear user_id on student update', function () {
    $student = Student::query()->create([
        'user_id' => null,
        'nis' => 'S0101',
        'full_name' => 'Santri Update',
        'gender' => Student::GENDER_MALE,
        'status' => Student::STATUS_ACTIVE,
        'admission_year' => 2021,
    ]);

    $alumniRole = Role::query()->where('name', Role::ALUMNI)->firstOrFail();
    $existingUser = User::factory()->create(['name' => 'Assigned User']);
    $existingUser->roles()->sync([$alumniRole->id]);

    $response = $this->actingAs($this->admin)->put(route('admin.students.update', $student), [
        'user_id' => $existingUser->id,
        'nis' => $student->nis,
        'nik' => null,
        'full_name' => $student->full_name,
        'birth_place' => null,
        'birth_date' => null,
        'gender' => $student->gender,
        'address' => null,
        'status' => $student->status,
        'admission_year' => $student->admission_year,
        'current_class_id' => null,
    ]);

    $response->assertRedirect(route('admin.students.show', $student));
    $student->refresh();
    expect($student->user_id)->toBe($existingUser->id);

    $response = $this->actingAs($this->admin)->put(route('admin.students.update', $student), [
        'user_id' => null,
        'nis' => $student->nis,
        'nik' => null,
        'full_name' => $student->full_name,
        'birth_place' => null,
        'birth_date' => null,
        'gender' => $student->gender,
        'address' => null,
        'status' => $student->status,
        'admission_year' => $student->admission_year,
        'current_class_id' => null,
    ]);

    $response->assertRedirect(route('admin.students.show', $student));
    $student->refresh();
    expect($student->user_id)->toBeNull();
});
