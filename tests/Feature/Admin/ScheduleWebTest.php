<?php

use App\Models\AcademicYear;
use App\Models\Diniyyah\AcademicPeriod;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();

    $this->seed(\Database\Seeders\RoleSeeder::class);

    $adminRole = Role::where('name', Role::ADMIN_AKADEMIK)->first();
    $guruRole = Role::where('name', Role::GURU)->first();

    $this->admin = User::factory()->create([
        'username' => 'admin_sched_test',
        'email_verified_at' => now(),
        'must_change_password' => false,
        'must_complete_profile' => false,
        'is_active' => true,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    $this->guru = User::factory()->create([
        'username' => 'guru_sched_test',
        'email_verified_at' => now(),
        'must_change_password' => false,
        'must_complete_profile' => false,
        'is_active' => true,
    ]);
    $this->guru->roles()->sync([$guruRole->id]);

    $academicYear = AcademicYear::create([
        'name' => '2024/2025',
        'start_date' => '2024-07-01',
        'end_date' => '2025-06-30',
        'is_active' => true,
    ]);

    $semester = Semester::create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil',
        'start_date' => '2024-07-01',
        'end_date' => '2024-12-31',
        'is_active' => true,
    ]);

    $this->period = AcademicPeriod::create([
        'name' => 'Periode Ganjil',
        'type' => AcademicPeriod::TYPE_SEMESTER_1,
        'is_active' => true,
        'semester_id' => $semester->id,
    ]);

    $this->gradeLevel = GradeLevel::create([
        'name' => 'Salafy 1',
        'order' => 1,
    ]);

    $this->schoolClass = SchoolClass::create([
        'name' => '1A',
        'grade_level_id' => $this->gradeLevel->id,
        'level_order' => 1,
        'level' => SchoolClass::LEVEL_SALAFY1,
    ]);

    $this->subject = Subject::create([
        'name' => 'Kitab '.uniqid('', true),
    ]);
});

test('admin akademik can view schedules index', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('admin.schedules.index', ['period_id' => $this->period->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/schedules/index'));
});

test('admin akademik can create schedule via web', function () {
    $this->actingAs($this->admin);

    $response = $this->from(route('admin.schedules.index', ['period_id' => $this->period->id]))
        ->post(route('admin.schedules.store'), [
            'class_id' => $this->schoolClass->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->guru->id,
            'period_id' => $this->period->id,
            'day' => 1,
            'time_start' => '07:00',
            'time_end' => '08:30',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('schedules', [
        'class_id' => $this->schoolClass->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru->id,
        'period_id' => $this->period->id,
        'day' => 1,
    ]);
});

test('admin akademik can update schedule via web', function () {
    $schedule = AcademicSchedule::create([
        'class_id' => $this->schoolClass->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru->id,
        'period_id' => $this->period->id,
        'day' => 2,
        'time_start' => '08:00',
        'time_end' => '09:00',
    ]);

    $this->actingAs($this->admin);

    $response = $this->from(route('admin.schedules.index', ['period_id' => $this->period->id]))
        ->put(route('admin.schedules.update', $schedule), [
            'class_id' => $this->schoolClass->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->guru->id,
            'period_id' => $this->period->id,
            'day' => 3,
            'time_start' => '09:00',
            'time_end' => '10:00',
        ]);

    $response->assertRedirect();
    expect($schedule->fresh()->day)->toBe(3);
});

test('admin akademik can delete schedule via web', function () {
    $schedule = AcademicSchedule::create([
        'class_id' => $this->schoolClass->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru->id,
        'period_id' => $this->period->id,
        'day' => 4,
        'time_start' => '10:00',
        'time_end' => '11:00',
    ]);

    $this->actingAs($this->admin);

    $response = $this->delete(route('admin.schedules.destroy', $schedule).'?period_id='.$this->period->id);

    $response->assertRedirect();
    $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
});
