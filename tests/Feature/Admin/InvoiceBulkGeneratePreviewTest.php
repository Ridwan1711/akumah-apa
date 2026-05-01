<?php

use App\Models\AcademicYear;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $superRole = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'must_complete_profile' => false,
        'is_active' => true,
    ]);
    $this->user->roles()->sync([$superRole->id]);

    $this->academicYear = AcademicYear::query()->create([
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);

    $this->paymentType = PaymentType::query()->create([
        'name' => 'SPP Preview',
        'code' => 'spp-preview',
        'category' => 'spp',
        'is_recurring' => true,
        'default_amount' => 500_000,
        'is_active' => true,
    ]);

    Student::query()->create([
        'nis' => 'PREV-001',
        'full_name' => 'Santri Alpha',
        'gender' => Student::GENDER_MALE,
        'admission_year' => 2026,
        'status' => Student::STATUS_ACTIVE,
        'is_kuliah' => false,
    ]);
    Student::query()->create([
        'nis' => 'PREV-002',
        'full_name' => 'Santri Beta',
        'gender' => Student::GENDER_MALE,
        'admission_year' => 2026,
        'status' => Student::STATUS_ACTIVE,
        'is_kuliah' => true,
    ]);
});

test('bulk generate preview returns target count for all active students', function () {
    $response = $this->actingAs($this->user)->postJson(route('admin.invoices.bulk-generate-preview'), [
        'payment_type_id' => $this->paymentType->id,
        'academic_year_id' => $this->academicYear->id,
        'target_type' => 'all',
        'due_date' => '2026-08-01',
        'month' => 8,
        'send_notification_for_existing' => true,
    ]);

    $response->assertOk();
    $response->assertJsonPath('target_student_count', 2);
    $response->assertJsonPath('kuliah_without_tariff_count', 1);
    $response->assertJsonPath('summary.payment_type_name', 'SPP Preview');
    $response->assertJsonPath('summary.academic_year_name', '2026/2027');
    $response->assertJsonPath('summary.month_label', 'Agustus');
});

test('bulk generate preview returns validation error when due date missing', function () {
    $response = $this->actingAs($this->user)->postJson(route('admin.invoices.bulk-generate-preview'), [
        'payment_type_id' => $this->paymentType->id,
        'academic_year_id' => $this->academicYear->id,
        'target_type' => 'all',
        'send_notification_for_existing' => true,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['due_date']);
});
