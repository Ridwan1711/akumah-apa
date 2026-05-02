<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $guruRole = Role::query()->where('name', Role::GURU)->firstOrFail();
    $this->guru = User::factory()->create([
        'is_active' => true,
        'must_change_password' => false,
        'must_complete_profile' => false,
    ]);
    $this->guru->roles()->sync([$guruRole->id]);
});

test('api guru dashboard returns enriched payload shape', function (): void {
    Sanctum::actingAs($this->guru);

    $this->getJson('/api/v1/guru/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'assignments',
            'waliKelasClasses',
            'active_semester',
            'active_period_id',
            'today_schedule_slots',
            'sessions_pending_attendance_today',
        ]);
});
