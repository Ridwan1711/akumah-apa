<?php

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\ScheduleSet;
use App\Models\Diniyyah\ScheduleTimeSlot;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use App\Services\Schedule\ScheduleMatrixService;

beforeEach(function () {
    $this->withoutVite();

    $this->seed(\Database\Seeders\RoleSeeder::class);

    $adminRole = Role::where('name', Role::ADMIN_AKADEMIK)->first();
    $guruRole = Role::where('name', Role::GURU)->first();

    $this->admin = User::factory()->create([
        'username' => 'admin_matrix_'.uniqid(),
        'email_verified_at' => now(),
        'must_change_password' => false,
        'must_complete_profile' => false,
        'is_active' => true,
    ]);
    $this->admin->roles()->sync([$adminRole->id]);

    $this->guru = User::factory()->create([
        'username' => 'guru_matrix_'.uniqid(),
        'email_verified_at' => now(),
        'must_change_password' => false,
        'must_complete_profile' => false,
        'is_active' => true,
    ]);
    $this->guru->roles()->sync([$guruRole->id]);

    $academicYear = AcademicYear::create([
        'name' => '2024/2025-mx',
        'start_date' => '2024-07-01',
        'end_date' => '2025-06-30',
        'is_active' => true,
    ]);

    $semester = Semester::create([
        'academic_year_id' => $academicYear->id,
        'name' => 'Ganjil-mx',
        'start_date' => '2024-07-01',
        'end_date' => '2024-12-31',
        'is_active' => true,
    ]);

    $this->period = AcademicPeriod::create([
        'name' => 'Periode Matrix',
        'type' => AcademicPeriod::TYPE_SEMESTER_1,
        'is_active' => true,
        'semester_id' => $semester->id,
    ]);

    $this->gradeLevel = GradeLevel::create(['name' => 'Salafy-mx', 'order' => 1]);

    $this->classA = SchoolClass::create([
        'name' => 'A-mx',
        'grade_level_id' => $this->gradeLevel->id,
        'level_order' => 1,
        'level' => SchoolClass::LEVEL_SALAFY1,
    ]);
    $this->classB = SchoolClass::create([
        'name' => 'B-mx',
        'grade_level_id' => $this->gradeLevel->id,
        'level_order' => 2,
        'level' => SchoolClass::LEVEL_SALAFY1,
    ]);

    $this->subjectMath = Subject::create(['name' => 'Math-mx-'.uniqid()]);
    $this->subjectFiqh = Subject::create(['name' => 'Fiqh-mx-'.uniqid()]);

    $this->pengampuA = TeacherAssignment::create([
        'teacher_id' => $this->guru->id,
        'class_id' => $this->classA->id,
        'subject_id' => $this->subjectMath->id,
        'period_id' => $this->period->id,
        'target_jam' => 2,
    ]);
    $this->pengampuB = TeacherAssignment::create([
        'teacher_id' => $this->guru->id,
        'class_id' => $this->classB->id,
        'subject_id' => $this->subjectMath->id,
        'period_id' => $this->period->id,
        'target_jam' => 2,
    ]);
    $this->pengampuBFiqh = TeacherAssignment::create([
        'teacher_id' => $this->guru->id,
        'class_id' => $this->classB->id,
        'subject_id' => $this->subjectFiqh->id,
        'period_id' => $this->period->id,
        'target_jam' => 2,
    ]);

    $this->set = ScheduleSet::create([
        'period_id' => $this->period->id,
        'name' => 'Matrix Utama '.uniqid(),
        'jam_count' => 6,
        'day_count' => 6,
        'is_active' => true,
    ]);
    ScheduleTimeSlot::create([
        'schedule_set_id' => $this->set->id,
        'jam_no' => 1,
        'time_start' => '07:00',
        'time_end' => '07:45',
    ]);
    ScheduleTimeSlot::create([
        'schedule_set_id' => $this->set->id,
        'jam_no' => 2,
        'time_start' => '07:45',
        'time_end' => '08:30',
    ]);
});

test('admin can view schedule sets index', function () {
    $this->actingAs($this->admin);
    $this->get(route('admin.schedule-sets.index', ['period_id' => $this->period->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/schedules/sets-index'));
});

test('admin can create schedule set', function () {
    $this->actingAs($this->admin);
    $this->post(route('admin.schedule-sets.store'), [
        'period_id' => $this->period->id,
        'name' => 'Set Baru',
        'jam_count' => 5,
        'day_count' => 6,
        'is_active' => false,
    ])->assertRedirect();

    $this->assertDatabaseHas('schedule_sets', [
        'period_id' => $this->period->id,
        'name' => 'Set Baru',
        'jam_count' => 5,
    ]);
});

test('activating a set deactivates others in same period', function () {
    $other = ScheduleSet::create([
        'period_id' => $this->period->id,
        'name' => 'Other-set',
        'jam_count' => 6,
        'day_count' => 6,
        'is_active' => false,
    ]);

    $this->actingAs($this->admin);
    $this->patch(route('admin.schedule-sets.activate', $other))->assertRedirect();

    expect($other->fresh()->is_active)->toBeTrue();
    expect($this->set->fresh()->is_active)->toBeFalse();
});

test('assign cell creates schedule when no conflict', function () {
    /** @var ScheduleMatrixService $svc */
    $svc = app(ScheduleMatrixService::class);
    $schedule = $svc->assignCell($this->set, $this->pengampuA, 1, 1, ScheduleMatrixService::ACTION_ASSIGN);

    expect($schedule->exists)->toBeTrue();
    expect($schedule->class_id)->toBe($this->classA->id);
    expect($schedule->jam_no)->toBe(1);
    expect((string) $schedule->time_start)->toContain('07:00');
});

test('conflict detected when same teacher same subject different class at same slot', function () {
    /** @var ScheduleMatrixService $svc */
    $svc = app(ScheduleMatrixService::class);
    $svc->assignCell($this->set, $this->pengampuA, 1, 1, ScheduleMatrixService::ACTION_ASSIGN);

    $conflict = $svc->checkConflict($this->set, $this->pengampuB, 1, 1);
    expect($conflict['type'])->toBe(ScheduleMatrixService::CONFLICT_SAME_SUBJECT_OTHER_CLASS);
});

test('merge action attaches combined group id to both cells', function () {
    /** @var ScheduleMatrixService $svc */
    $svc = app(ScheduleMatrixService::class);
    $svc->assignCell($this->set, $this->pengampuA, 1, 1, ScheduleMatrixService::ACTION_ASSIGN);
    $merged = $svc->assignCell($this->set, $this->pengampuB, 1, 1, ScheduleMatrixService::ACTION_MERGE);

    expect($merged->combined_group_id)->not->toBeNull();

    $rows = AcademicSchedule::where('schedule_set_id', $this->set->id)
        ->where('day', 1)
        ->where('jam_no', 1)
        ->get();
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('combined_group_id')->unique())->toHaveCount(1);
});

test('conflict detected when same teacher different subject at same slot', function () {
    /** @var ScheduleMatrixService $svc */
    $svc = app(ScheduleMatrixService::class);
    $svc->assignCell($this->set, $this->pengampuA, 1, 1, ScheduleMatrixService::ACTION_ASSIGN);

    $conflict = $svc->checkConflict($this->set, $this->pengampuBFiqh, 1, 1);
    expect($conflict['type'])->toBe(ScheduleMatrixService::CONFLICT_DIFFERENT_SUBJECT_OTHER_CLASS);
});

test('replace across classes deletes previous schedule', function () {
    /** @var ScheduleMatrixService $svc */
    $svc = app(ScheduleMatrixService::class);
    $old = $svc->assignCell($this->set, $this->pengampuA, 1, 1, ScheduleMatrixService::ACTION_ASSIGN);
    $svc->assignCell($this->set, $this->pengampuBFiqh, 1, 1, ScheduleMatrixService::ACTION_REPLACE);

    $this->assertDatabaseMissing('schedules', ['id' => $old->id]);
    $this->assertDatabaseHas('schedules', [
        'schedule_set_id' => $this->set->id,
        'class_id' => $this->classB->id,
        'subject_id' => $this->subjectFiqh->id,
        'day' => 1,
        'jam_no' => 1,
    ]);
});

test('delete cell clears combined group when only one remains', function () {
    /** @var ScheduleMatrixService $svc */
    $svc = app(ScheduleMatrixService::class);
    $a = $svc->assignCell($this->set, $this->pengampuA, 1, 1, ScheduleMatrixService::ACTION_ASSIGN);
    $svc->assignCell($this->set, $this->pengampuB, 1, 1, ScheduleMatrixService::ACTION_MERGE);

    $svc->deleteCell($a->fresh());

    $remaining = AcademicSchedule::where('schedule_set_id', $this->set->id)
        ->where('day', 1)->where('jam_no', 1)->first();
    expect($remaining)->not->toBeNull();
    expect($remaining->combined_group_id)->toBeNull();
});

test('preflight endpoint returns conflict type', function () {
    /** @var ScheduleMatrixService $svc */
    $svc = app(ScheduleMatrixService::class);
    $svc->assignCell($this->set, $this->pengampuA, 1, 1, ScheduleMatrixService::ACTION_ASSIGN);

    $this->actingAs($this->admin);
    $this->postJson(route('admin.schedule-sets.cells.preflight', $this->set), [
        'pengampu_id' => $this->pengampuB->id,
        'day' => 1,
        'jam_no' => 1,
    ])->assertOk()
        ->assertJson(['type' => ScheduleMatrixService::CONFLICT_SAME_SUBJECT_OTHER_CLASS]);
});

test('assign is blocked when allocation reaches target jam', function () {
    /** @var ScheduleMatrixService $svc */
    $svc = app(ScheduleMatrixService::class);
    $this->pengampuA->update(['target_jam' => 1]);
    $svc->assignCell($this->set, $this->pengampuA->fresh(), 1, 1, ScheduleMatrixService::ACTION_ASSIGN);

    $conflict = $svc->checkConflict($this->set, $this->pengampuA->fresh(), 1, 2);
    expect($conflict['type'])->toBe(ScheduleMatrixService::CONFLICT_TARGET_REACHED);

    expect(fn () => $svc->assignCell($this->set, $this->pengampuA->fresh(), 1, 2, ScheduleMatrixService::ACTION_ASSIGN))
        ->toThrow(\InvalidArgumentException::class);
});

test('assign endpoint rejects pengampu from different period', function () {
    $otherYear = AcademicYear::create([
        'name' => '2025-other',
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
        'is_active' => false,
    ]);
    $otherSem = Semester::create([
        'academic_year_id' => $otherYear->id,
        'name' => 'Other-mx',
        'start_date' => '2025-07-01',
        'end_date' => '2025-12-31',
        'is_active' => false,
    ]);
    $otherPeriod = AcademicPeriod::create([
        'name' => 'Other Period',
        'type' => AcademicPeriod::TYPE_SEMESTER_1,
        'is_active' => false,
        'semester_id' => $otherSem->id,
    ]);
    $otherPengampu = TeacherAssignment::create([
        'teacher_id' => $this->guru->id,
        'class_id' => $this->classA->id,
        'subject_id' => $this->subjectMath->id,
        'period_id' => $otherPeriod->id,
    ]);

    $this->actingAs($this->admin);
    $this->from(route('admin.schedule-sets.editor', $this->set))
        ->post(route('admin.schedule-sets.cells.assign', $this->set), [
            'pengampu_id' => $otherPengampu->id,
            'day' => 1,
            'jam_no' => 1,
            'action' => ScheduleMatrixService::ACTION_ASSIGN,
        ])->assertSessionHasErrors(['pengampu_id']);
});

test('save time slots syncs slot config and cascades delete on removed jam', function () {
    /** @var ScheduleMatrixService $svc */
    $svc = app(ScheduleMatrixService::class);
    $svc->assignCell($this->set, $this->pengampuA, 1, 2, ScheduleMatrixService::ACTION_ASSIGN);

    $this->actingAs($this->admin);
    $this->put(route('admin.schedule-sets.time-slots', $this->set), [
        'jam_count' => 1,
        'day_count' => 6,
        'slots' => [
            ['jam_no' => 1, 'time_start' => '07:00', 'time_end' => '07:45'],
        ],
    ])->assertRedirect();

    $this->assertDatabaseMissing('schedules', [
        'schedule_set_id' => $this->set->id,
        'jam_no' => 2,
    ]);
    $this->assertDatabaseMissing('schedule_time_slots', [
        'schedule_set_id' => $this->set->id,
        'jam_no' => 2,
    ]);
});

test('legacy schedule controller attaches to active set automatically', function () {
    $this->actingAs($this->admin);
    $this->from(route('admin.schedules.index', ['period_id' => $this->period->id]))
        ->post(route('admin.schedules.store'), [
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->guru->id,
            'period_id' => $this->period->id,
            'day' => 3,
            'time_start' => '07:00',
            'time_end' => '07:45',
        ])->assertRedirect();

    $row = AcademicSchedule::where('class_id', $this->classA->id)
        ->where('day', 3)
        ->first();
    expect($row)->not->toBeNull();
    expect($row->schedule_set_id)->toBe($this->set->id);
    expect($row->jam_no)->toBe(1);
});
