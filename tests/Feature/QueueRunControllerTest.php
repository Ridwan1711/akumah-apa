<?php

use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Support\Str;

test('guests cannot list queue runs', function () {
    $this->get(route('queue-runs.index'))->assertRedirect(route('login'));
});

test('authenticated user receives queue runs json with active count not limited to the list page', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);

    for ($i = 0; $i < 16; $i++) {
        ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_STUDENTS,
            'job_type' => ImportRun::JOB_STUDENT_IMPORT,
            'strategy' => 'skip',
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => $user->id,
            'file_name' => "x{$i}.csv",
            'file_path' => "imports/x{$i}.csv",
        ]);
    }
    for ($i = 0; $i < 4; $i++) {
        ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_STUDENTS,
            'job_type' => ImportRun::JOB_STUDENT_IMPORT,
            'strategy' => 'skip',
            'status' => ImportRun::STATUS_COMPLETED,
            'requested_by' => $user->id,
            'file_name' => "done{$i}.csv",
            'file_path' => "imports/done{$i}.csv",
        ]);
    }

    $this->actingAs($user);

    $response = $this->getJson(route('queue-runs.index', ['limit' => 15]));

    $response->assertOk();
    $response->assertJsonPath('meta.active_count', 16);
    $response->assertJsonPath('meta.current_scope', 'my');
    expect($response->json('data'))->toHaveCount(15);
});

test('queue runs index only includes current user runs for default scope', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);
    $other = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);

    ImportRun::query()->create([
        'uuid' => (string) Str::uuid(),
        'type' => ImportRun::TYPE_STUDENTS,
        'job_type' => ImportRun::JOB_STUDENT_IMPORT,
        'strategy' => 'skip',
        'status' => ImportRun::STATUS_COMPLETED,
        'requested_by' => $other->id,
        'file_name' => 'other.csv',
        'file_path' => 'imports/other.csv',
    ]);

    ImportRun::query()->create([
        'uuid' => (string) Str::uuid(),
        'type' => ImportRun::TYPE_STUDENTS,
        'job_type' => ImportRun::JOB_STUDENT_IMPORT,
        'strategy' => 'skip',
        'status' => ImportRun::STATUS_PROCESSING,
        'requested_by' => $user->id,
        'file_name' => 'mine.csv',
        'file_path' => 'imports/mine.csv',
    ]);

    $this->actingAs($user);
    $response = $this->getJson(route('queue-runs.index'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.file_name', 'mine.csv');
    $response->assertJsonPath('meta.active_count', 1);
});
