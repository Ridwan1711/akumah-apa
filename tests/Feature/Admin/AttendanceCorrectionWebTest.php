<?php

use App\Models\AcademicYear;
use App\Models\Diniyyah\AcademicPeriod;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\LessonAttendance;
use App\Models\LessonSession;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();

    $this->seed(\Database\Seeders\RoleSeeder::class);

    $adminRole = Role::where('name', Role::ADMIN_AKADEMIK)->first();
    $guruRole = Role::where('name', Role::GURU)->first();

    $this->admin = User::factory()->create([
        'username' => 'admin_att_test',
        'email_verified_at' => now(),
        'must_change_password' => false,
        'must_complete_profile' => false,
        'is_active' => true,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    $this->guru = User::factory()->create([
        'username' => 'guru_att_test',
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

    $gradeLevel = GradeLevel::create([
        'name' => 'Salafy 1',
        'order' => 1,
    ]);

    $this->schoolClass = SchoolClass::create([
        'name' => '1B',
        'grade_level_id' => $gradeLevel->id,
        'level_order' => 1,
        'level' => SchoolClass::LEVEL_SALAFY1,
    ]);

    $subject = Subject::create([
        'name' => 'Kitab '.uniqid('', true),
    ]);

    $this->schedule = AcademicSchedule::create([
        'class_id' => $this->schoolClass->id,
        'subject_id' => $subject->id,
        'teacher_id' => $this->guru->id,
        'period_id' => $this->period->id,
        'day' => 1,
        'time_start' => '07:00',
        'time_end' => '08:00',
    ]);

    $this->session = LessonSession::create([
        'schedule_id' => $this->schedule->id,
        'semester_id' => $semester->id,
        'date' => '2025-01-15',
        'start_time' => '07:00:00',
        'end_time' => '08:00:00',
        'status' => 'planned',
        'created_by' => $this->guru->id,
    ]);

    $this->student = Student::create([
        'nis' => 'NIS-'.uniqid(),
        'full_name' => 'Santri Test',
        'gender' => 'male',
        'admission_year' => 2024,
        'current_class_id' => $this->schoolClass->id,
        'status' => Student::STATUS_ACTIVE,
    ]);

    $this->attendance = LessonAttendance::create([
        'lesson_session_id' => $this->session->id,
        'student_id' => $this->student->id,
        'status' => 'absent',
        'reason' => null,
        'leave_permission_id' => null,
        'marked_by' => $this->guru->id,
        'marked_at' => now(),
    ]);
});

test('admin akademik can correct attendance via web', function () {
    $this->actingAs($this->admin);

    $response = $this->from(route('admin.attendances.index'))
        ->put(route('admin.attendances.update', $this->attendance), [
            'status' => 'present',
            'reason' => 'Koreksi admin',
        ]);

    $response->assertRedirect(route('admin.attendances.index'));
    expect($this->attendance->fresh()->status)->toBe('present');
    expect($this->attendance->fresh()->reason)->toBe('Koreksi admin');
});
