<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

test('user with multiple roles receives union route access', function () {
    $adminAkademik = Role::query()->where('name', Role::ADMIN_AKADEMIK)->firstOrFail();
    $guru = Role::query()->where('name', Role::GURU)->firstOrFail();

    $user = User::factory()->create([
        'username' => 'multi_role_user',
        'email' => 'multi-role@example.test',
        'is_active' => true,
    ]);
    $user->roles()->sync([$adminAkademik->id, $guru->id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/admin/dashboard')->assertOk();
    $this->getJson('/api/v1/guru/dashboard')->assertOk();
});

test('admin user management rejects super admin mixed with other roles', function () {
    $superAdminRole = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();
    $guruRole = Role::query()->where('name', Role::GURU)->firstOrFail();

    $actor = User::factory()->create([
        'username' => 'super_admin_actor',
        'email' => 'super-admin-actor@example.test',
        'is_active' => true,
    ]);
    $actor->roles()->sync([$superAdminRole->id]);

    Sanctum::actingAs($actor);

    $this->postJson('/api/v1/admin/users', [
        'name' => 'Invalid Multi Super Admin',
        'username' => 'invalid-super-mix',
        'email' => 'invalid-super-mix@example.test',
        'password' => 'password123',
        'role_ids' => [$superAdminRole->id, $guruRole->id],
        'is_active' => true,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['role_ids']);
});

test('login payload includes roles array for multi-role user', function () {
    $guru = Role::query()->where('name', Role::GURU)->firstOrFail();
    $wali = Role::query()->where('name', Role::WALI_SANTRI)->firstOrFail();

    $password = 'password123';
    $user = User::factory()->create([
        'username' => 'multi_login_user',
        'email' => 'multi-login@example.test',
        'password' => bcrypt($password),
        'is_active' => true,
    ]);
    $user->roles()->sync([$guru->id, $wali->id]);

    $response = $this->postJson('/api/v1/login', [
        'username' => 'multi_login_user',
        'password' => $password,
        'device_name' => 'PHPUnit',
    ])->assertOk()
        ->assertJsonPath('user.username', 'multi_login_user')
        ->assertJsonCount(2, 'roles');

    $roleNames = collect($response->json('roles'))->pluck('name')->sort()->values()->all();
    expect($roleNames)->toBe([Role::GURU, Role::WALI_SANTRI]);
});
