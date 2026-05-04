<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\ClassSubject;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\LessonAttendance;
use App\Models\LessonSession;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $guruRole = Role::query()->where('name', Role::GURU)->firstOrFail();
    $this->guru = User::factory()->create([
        'is_active' => true,
        'must_change_password' => false,
        'must_complete_profile' => false,
    ]);
    $this->guru->roles()->sync([$guruRole->id]);

    $academicYear = AcademicYear::query()->create([
        'name' => '2026/2027-audit-la',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->semester = Semester::query()->create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil-audit-la',
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
    ]);
    $this->period = AcademicPeriod::query()->create([
        'academic_year_id' => $academicYear->id,
        'semester_id' => $this->semester->id,
        'is_active' => true,
    ]);

    $level = GradeLevel::query()->create([
        'name' => 'Level Audit LA',
        'order' => 1,
    ]);
    $this->schoolClass = SchoolClass::query()->create([
        'name' => 'Kelas Audit LA',
        'grade_level_id' => $level->id,
        'order' => 1,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
    ]);
    $this->subject = Subject::query()->create(['name' => 'Kitab Audit LA']);
    ClassSubject::query()->create([
        'class_id' => $this->schoolClass->id,
        'subject_id' => $this->subject->id,
        'period_id' => $this->period->id,
        'has_score' => true,
        'is_active' => true,
    ]);

    TeacherAssignment::query()->create([
        'teacher_id' => $this->guru->id,
        'class_id' => $this->schoolClass->id,
        'subject_id' => $this->subject->id,
        'period_id' => $this->period->id,
        'target_jam' => 1,
    ]);

    $this->student = Student::query()->create([
        'nis' => 'AUDIT-LA-001',
        'full_name' => 'Santri Audit LA',
        'gender' => Student::GENDER_MALE,
        'status' => Student::STATUS_ACTIVE,
        'admission_year' => 2026,
        'current_class_id' => $this->schoolClass->id,
    ]);

    $this->schedule = AcademicSchedule::query()->create([
        'class_id' => $this->schoolClass->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->guru->id,
        'period_id' => $this->period->id,
        'day' => 1,
        'time_start' => '07:00',
        'time_end' => '08:00',
    ]);

    $this->lessonSession = LessonSession::query()->create([
        'schedule_id' => $this->schedule->id,
        'semester_id' => $this->semester->id,
        'date' => '2026-05-02',
        'start_time' => '07:00:00',
        'end_time' => '08:00:00',
        'status' => 'planned',
        'created_by' => $this->guru->id,
    ]);
});

test('creating lesson attendance writes audit log entry', function () {
    $this->actingAs($this->guru);

    LessonAttendance::query()->create([
        'lesson_session_id' => $this->lessonSession->id,
        'student_id' => $this->student->id,
        'status' => 'present',
        'reason' => null,
        'leave_permission_id' => null,
        'marked_by' => $this->guru->id,
        'marked_at' => now(),
    ]);

    $log = AuditLog::query()->where('module', 'lessonattendance')->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('create');
});

test('guru activity api returns lesson attendance summary', function () {
    $this->actingAs($this->guru);

    LessonAttendance::query()->create([
        'lesson_session_id' => $this->lessonSession->id,
        'student_id' => $this->student->id,
        'status' => 'present',
        'reason' => null,
        'leave_permission_id' => null,
        'marked_by' => $this->guru->id,
        'marked_at' => now(),
    ]);

    Sanctum::actingAs($this->guru);

    $response = $this->getJson('/api/v1/activity')->assertOk();

    $lines = collect($response->json('data'))->pluck('summary_line')->implode('|');

    expect($lines)->toContain('Kehadiran pelajaran dicatat untuk Santri Audit LA (Hadir)');
});

test('guru activity feed excludes audit rows created by other users', function () {
    $this->actingAs($this->guru);

    $attendance = LessonAttendance::query()->create([
        'lesson_session_id' => $this->lessonSession->id,
        'student_id' => $this->student->id,
        'status' => 'present',
        'reason' => null,
        'leave_permission_id' => null,
        'marked_by' => $this->guru->id,
        'marked_at' => now(),
    ]);

    $otherGuru = User::factory()->create([
        'is_active' => true,
        'must_change_password' => false,
        'must_complete_profile' => false,
    ]);
    $otherGuru->roles()->sync([Role::query()->where('name', Role::GURU)->value('id')]);

    AuditLog::query()->create([
        'user_id' => $otherGuru->id,
        'module' => 'lessonattendance',
        'action' => 'create',
        'auditable_type' => $attendance->getMorphClass(),
        'auditable_id' => $attendance->id,
        'old_data' => null,
        'new_data' => ['status' => 'present'],
        'created_at' => now(),
    ]);

    Sanctum::actingAs($this->guru);

    $data = $this->getJson('/api/v1/activity')->assertOk()->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['summary_line'])->toBeString()->not->toBe('');
});
