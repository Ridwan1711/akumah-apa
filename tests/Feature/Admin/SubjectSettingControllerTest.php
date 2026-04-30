<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\GradeSubject;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\SubjectLevelSetting;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        'name' => '2025/2026',
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
        'is_active' => true,
    ]);

    $this->semester = Semester::query()->create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil',
        'start_date' => '2025-07-01',
        'end_date' => '2025-12-31',
        'is_active' => true,
    ]);

    $this->period = AcademicPeriod::query()->create([
        'academic_year_id' => $academicYear->id,
        'is_active' => true,
        'semester_id' => $this->semester->id,
    ]);

    $this->level = GradeLevel::query()->create([
        'name' => 'Salafy 1',
        'order' => 1,
    ]);

    $this->subject = Subject::query()->create([
        'name' => 'Fiqih',
    ]);

    $this->schoolClass = SchoolClass::query()->create([
        'name' => '1A',
        'grade_level_id' => $this->level->id,
        'order' => 1,
        'student_gender' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
    ]);
});

test('admin akademik can view subject settings page', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('admin.subject-settings.index', ['semester_id' => $this->semester->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/subject-settings/index'));
});

test('admin akademik can view subject level mapping page', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('admin.subject-level-mappings.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/subject-level-mappings/index'));
});

test('admin akademik can upsert level setting', function () {
    $this->actingAs($this->admin);
    GradeSubject::query()->create([
        'subject_id' => $this->subject->id,
        'grade_level_id' => $this->level->id,
    ]);

    $response = $this->post(route('admin.subject-settings.level.store'), [
        'semester_id' => $this->semester->id,
        'subject_id' => $this->subject->id,
        'level_id' => $this->level->id,
        'is_taught' => true,
        'default_hours' => 3,
        'is_assessed' => false,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('level_subject_defaults', [
        'period_id' => $this->period->id,
        'subject_id' => $this->subject->id,
        'level_id' => $this->level->id,
        'is_mandatory_teaching' => true,
        'target_jam_default' => 3,
        'has_score_default' => false,
    ]);
});

test('admin akademik can manage class override hours', function () {
    $this->actingAs($this->admin);
    GradeSubject::query()->create([
        'subject_id' => $this->subject->id,
        'grade_level_id' => $this->level->id,
    ]);

    $levelSetting = SubjectLevelSetting::query()->create([
        'period_id' => $this->period->id,
        'subject_id' => $this->subject->id,
        'level_id' => $this->level->id,
        'is_mandatory_teaching' => true,
        'target_jam_default' => 2,
        'has_score_default' => true,
    ]);

    $storeResponse = $this->post(route('admin.subject-settings.class-override.store'), [
        'semester_id' => $this->semester->id,
        'subject_id' => $this->subject->id,
        'level_id' => $this->level->id,
        'class_id' => $this->schoolClass->id,
        'override_hours' => 4,
    ]);

    $storeResponse->assertRedirect();
    $this->assertDatabaseHas('subject_class_overrides', [
        'level_subject_default_id' => $levelSetting->id,
        'class_id' => $this->schoolClass->id,
        'override_hours' => 4,
    ]);

    $overrideId = (int) DB::table('subject_class_overrides')->value('id');
    $deleteResponse = $this->delete(route('admin.subject-settings.class-override.destroy', $overrideId), [
        'semester_id' => $this->semester->id,
    ]);

    $deleteResponse->assertRedirect();
    $this->assertDatabaseMissing('subject_class_overrides', [
        'id' => $overrideId,
    ]);
});

test('admin akademik can assign and unassign subject to level', function () {
    $this->actingAs($this->admin);

    $assign = $this->post(route('admin.subject-settings.assign-level'), [
        'semester_id' => $this->semester->id,
        'subject_id' => $this->subject->id,
        'level_id' => $this->level->id,
    ]);
    $assign->assertRedirect();
    $this->assertDatabaseHas('grade_subjects', [
        'subject_id' => $this->subject->id,
        'grade_level_id' => $this->level->id,
    ]);

    $remove = $this->delete(route('admin.subject-settings.remove-level'), [
        'semester_id' => $this->semester->id,
        'subject_id' => $this->subject->id,
        'level_id' => $this->level->id,
    ]);
    $remove->assertRedirect();
    $this->assertDatabaseMissing('grade_subjects', [
        'subject_id' => $this->subject->id,
        'grade_level_id' => $this->level->id,
    ]);
});

test('admin akademik can bulk sync subject level mappings', function () {
    $this->actingAs($this->admin);

    $secondLevel = GradeLevel::query()->create([
        'name' => 'Salafy 2',
        'order' => 2,
    ]);

    $secondSubject = Subject::query()->create([
        'name' => 'Nahwu',
    ]);

    $response = $this->post(route('admin.subject-level-mappings.sync'), [
        'level_ids' => [$this->level->id, $secondLevel->id],
        'subject_ids' => [$this->subject->id, $secondSubject->id],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseCount('grade_subjects', 4);
});
