<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\AssessmentComponent;
use App\Models\Diniyyah\ClassSubject;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\KitabGradeSession;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\TeacherAssignment;
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
        'name' => '2026/2027-api-lock',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->semester = Semester::query()->create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil-api-lock',
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
    ]);
    $this->period = AcademicPeriod::query()->create([
        'academic_year_id' => $academicYear->id,
        'semester_id' => $this->semester->id,
        'is_active' => true,
    ]);

    $level = GradeLevel::query()->create([
        'name' => 'Level API Lock',
        'order' => 1,
    ]);
    $this->class = SchoolClass::query()->create([
        'name' => 'Kelas API Lock',
        'grade_level_id' => $level->id,
        'order' => 1,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
    ]);
    $this->subject = Subject::query()->create(['name' => 'Fiqih API Lock']);
    ClassSubject::query()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'period_id' => $this->period->id,
        'has_score' => true,
        'is_active' => true,
    ]);
    $this->student = Student::query()->create([
        'nis' => 'API-LOCK-001',
        'full_name' => 'Santri API Lock',
        'gender' => 'L',
        'status' => Student::STATUS_ACTIVE,
        'admission_year' => 2026,
        'current_class_id' => $this->class->id,
    ]);
    $this->componentCore = AssessmentComponent::query()->create([
        'name' => 'UTS API Core',
        'type' => AssessmentComponent::TYPE_EXAM,
        'is_core_required' => true,
    ]);
    $this->componentDaily = AssessmentComponent::query()->create([
        'name' => 'Harian API',
        'type' => AssessmentComponent::TYPE_DAILY,
        'is_core_required' => false,
    ]);

    TeacherAssignment::query()->create([
        'teacher_id' => $this->guru->id,
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'period_id' => $this->period->id,
        'target_jam' => 1,
    ]);
});

test('api guru kitab grades stores locked component session on first save', function () {
    Sanctum::actingAs($this->guru);

    $this->postJson('/api/v1/guru/kitab-grades', [
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'semester_id' => $this->semester->id,
        'component_id' => $this->componentCore->id,
        'grades' => [[
            'student_id' => $this->student->id,
            'score' => 85,
            'notes' => '',
        ]],
    ])->assertOk();

    expect(
        KitabGradeSession::query()
            ->where('teacher_id', $this->guru->id)
            ->where('class_id', $this->class->id)
            ->where('subject_id', $this->subject->id)
            ->where('period_id', $this->period->id)
            ->value('active_component_ids')
    )->toEqual([$this->componentCore->id]);
});

test('api guru kitab grades rejects changing locked component in same semester', function () {
    Sanctum::actingAs($this->guru);

    $this->postJson('/api/v1/guru/kitab-grades', [
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'semester_id' => $this->semester->id,
        'component_id' => $this->componentCore->id,
        'grades' => [[
            'student_id' => $this->student->id,
            'score' => 85,
            'notes' => '',
        ]],
    ])->assertOk();

    $this->postJson('/api/v1/guru/kitab-grades', [
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'semester_id' => $this->semester->id,
        'component_id' => $this->componentDaily->id,
        'grades' => [[
            'student_id' => $this->student->id,
            'score' => 90,
            'notes' => '',
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors(['component_id']);
});
