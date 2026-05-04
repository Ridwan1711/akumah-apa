<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\LessonAttendance;
use App\Models\LessonSession;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $guruRole = Role::query()->where('name', Role::GURU)->firstOrFail();
    $adminRole = Role::query()->where('name', Role::ADMIN_AKADEMIK)->firstOrFail();

    $this->guru = User::factory()->create([
        'is_active' => true,
        'must_change_password' => false,
        'must_complete_profile' => false,
    ]);
    $this->guru->roles()->sync([$guruRole->id]);

    $this->otherGuru = User::factory()->create([
        'is_active' => true,
        'must_change_password' => false,
        'must_complete_profile' => false,
    ]);
    $this->otherGuru->roles()->sync([$guruRole->id]);

    $this->admin = User::factory()->create([
        'is_active' => true,
        'must_change_password' => false,
        'must_complete_profile' => false,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);
});

test('guru can check in and check out attendance for today', function (): void {
    Sanctum::actingAs($this->guru);

    $this->postJson('/api/v1/guru/teacher-attendance/check-in')
        ->assertOk()
        ->assertJsonPath('attendance.teacher_id', $this->guru->id)
        ->assertJsonPath('attendance.check_out_at', null);

    $this->postJson('/api/v1/guru/teacher-attendance/check-out')
        ->assertOk()
        ->assertJsonPath('attendance.teacher_id', $this->guru->id);

    $this->getJson('/api/v1/guru/teacher-attendance/today')
        ->assertOk()
        ->assertJsonPath('attendance.teacher_id', $this->guru->id)
        ->assertJsonStructure([
            'date',
            'attendance' => [
                'id',
                'status',
                'check_in_at',
                'check_out_at',
            ],
        ]);

    expect(
        TeacherAttendance::query()
            ->where('teacher_id', $this->guru->id)
            ->whereDate('date', now()->toDateString())
            ->exists()
    )->toBeTrue();
});

test('guru recap is scoped to own attendance records', function (): void {
    TeacherAttendance::query()->create([
        'teacher_id' => $this->guru->id,
        'date' => now()->toDateString(),
        'status' => 'present',
        'check_in_at' => now()->subHours(2),
        'check_out_at' => now()->subHour(),
    ]);

    TeacherAttendance::query()->create([
        'teacher_id' => $this->otherGuru->id,
        'date' => now()->toDateString(),
        'status' => 'present',
        'check_in_at' => now()->subHours(2),
        'check_out_at' => now()->subHour(),
    ]);

    Sanctum::actingAs($this->guru);

    $response = $this->getJson('/api/v1/guru/attendance-recap');
    $response->assertOk()
        ->assertJsonPath('scope', 'guru')
        ->assertJsonCount(1, 'teacher_attendance.summary')
        ->assertJsonPath('teacher_attendance.summary.0.teacher_id', $this->guru->id)
        ->assertJsonCount(0, 'student_attendance_by_class');
});

test('admin recap includes teacher and student attendance summaries', function (): void {
    $academicYear = AcademicYear::query()->create([
        'name' => '2026/2027-recap',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $semester = Semester::query()->create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil',
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
    ]);
    $period = AcademicPeriod::query()->create([
        'academic_year_id' => $academicYear->id,
        'semester_id' => $semester->id,
        'is_active' => true,
    ]);

    $gradeLevel = GradeLevel::query()->create([
        'name' => 'Kelas Rekap',
        'order' => 1,
    ]);
    $class = SchoolClass::query()->create([
        'name' => 'Kelas Rekap API',
        'grade_level_id' => $gradeLevel->id,
        'order' => 1,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
    ]);
    $subject = Subject::query()->create(['name' => 'Mapel Rekap']);
    $schedule = AcademicSchedule::query()->create([
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $this->guru->id,
        'period_id' => $period->id,
        'day' => (int) now()->isoWeekday(),
        'time_start' => '08:00:00',
        'time_end' => '09:00:00',
    ]);
    $session = LessonSession::query()->create([
        'schedule_id' => $schedule->id,
        'semester_id' => $semester->id,
        'date' => now()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'completed',
        'created_by' => $this->guru->id,
    ]);

    $student = Student::factory()->create([
        'current_class_id' => $class->id,
        'status' => Student::STATUS_ACTIVE,
    ]);
    LessonAttendance::query()->create([
        'lesson_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'present',
        'marked_by' => $this->guru->id,
        'marked_at' => now(),
    ]);

    TeacherAttendance::query()->create([
        'teacher_id' => $this->guru->id,
        'date' => now()->toDateString(),
        'status' => 'present',
        'check_in_at' => now()->subHours(2),
        'check_out_at' => now()->subHour(),
    ]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/guru/attendance-recap')
        ->assertOk()
        ->assertJsonPath('scope', 'admin')
        ->assertJsonPath('teacher_attendance.summary.0.teacher_id', $this->guru->id)
        ->assertJsonPath('student_attendance_by_class.0.class_id', $class->id)
        ->assertJsonPath('student_attendance_by_class.0.present_count', 1);
});
