<?php

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\AccountGeneratorController;
use App\Http\Controllers\Admin\AdminStudentPositionController;
use App\Http\Controllers\Admin\AsramaController;
use App\Http\Controllers\Admin\AssessmentComponentController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ClassPromotionController;
use App\Http\Controllers\Admin\DiniyahClassController;
use App\Http\Controllers\Admin\GuardianController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\KitabGradeController;
use App\Http\Controllers\Admin\KitabReadingAssessmentController;
use App\Http\Controllers\Admin\KitabReadingExaminerAssignmentController;
use App\Http\Controllers\Admin\KitabSubjectController;
use App\Http\Controllers\Admin\KitabTeachingAssignmentController;
use App\Http\Controllers\Admin\LeavePermissionController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentReportController;
use App\Http\Controllers\Admin\PaymentTypeController;
use App\Http\Controllers\Admin\ReportCardAssetController;
use App\Http\Controllers\Admin\ReportCardController;
use App\Http\Controllers\Admin\ReportCardTemplateController;
use App\Http\Controllers\Admin\RoleCertificateController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ScheduleMatrixController;
use App\Http\Controllers\Admin\ScheduleSetController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentDataTransferController;
use App\Http\Controllers\Admin\StudentDiscountController;
use App\Http\Controllers\Admin\StudentEnrollmentController;
use App\Http\Controllers\Admin\StudentEnrollmentTransferController;
use App\Http\Controllers\Admin\SubjectSettingController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\TeacherDataTransferController;
use App\Http\Controllers\Admin\TeacherManagementController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ViolationController;
use App\Http\Controllers\Auth\ForceChangePasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Guru\GuruAcademicController;
use App\Http\Controllers\Guru\GuruAttendanceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\QueueRunController;
use App\Http\Controllers\ReportCardVerificationController;
use App\Http\Controllers\Santri\SantriController;
use App\Http\Controllers\Wali\WaliPaymentController;
use App\Http\Controllers\Wali\WaliProfileController;
use App\Http\Controllers\Wali\WaliSantriController;
use App\Http\Controllers\WaliKelas\WaliKelasClassPromotionRecapController;
use App\Http\Controllers\WaliKelas\WaliKelasGradeReviewController;
use App\Http\Controllers\WaliKelas\WaliKelasReportController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Public raport verification (no auth)
Route::get('raport/verify/{token}', [ReportCardVerificationController::class, 'show'])->name('raport.verify');
Route::post('payment/midtrans/notification', [PaymentGatewayController::class, 'handleNotification'])->name('payment.midtrans.notification');

Route::middleware('auth')->group(function () {
    Route::get('force-change-password', [ForceChangePasswordController::class, 'show'])
        ->name('password.force-change');
    Route::post('force-change-password', [ForceChangePasswordController::class, 'update'])
        ->name('password.force-change.update');
});

Route::middleware(['auth', 'verified', 'password.changed'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('queue-runs', [QueueRunController::class, 'index'])->name('queue-runs.index');
    Route::post('queue-runs/{importRun}/retry', [QueueRunController::class, 'retry'])->name('queue-runs.retry');
});

// Admin routes (super_admin + admin_akademik)
Route::middleware(['auth', 'verified', 'password.changed', 'role:'.implode(',', [
    Role::SUPER_ADMIN,
    Role::ADMIN_AKADEMIK,
])])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Students & Guardians
        Route::resource('students', StudentController::class);
        Route::get('student-positions', [AdminStudentPositionController::class, 'index'])->name('student-positions.index');
        Route::post('student-positions', [AdminStudentPositionController::class, 'store'])->name('student-positions.store');
        Route::put('student-positions/{studentPosition}', [AdminStudentPositionController::class, 'update'])->name('student-positions.update');
        Route::delete('student-positions/{studentPosition}', [AdminStudentPositionController::class, 'destroy'])->name('student-positions.destroy');
        Route::patch('student-positions/{studentPosition}/activate', [AdminStudentPositionController::class, 'activate'])->name('student-positions.activate');
        Route::patch('student-positions/{studentPosition}/deactivate', [AdminStudentPositionController::class, 'deactivate'])->name('student-positions.deactivate');
        Route::put('student-positions/division-access/{user}', [AdminStudentPositionController::class, 'updateDivisionAccess'])->name('student-positions.division-access.update');
        Route::get('student-enrollments', [StudentEnrollmentController::class, 'index'])->name('student-enrollments.index');
        Route::post('student-enrollments/preview', [StudentEnrollmentController::class, 'preview'])->name('student-enrollments.preview');
        Route::post('student-enrollments/bulk-assign', [StudentEnrollmentController::class, 'bulkAssign'])->name('student-enrollments.bulk-assign');
        Route::post('student-enrollments/bulk-move', [StudentEnrollmentController::class, 'bulkMove'])->name('student-enrollments.bulk-move');
        Route::post('student-enrollments/bulk-clear', [StudentEnrollmentController::class, 'bulkClear'])->name('student-enrollments.bulk-clear');
        Route::get('student-enrollments-export', [StudentEnrollmentTransferController::class, 'export'])->name('student-enrollments.export');
        Route::get('student-enrollments-template', [StudentEnrollmentTransferController::class, 'template'])->name('student-enrollments.template');
        Route::post('student-enrollments-import', [StudentEnrollmentTransferController::class, 'import'])->name('student-enrollments.import');
        Route::get('student-enrollments-import-errors/{token}', [StudentEnrollmentTransferController::class, 'downloadErrors'])->name('student-enrollments.import-errors');
        Route::post('student-enrollments-import-runs/{importRun}/retry', [StudentEnrollmentTransferController::class, 'retry'])->name('student-enrollments.import-runs.retry');
        Route::get('students-export', [StudentDataTransferController::class, 'export'])->name('students.export');
        Route::get('students-template', [StudentDataTransferController::class, 'template'])->name('students.template');
        Route::post('students-import', [StudentDataTransferController::class, 'import'])->name('students.import');
        Route::get('students-import-errors/{token}', [StudentDataTransferController::class, 'downloadErrors'])->name('students.import-errors');
        Route::post('students-import-runs/{importRun}/retry', [StudentDataTransferController::class, 'retry'])->name('students.import-runs.retry');
        Route::get('students/{student}/guardians/attach', [GuardianController::class, 'attach'])->name('students.guardians.attach');
        Route::post('students/{student}/guardians/attach', [GuardianController::class, 'storeAttach'])->name('students.guardians.attach.store');
        Route::resource('students.guardians', GuardianController::class)->except(['index', 'show']);

        // Account Generator
        Route::get('account-generator', [AccountGeneratorController::class, 'index'])->name('account-generator.index');
        Route::post('account-generator/wali-preview', [AccountGeneratorController::class, 'previewWaliImpact'])->name('account-generator.wali-preview');
        Route::post('account-generator/students', [AccountGeneratorController::class, 'generateStudentAccounts'])->name('account-generator.students');
        Route::post('account-generator/guardians', [AccountGeneratorController::class, 'generateGuardianAccounts'])->name('account-generator.guardians');
        Route::post('account-generator/runs/{importRun}/retry', [AccountGeneratorController::class, 'retryBulkRun'])->name('account-generator.runs.retry');

        // Academic Years & Semesters
        Route::resource('academic-years', AcademicYearController::class)->except(['create', 'show', 'edit']);
        Route::post('semesters', [AcademicYearController::class, 'storeSemester'])->name('semesters.store');
        Route::put('semesters/{semester}', [AcademicYearController::class, 'updateSemester'])->name('semesters.update');
        Route::delete('semesters/{semester}', [AcademicYearController::class, 'destroySemester'])->name('semesters.destroy');

        // Diniyah Classes
        Route::resource('diniyah-classes', DiniyahClassController::class)->except(['create', 'show', 'edit']);
        Route::get('diniyah-classes-export', [DiniyahClassController::class, 'export'])->name('diniyah-classes.export');
        Route::get('diniyah-classes-template', [DiniyahClassController::class, 'template'])->name('diniyah-classes.template');
        Route::post('diniyah-classes-import', [DiniyahClassController::class, 'import'])->name('diniyah-classes.import');

        // Kitab Subjects
        Route::resource('kitab-subjects', KitabSubjectController::class)->except(['create', 'show', 'edit']);
        Route::get('kitab-subjects-export', [KitabSubjectController::class, 'export'])->name('kitab-subjects.export');
        Route::get('kitab-subjects-template', [KitabSubjectController::class, 'template'])->name('kitab-subjects.template');
        Route::post('kitab-subjects-import', [KitabSubjectController::class, 'import'])->name('kitab-subjects.import');

        // Komponen penilaian diniyyah (harian / ujian)
        Route::resource('assessment-components', AssessmentComponentController::class)->except(['create', 'show', 'edit']);
        Route::get('subject-level-mappings', [SubjectSettingController::class, 'mappingIndex'])->name('subject-level-mappings.index');
        Route::post('subject-level-mappings/sync', [SubjectSettingController::class, 'syncMappings'])->name('subject-level-mappings.sync');
        Route::get('subject-settings', [SubjectSettingController::class, 'index'])->name('subject-settings.index');
        Route::post('subject-settings/assign-level', [SubjectSettingController::class, 'assignSubjectToLevel'])->name('subject-settings.assign-level');
        Route::delete('subject-settings/assign-level', [SubjectSettingController::class, 'removeSubjectFromLevel'])->name('subject-settings.remove-level');
        Route::post('subject-settings/level', [SubjectSettingController::class, 'upsertLevel'])->name('subject-settings.level.store');
        Route::post('subject-settings/class-override', [SubjectSettingController::class, 'upsertClassOverride'])->name('subject-settings.class-override.store');
        Route::delete('subject-settings/class-override/{subjectClassOverride}', [SubjectSettingController::class, 'destroyClassOverride'])->name('subject-settings.class-override.destroy');

        // Kitab Teaching Assignments (guru -> kelas -> mata pelajaran)
        Route::get('teaching-assignments', [KitabTeachingAssignmentController::class, 'index'])->name('teaching-assignments.index');
        Route::post('teaching-assignments', [KitabTeachingAssignmentController::class, 'store'])->name('teaching-assignments.store');
        Route::delete('teaching-assignments/{teachingAssignment}', [KitabTeachingAssignmentController::class, 'destroy'])->name('teaching-assignments.destroy');
        Route::get('teachers', [TeacherManagementController::class, 'index'])->name('teachers.index');
        Route::get('teachers/eligible-users', [TeacherManagementController::class, 'eligibleUsers'])->name('teachers.eligible-users');
        Route::post('teachers', [TeacherManagementController::class, 'store'])->name('teachers.store');
        Route::put('teachers/{teacher}', [TeacherManagementController::class, 'update'])->name('teachers.update');
        Route::post('teachers/{teacher}/toggle-active', [TeacherManagementController::class, 'toggleActive'])->name('teachers.toggle-active');
        Route::get('kitab-reading-examiners', [KitabReadingExaminerAssignmentController::class, 'index'])->name('kitab-reading-examiners.index');
        Route::post('kitab-reading-examiners', [KitabReadingExaminerAssignmentController::class, 'store'])->name('kitab-reading-examiners.store');
        Route::delete('kitab-reading-examiners/{kitabReadingExaminer}', [KitabReadingExaminerAssignmentController::class, 'destroy'])->name('kitab-reading-examiners.destroy');
        Route::get('role-certificates', [RoleCertificateController::class, 'index'])->name('role-certificates.index');
        Route::post('role-certificates', [RoleCertificateController::class, 'store'])->name('role-certificates.store');
        Route::get('role-certificates/{roleCertificate}/download', [RoleCertificateController::class, 'download'])->name('role-certificates.download');

        // Jadwal kitab (periode, kelas, mapel, guru) - legacy list view
        Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::put('schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');

        // Matrix Schedule Editor (versi jadwal bernama + matrix per set)
        Route::get('schedule-sets', [ScheduleSetController::class, 'index'])->name('schedule-sets.index');
        Route::post('schedule-sets', [ScheduleSetController::class, 'store'])->name('schedule-sets.store');
        Route::put('schedule-sets/{scheduleSet}', [ScheduleSetController::class, 'update'])->name('schedule-sets.update');
        Route::patch('schedule-sets/{scheduleSet}/activate', [ScheduleSetController::class, 'activate'])->name('schedule-sets.activate');
        Route::delete('schedule-sets/{scheduleSet}', [ScheduleSetController::class, 'destroy'])->name('schedule-sets.destroy');
        Route::put('schedule-sets/{scheduleSet}/time-slots', [ScheduleSetController::class, 'saveTimeSlots'])->name('schedule-sets.time-slots');
        Route::get('schedule-sets/{scheduleSet}/editor', [ScheduleMatrixController::class, 'edit'])->name('schedule-sets.editor');
        Route::post('schedule-sets/{scheduleSet}/cells/preflight', [ScheduleMatrixController::class, 'preflight'])->name('schedule-sets.cells.preflight');
        Route::post('schedule-sets/{scheduleSet}/cells', [ScheduleMatrixController::class, 'assign'])->name('schedule-sets.cells.assign');
        Route::delete('schedule-sets/{scheduleSet}/cells/{schedule}', [ScheduleMatrixController::class, 'destroyCell'])->name('schedule-sets.cells.destroy');

        // Asrama
        Route::get('asrama', [AsramaController::class, 'index'])->name('asrama.index');
        Route::post('asrama/buildings', [AsramaController::class, 'storeBuilding'])->name('asrama.buildings.store');
        Route::delete('asrama/buildings/{building}', [AsramaController::class, 'destroyBuilding'])->name('asrama.buildings.destroy');
        Route::post('asrama/rooms', [AsramaController::class, 'storeRoom'])->name('asrama.rooms.store');
        Route::delete('asrama/rooms/{room}', [AsramaController::class, 'destroyRoom'])->name('asrama.rooms.destroy');
        Route::get('asrama/assign', [AsramaController::class, 'assign'])->name('asrama.assign');
        Route::post('asrama/assignments', [AsramaController::class, 'storeAssignment'])->name('asrama.assignments.store');
        Route::post('asrama/assignments/{assignment}/checkout', [AsramaController::class, 'checkout'])->name('asrama.assignments.checkout');

        // Violations
        Route::get('violations', [ViolationController::class, 'index'])->name('violations.index');
        Route::get('violations/create', [ViolationController::class, 'create'])->name('violations.create');
        Route::post('violations', [ViolationController::class, 'store'])->name('violations.store');
        Route::post('violations/{violation}/resolve', [ViolationController::class, 'resolve'])->name('violations.resolve');
        Route::get('violations/types', [ViolationController::class, 'types'])->name('violations.types');
        Route::post('violations/types', [ViolationController::class, 'storeType'])->name('violations.types.store');
        Route::delete('violations/types/{violationType}', [ViolationController::class, 'destroyType'])->name('violations.types.destroy');

        // Leave Permissions
        Route::get('leave-permissions', [LeavePermissionController::class, 'index'])->name('leave-permissions.index');
        Route::get('leave-permissions/create', [LeavePermissionController::class, 'create'])->name('leave-permissions.create');
        Route::post('leave-permissions', [LeavePermissionController::class, 'store'])->name('leave-permissions.store');
        Route::post('leave-permissions/{leavePermission}/approve', [LeavePermissionController::class, 'approve'])->name('leave-permissions.approve');
        Route::post('leave-permissions/{leavePermission}/reject', [LeavePermissionController::class, 'reject'])->name('leave-permissions.reject');
        Route::post('leave-permissions/{leavePermission}/returned', [LeavePermissionController::class, 'markReturned'])->name('leave-permissions.returned');

        // Lesson Attendance
        Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::put('attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');

        // Report Cards
        Route::get('report-cards', [ReportCardController::class, 'index'])->name('report-cards.index');
        Route::get('report-cards/preview', [ReportCardController::class, 'preview'])->name('report-cards.preview');
        Route::post('report-cards/save-notes', [ReportCardController::class, 'saveNotes'])->name('report-cards.save-notes');
        Route::get('report-cards/pdf', [ReportCardController::class, 'downloadPdf'])->name('report-cards.pdf');

        // Report Card Assets (upload images for template)
        Route::post('report-card-assets/upload', [ReportCardAssetController::class, 'upload'])->name('report-card-assets.upload');
        Route::get('report-card-assets/list', [ReportCardAssetController::class, 'list'])->name('report-card-assets.list');

        // Report Card Templates
        Route::get('report-card-templates', [ReportCardTemplateController::class, 'index'])->name('report-card-templates.index');
        Route::get('report-card-templates/create', [ReportCardTemplateController::class, 'create'])->name('report-card-templates.create');
        Route::post('report-card-templates', [ReportCardTemplateController::class, 'store'])->name('report-card-templates.store');
        Route::get('report-card-templates/{reportCardTemplate}/edit', [ReportCardTemplateController::class, 'edit'])->name('report-card-templates.edit');
        Route::get('report-card-templates/{reportCardTemplate}/design', [ReportCardTemplateController::class, 'design'])->name('report-card-templates.design');
        Route::put('report-card-templates/{reportCardTemplate}', [ReportCardTemplateController::class, 'update'])->name('report-card-templates.update');
        Route::post('report-card-templates/{reportCardTemplate}/set-default', [ReportCardTemplateController::class, 'setDefault'])->name('report-card-templates.set-default');

        // Class Promotion
        Route::get('class-promotion', [ClassPromotionController::class, 'index'])->name('class-promotion.index');
        Route::post('class-promotion/{classPromotionRecap}/approve', [ClassPromotionController::class, 'approve'])->name('class-promotion.approve');
        Route::post('class-promotion/{classPromotionRecap}/reject', [ClassPromotionController::class, 'reject'])->name('class-promotion.reject');

        // User Management (super_admin only)
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::get('teachers-export', [TeacherDataTransferController::class, 'export'])->name('teachers.export');
        Route::get('teachers-template', [TeacherDataTransferController::class, 'template'])->name('teachers.template');
        Route::post('teachers-import', [TeacherDataTransferController::class, 'import'])->name('teachers.import');
        Route::get('teachers-import-errors/{token}', [TeacherDataTransferController::class, 'downloadErrors'])->name('teachers.import-errors');
        Route::post('teachers-import-runs/{importRun}/retry', [TeacherDataTransferController::class, 'retry'])->name('teachers.import-runs.retry');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::get('system-logs', [SystemLogController::class, 'index'])->name('system-logs.index');
    });

// Audit log: super_admin (semua modul), admin_keuangan (keuangan), admin_akademik (akademik & operasional)
Route::middleware(['auth', 'verified', 'password.changed', 'permission:audit_log.view_finance,audit_log.view_akademik'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

// Keuangan routes (super_admin + admin_keuangan)
Route::middleware(['auth', 'verified', 'password.changed', 'role:'.implode(',', [
    Role::SUPER_ADMIN,
    Role::ADMIN_KEUANGAN,
]), 'permission:invoice.view,payment.view,payment.report.view'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('payment-types', PaymentTypeController::class)->except(['create', 'show', 'edit']);

        Route::get('student-discounts', [StudentDiscountController::class, 'index'])->name('student-discounts.index');
        Route::post('student-discounts', [StudentDiscountController::class, 'store'])->name('student-discounts.store');
        Route::delete('student-discounts/{studentDiscount}', [StudentDiscountController::class, 'destroy'])->name('student-discounts.destroy');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
        Route::post('invoices/bulk-generate-preview', [InvoiceController::class, 'previewBulkGenerate'])->name('invoices.bulk-generate-preview');
        Route::post('invoices/bulk-generate', [InvoiceController::class, 'bulkGenerate'])->name('invoices.bulk-generate');
        Route::post('invoices/bulk-runs/{importRun}/retry', [InvoiceController::class, 'retryBulkRun'])->name('invoices.bulk-runs.retry');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
        Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');

        Route::get('payment-reports', [PaymentReportController::class, 'summary'])->name('payment-reports.summary');
        Route::get('payment-reports/arrears', [PaymentReportController::class, 'arrears'])->name('payment-reports.arrears');
        Route::get('payment-reports/export', [PaymentReportController::class, 'export'])->name('payment-reports.export');
    });

// Kitab Grades (admin atau user dengan KitabTeachingAssignment)
Route::middleware(['auth', 'verified', 'password.changed', 'can.access.kitab-grades'])
    ->prefix('admin')
    ->name('guru.')
    ->group(function () {
        Route::get('schedule', [GuruAcademicController::class, 'schedule'])->name('schedule');
        Route::get('attendance-sessions', [GuruAttendanceController::class, 'index'])->name('attendance-sessions.index');
        Route::get('attendance-sessions/{session}', [GuruAttendanceController::class, 'show'])->name('attendance-sessions.show');
        Route::post('attendance-sessions/{session}', [GuruAttendanceController::class, 'store'])->name('attendance-sessions.store');
        Route::get('kitab-grades/{academic_period}/{kitab_subject}/{diniyah_class}/setting', [KitabGradeController::class, 'setting'])
            ->whereNumber(['academic_period', 'kitab_subject', 'diniyah_class'])
            ->name('admin.kitab-grades.setting');
        Route::post('kitab-grades/{academic_period}/{kitab_subject}/{diniyah_class}/setting', [KitabGradeController::class, 'saveSetting'])
            ->whereNumber(['academic_period', 'kitab_subject', 'diniyah_class'])
            ->name('admin.kitab-grades.setting.store');
        Route::get('kitab-grades/{academic_period}/{kitab_subject}/{diniyah_class}', [KitabGradeController::class, 'input'])
            ->whereNumber(['academic_period', 'kitab_subject', 'diniyah_class'])
            ->name('admin.kitab-grades.input');
        Route::post('kitab-grades/{academic_period}/{kitab_subject}/{diniyah_class}', [KitabGradeController::class, 'store'])
            ->whereNumber(['academic_period', 'kitab_subject', 'diniyah_class'])
            ->name('admin.kitab-grades.store');
        Route::get('kitab-grades/{academic_period}/{kitab_subject}', [KitabGradeController::class, 'pickClass'])
            ->whereNumber(['academic_period', 'kitab_subject'])
            ->name('admin.kitab-grades.class');
        Route::get('kitab-grades/{academic_period}', [KitabGradeController::class, 'pickSubject'])
            ->whereNumber('academic_period')
            ->name('admin.kitab-grades.subject');
        Route::get('kitab-grades', [KitabGradeController::class, 'entry'])->name('admin.kitab-grades.index');
        Route::get('kitab-reading-assessments', [KitabReadingAssessmentController::class, 'index'])->name('kitab-reading-assessments.index');
        Route::post('kitab-reading-assessments', [KitabReadingAssessmentController::class, 'store'])->name('kitab-reading-assessments.store');
    });

// Santri portal (read-only)
Route::middleware(['auth', 'verified', 'password.changed', 'role:'.Role::SANTRI])
    ->prefix('santri')
    ->name('santri.')
    ->group(function () {
        Route::get('grades', [SantriController::class, 'grades'])->name('grades');
        Route::get('schedule', [SantriController::class, 'schedule'])->name('schedule');
        Route::get('attendances', [SantriController::class, 'attendances'])->name('attendances');
        Route::get('violations', [SantriController::class, 'violations'])->name('violations');
        Route::get('profile', [SantriController::class, 'profile'])->name('profile');
        Route::get('profile/edit', [SantriController::class, 'editProfile'])->name('profile.edit');
        Route::put('profile', [SantriController::class, 'updateProfile'])->name('profile.update');
        Route::put('profile/guardians/{guardian}', [SantriController::class, 'updateGuardian'])->name('profile.guardians.update');
    });

// Wali Kelas portal (hanya user yang punya wali_kelas_id di diniyah_classes)
Route::middleware(['auth', 'verified', 'password.changed', 'has.wali-kelas-record'])
    ->prefix('wali-kelas')
    ->name('wali-kelas.')
    ->group(function () {
        Route::get('grade-reviews', [WaliKelasGradeReviewController::class, 'index'])->name('grade-reviews.index');
        Route::post('grade-reviews/review', [WaliKelasGradeReviewController::class, 'review'])->name('grade-reviews.review');
        Route::get('class-promotion-recaps', [WaliKelasClassPromotionRecapController::class, 'index'])->name('class-promotion-recaps.index');
        Route::post('class-promotion-recaps', [WaliKelasClassPromotionRecapController::class, 'submit'])->name('class-promotion-recaps.submit');
        Route::get('report-cards', [WaliKelasReportController::class, 'index'])->name('report-cards.index');
        Route::get('report-cards/preview', [WaliKelasReportController::class, 'preview'])->name('report-cards.preview');
        Route::post('report-cards/save-notes', [WaliKelasReportController::class, 'saveNotes'])->name('report-cards.save-notes');
        Route::get('report-cards/pdf', [WaliKelasReportController::class, 'downloadPdf'])->name('report-cards.pdf');
    });

// Wali Santri — lengkapi profil (tanpa middleware profile.complete)
Route::middleware(['auth', 'verified', 'password.changed', 'role:'.Role::WALI_SANTRI])
    ->prefix('wali')
    ->name('wali.')
    ->group(function () {
        Route::get('profile/complete', [WaliProfileController::class, 'show'])->name('profile.complete');
        Route::put('profile/complete', [WaliProfileController::class, 'update'])->name('profile.complete.update');
    });

// Wali Santri portal
Route::middleware(['auth', 'verified', 'password.changed', 'role:'.Role::WALI_SANTRI, 'profile.complete'])
    ->prefix('wali')
    ->name('wali.')
    ->group(function () {
        Route::get('children', [WaliSantriController::class, 'children'])->name('children');
        Route::get('children/{student}', [WaliSantriController::class, 'childDetail'])->name('children.show');
        Route::get('children/{student}/schedule', [WaliSantriController::class, 'childSchedule'])->name('children.schedule');

        Route::get('invoices', [WaliPaymentController::class, 'invoices'])->name('invoices');
        Route::get('invoices/{invoice}', [WaliPaymentController::class, 'invoiceDetail'])->name('invoices.show');
        Route::post('invoices/{invoice}/upload-proof', [WaliPaymentController::class, 'uploadProof'])->name('invoices.upload-proof');
        Route::get('payment-history', [WaliPaymentController::class, 'paymentHistory'])->name('payment-history');
        Route::post('invoices/{invoice}/pay', [WaliPaymentController::class, 'createPayment'])->name('invoices.pay');
        Route::post('payment/create-charge', [PaymentGatewayController::class, 'createCharge'])->name('payment.create-charge');
    });

require __DIR__.'/settings.php';
