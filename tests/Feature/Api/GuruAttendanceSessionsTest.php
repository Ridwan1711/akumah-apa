<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    $academicYear = AcademicYear::query()->create([
        'name' => '2026/2027-sessions',
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
        'name' => 'Level Sesi',
        'order' => 1,
    ]);
    $this->class = SchoolClass::query()->create([
        'name' => 'Kelas Sesi',
        'grade_level_id' => $level->id,
        'order' => 1,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
    ]);
    $this->subject = Subject::query()->create(['name' => 'Mapel Sesi']);
});

test('api guru sessions returns lesson sessions for schedules on requested date', function (): void {
    $today = Carbon::now(config('app.timezone'));
    $date = $today->toDateString();
    $dow = (int) $today->isoWeekday();

    AcademicSchedule::query()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru->id,
        'period_id' => $this->period->id,
        'day' => $dow,
        'time_start' => '13:00:00',
        'time_end' => '14:30:00',
    ]);

    Sanctum::actingAs($this->guru);

    $this->getJson('/api/v1/guru/sessions?date='.$date)
        ->assertOk()
        ->assertJsonPath('date', $date)
        ->assertJsonCount(1, 'sessions')
        ->assertJsonPath('sessions.0.class.name', $this->class->name)
        ->assertJsonPath('sessions.0.subject.name', $this->subject->name)
        ->assertJsonStructure([
            'sessions' => [
                [
                    'id',
                    'class' => ['id', 'name', 'grade_level_id'],
                    'subject' => ['id', 'name'],
                ],
            ],
        ]);
});
