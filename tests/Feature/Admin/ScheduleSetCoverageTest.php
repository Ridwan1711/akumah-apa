<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\ScheduleSet;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\SubjectLevelSetting;
use App\Models\Diniyyah\TeacherAssignment;
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
        'name' => '2025/2026-cov',
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
        'is_active' => true,
    ]);

    $this->semester = Semester::query()->create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil-cov',
        'start_date' => '2025-07-01',
        'end_date' => '2025-12-31',
        'is_active' => true,
    ]);

    $this->period = AcademicPeriod::query()->create([
        'academic_year_id' => $academicYear->id,
        'is_active' => true,
        'semester_id' => $this->semester->id,
    ]);
});

test('schedule sets index includes unmet coverage using effective target hours', function () {
    $this->actingAs($this->admin);

    $level = GradeLevel::query()->create(['name' => 'Salafy Coverage', 'order' => 1]);
    $class = SchoolClass::query()->create([
        'name' => '1-Cov',
        'grade_level_id' => $level->id,
        'order' => 1,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
    ]);
    $subject = Subject::query()->create(['name' => 'Fiqih Coverage']);
    $teacher = User::factory()->create();

    TeacherAssignment::query()->create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'period_id' => $this->period->id,
        'target_jam' => 0,
    ]);

    SubjectLevelSetting::query()->create([
        'period_id' => $this->period->id,
        'subject_id' => $subject->id,
        'level_id' => $level->id,
        'has_score_default' => true,
        'target_jam_default' => 3,
        'is_mandatory_teaching' => true,
    ]);

    $set = ScheduleSet::query()->create([
        'period_id' => $this->period->id,
        'name' => 'Coverage Set',
        'jam_count' => 6,
        'day_count' => 6,
        'is_active' => true,
    ]);

    AcademicSchedule::query()->create([
        'schedule_set_id' => $set->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'period_id' => $this->period->id,
        'day' => 1,
        'jam_no' => 1,
        'time_start' => '07:00',
        'time_end' => '07:45',
    ]);

    $response = $this->get(route('admin.schedule-sets.index', ['semester_id' => $this->semester->id]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('admin/schedules/sets-index')
            ->where('sets.0.id', $set->id)
            ->where('sets.0.unmet_pengampu_count', 1)
            ->where('sets.0.unmet_jam_total', 2)
    );
});
