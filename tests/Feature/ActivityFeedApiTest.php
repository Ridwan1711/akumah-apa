<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('activity feed rejects guests', function () {
    $this->getJson('/api/v1/activity')->assertUnauthorized();
});

test('super admin sees audit logs across modules', function () {
    $superRole = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();
    $admin = User::factory()->create([
        'is_active' => true,
        'must_change_password' => false,
        'must_complete_profile' => false,
    ]);
    $admin->roles()->sync([$superRole->id]);

    $student = Student::query()->create([
        'nis' => 'ACT-SUPER-1',
        'full_name' => 'Santri Aktivitas',
        'gender' => Student::GENDER_MALE,
        'status' => Student::STATUS_ACTIVE,
        'admission_year' => 2026,
    ]);

    AuditLog::query()->create([
        'user_id' => $admin->id,
        'module' => 'student',
        'action' => 'update',
        'auditable_type' => $student->getMorphClass(),
        'auditable_id' => $student->id,
        'old_data' => ['full_name' => 'Lama'],
        'new_data' => ['full_name' => 'Baru'],
        'created_at' => now(),
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/activity')->assertOk();

    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(1);
    expect($response->json('data'))->not->toBeEmpty();
    expect($response->json('scope_description'))->toContain('seluruh');
    expect($response->json('data.0.summary_line'))->toBeString()->not->toBe('');
});

test('santri only sees activity tied to their student record', function () {
    $santriRole = Role::query()->where('name', Role::SANTRI)->firstOrFail();
    $santriUser = User::factory()->create([
        'is_active' => true,
        'must_change_password' => false,
        'must_complete_profile' => false,
    ]);
    $santriUser->roles()->sync([$santriRole->id]);

    $mine = Student::query()->create([
        'nis' => 'ACT-MINE',
        'full_name' => 'Santri Saya',
        'gender' => Student::GENDER_MALE,
        'status' => Student::STATUS_ACTIVE,
        'admission_year' => 2026,
        'user_id' => $santriUser->id,
    ]);

    $other = Student::query()->create([
        'nis' => 'ACT-OTHER',
        'full_name' => 'Santri Orang',
        'gender' => Student::GENDER_MALE,
        'status' => Student::STATUS_ACTIVE,
        'admission_year' => 2026,
    ]);

    AuditLog::query()->create([
        'user_id' => $santriUser->id,
        'module' => 'student',
        'action' => 'update',
        'auditable_type' => $mine->getMorphClass(),
        'auditable_id' => $mine->id,
        'old_data' => ['full_name' => 'A'],
        'new_data' => ['full_name' => 'B'],
        'created_at' => now()->subMinute(),
    ]);

    AuditLog::query()->create([
        'user_id' => $santriUser->id,
        'module' => 'student',
        'action' => 'update',
        'auditable_type' => $other->getMorphClass(),
        'auditable_id' => $other->id,
        'old_data' => ['full_name' => 'X'],
        'new_data' => ['full_name' => 'Y'],
        'created_at' => now(),
    ]);

    Sanctum::actingAs($santriUser);

    $response = $this->getJson('/api/v1/activity')->assertOk();

    expect($response->json('meta.total'))->toBe(1);
    $summaries = collect($response->json('data'))->pluck('summary_line')->implode(' ');
    expect($summaries)->not->toContain('Santri Orang');
});
