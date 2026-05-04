<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\LessonSession;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $this->guruRole = Role::query()->where('name', Role::GURU)->firstOrFail();
    $this->adminRole = Role::query()->where('name', Role::ADMIN_AKADEMIK)->firstOrFail();
    $this->santriRole = Role::query()->where('name', Role::SANTRI)->firstOrFail();

    $this->guru = User::factory()->create();
    $this->guru->roles()->sync([$this->guruRole->id]);

    $this->admin = User::factory()->create();
    $this->admin->roles()->sync([$this->adminRole->id]);

    $level = GradeLevel::query()->create(['name' => 'Geo Level', 'order' => 1]);
    $class = SchoolClass::query()->create([
        'name' => 'Geo Class',
        'grade_level_id' => $level->id,
        'order' => 1,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
    ]);
    $subject = Subject::query()->create(['name' => 'Fiqih']);

    $year = AcademicYear::query()->create([
        'name' => '2026/2027-geo',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $semester = Semester::query()->create([
        'academic_year_id' => $year->id,
        'name' => 'Ganjil',
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
    ]);
    $period = AcademicPeriod::query()->create([
        'academic_year_id' => $year->id,
        'semester_id' => $semester->id,
        'is_active' => true,
    ]);

    $schedule = AcademicSchedule::query()->create([
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $this->guru->id,
        'period_id' => $period->id,
        'day' => 1,
        'time_start' => '08:00:00',
        'time_end' => '09:00:00',
    ]);

    $this->session = LessonSession::query()->create([
        'schedule_id' => $schedule->id,
        'semester_id' => $semester->id,
        'date' => now()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'planned',
        'created_by' => $this->guru->id,
    ]);

    $this->student = Student::query()->create([
        'nis' => 'GEO-001',
        'full_name' => 'Santri Geo',
        'gender' => Student::GENDER_MALE,
        'status' => Student::STATUS_ACTIVE,
        'admission_year' => 2026,
        'current_class_id' => $class->id,
    ]);
});

test('store attendance remains successful and returns warnings', function (): void {
    config()->set('geo_attendance.geofence.latitude', 0.0);
    config()->set('geo_attendance.geofence.longitude', 0.0);
    config()->set('geo_attendance.geofence.radius_meters', 100);

    Sanctum::actingAs($this->guru);

    $response = $this->postJson("/api/v1/guru/sessions/{$this->session->id}/attendance", [
        'attendances' => [
            [
                'student_id' => $this->student->id,
                'status' => 'present',
            ],
        ],
        'meta' => [
            'latitude' => -6.2,
            'longitude' => 106.8,
            'accuracy_meters' => 20,
            'device_recorded_at' => now()->addHours(5)->toIso8601String(),
            'is_location_enabled' => true,
            'source' => 'foreground',
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Kehadiran santri berhasil disimpan.')
        ->assertJsonPath('warnings.0.code', 'time_outside_window')
        ->assertJsonPath('warnings.1.code', 'outside_geofence');
});

test('guru location ping can be read by admin only', function (): void {
    Sanctum::actingAs($this->guru);

    $this->postJson('/api/v1/guru/location-ping', [
        'latitude' => -6.3,
        'longitude' => 106.7,
        'source' => 'background',
        'app_state' => 'background',
        'is_location_enabled' => true,
    ])->assertOk()->assertJsonPath('accepted', true);

    Sanctum::actingAs($this->admin);
    $this->getJson('/api/v1/admin/teacher-location/latest')
        ->assertOk()
        ->assertJsonStructure(['latest_locations']);

    $santri = User::factory()->create();
    $santri->roles()->sync([$this->santriRole->id]);
    Sanctum::actingAs($santri);
    $this->getJson('/api/v1/admin/teacher-location/latest')->assertForbidden();
});
