<?php

use App\Http\Controllers\Api\AdminAttendanceController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminKeuanganController;
use App\Http\Controllers\Api\AdminScheduleController;
use App\Http\Controllers\Api\AdminUserManagementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GuruAttendanceController;
use App\Http\Controllers\Api\GuruController;
use App\Http\Controllers\Api\SantriController;
use App\Http\Controllers\Api\WaliController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::patch('/user/profile', [AuthController::class, 'updateProfile']);
        Route::put('/user/password', [AuthController::class, 'updatePassword']);
        Route::post('/force-change-password', [AuthController::class, 'forceChangePassword']);

        Route::post('/user/fcm-token', [AuthController::class, 'registerFcmToken']);
        Route::delete('/user/fcm-token', [AuthController::class, 'unregisterFcmToken']);

        Route::get('/user/sessions', [AuthController::class, 'sessions']);
        Route::delete('/user/sessions/{id}', [AuthController::class, 'revokeSession'])
            ->whereNumber('id')
            ->middleware('throttle:30,1');
        Route::post('/user/sessions/revoke-others', [AuthController::class, 'revokeOtherSessions'])
            ->middleware('throttle:10,1');

        // Notifications (all roles)
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // Santri
        Route::middleware('role:santri')->prefix('santri')->group(function () {
            Route::get('/dashboard', [SantriController::class, 'dashboard']);
            Route::get('/grades', [SantriController::class, 'grades']);
            Route::get('/tahfidz', [SantriController::class, 'tahfidz']);
            Route::get('/violations', [SantriController::class, 'violations']);
            Route::get('/profile', [SantriController::class, 'profile']);
            Route::put('/profile', [SantriController::class, 'updateProfile']);
            Route::get('/leaves', [SantriController::class, 'leaves']);
            Route::post('/leaves', [SantriController::class, 'storeLeave']);
            Route::get('/schedule', [SantriController::class, 'schedule']);
            Route::get('/invoices', [SantriController::class, 'invoices']);
            Route::get('/invoices/{invoice}', [SantriController::class, 'invoiceDetail']);
            Route::get('/attendances', [SantriController::class, 'attendances']);
        });

        // Wali Santri
        Route::middleware('role:wali_santri')->prefix('wali')->group(function () {
            Route::get('/dashboard', [WaliController::class, 'dashboard']);
            Route::get('/children', [WaliController::class, 'children']);
            Route::get('/children/{student}', [WaliController::class, 'childDetail']);
            Route::put('/children/{student}', [WaliController::class, 'updateChildProfile']);
            Route::get('/children/{student}/schedule', [WaliController::class, 'childSchedule']);
            Route::get('/invoices', [WaliController::class, 'invoices']);
            Route::get('/invoices/{invoice}', [WaliController::class, 'invoiceDetail']);
            Route::post('/invoices/{invoice}/create-charge', [WaliController::class, 'createCharge']);
            Route::post('/invoices/{invoice}/upload-proof', [WaliController::class, 'uploadProof']);
            Route::get('/payment-history', [WaliController::class, 'paymentHistory']);
        });

        // Guru (and admin for kitab grades / report cards)
        Route::middleware('role:guru,super_admin,admin_akademik')->prefix('guru')->group(function () {
            Route::get('/dashboard', [GuruController::class, 'dashboard']);
            Route::get('/teaching-assignments', [GuruController::class, 'teachingAssignments']);
            Route::get('/schedule', [GuruController::class, 'schedule']);
            Route::get('/sessions', [GuruAttendanceController::class, 'index']);
            Route::get('/sessions/{session}/students', [GuruAttendanceController::class, 'students']);
            Route::post('/sessions/{session}/attendance', [GuruAttendanceController::class, 'storeAttendance']);
            Route::get('/sessions/{session}/attendance', [GuruAttendanceController::class, 'showAttendance']);
            Route::get('/kitab-grades', [GuruController::class, 'kitabGradesIndex']);
            Route::post('/kitab-grades', [GuruController::class, 'kitabGradesStore']);
            Route::get('/report-cards', [GuruController::class, 'reportCardsIndex']);
            Route::get('/report-cards/preview', [GuruController::class, 'reportCardsPreview']);
            Route::post('/report-cards/save-notes', [GuruController::class, 'reportCardsSaveNotes']);
            Route::get('/report-cards/pdf', [GuruController::class, 'reportCardsPdf']);
        });

        // Admin (super_admin, admin_akademik)
        Route::middleware('role:super_admin,admin_akademik')->prefix('admin')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/classes', [AdminController::class, 'classes']);
            Route::put('/classes/{class}', [AdminController::class, 'updateClassSettings']);
            Route::get('/classes/{class}/teachers', [AdminController::class, 'classTeachers']);
            Route::get('/classes/{class}/homeroom-candidates', [AdminController::class, 'classHomeroomCandidates']);
            Route::put('/classes/{class}/homeroom-teacher', [AdminController::class, 'setClassHomeroomTeacher']);
            Route::get('/dorm-buildings', [AdminController::class, 'dormBuildings']);
            Route::get('/dorm-management', [AdminController::class, 'dormManagement']);
            Route::post('/dorm-buildings', [AdminController::class, 'storeDormBuilding']);
            Route::put('/dorm-buildings/{building}', [AdminController::class, 'updateDormBuilding']);
            Route::delete('/dorm-buildings/{building}', [AdminController::class, 'destroyDormBuilding']);
            Route::post('/dorm-rooms', [AdminController::class, 'storeDormRoom']);
            Route::get('/dorm-rooms/{room}/members', [AdminController::class, 'dormRoomMembers']);
            Route::get('/dorm-rooms/{room}/assignable-students', [AdminController::class, 'dormRoomAssignableStudents']);
            Route::post('/dorm-rooms/{room}/members', [AdminController::class, 'storeDormRoomMembers']);
            Route::put('/dorm-rooms/{room}/leader', [AdminController::class, 'setDormRoomLeader']);
            Route::put('/dorm-rooms/{room}', [AdminController::class, 'updateDormRoom']);
            Route::delete('/dorm-rooms/{room}', [AdminController::class, 'destroyDormRoom']);
            Route::get('/semesters', [AdminController::class, 'semesters']);
            Route::get('/students', [AdminController::class, 'students']);
            Route::get('/students/form-options', [AdminController::class, 'studentFormOptions']);
            Route::post('/students', [AdminController::class, 'storeStudent']);
            Route::get('/students/{student}', [AdminController::class, 'studentDetail']);
            Route::put('/students/{student}', [AdminController::class, 'updateStudent']);
            Route::get('/kitab-grades', [AdminController::class, 'kitabGrades']);
            Route::get('/leave-permissions', [AdminController::class, 'leavePermissions']);
            Route::post('/leave-permissions', [AdminController::class, 'storeLeavePermission']);
            Route::post('/leave-permissions/{leavePermission}/approve', [AdminController::class, 'approveLeave']);
            Route::post('/leave-permissions/{leavePermission}/reject', [AdminController::class, 'rejectLeave']);
            Route::post('/leave-permissions/{leavePermission}/returned', [AdminController::class, 'markReturned']);
            Route::get('/violations', [AdminController::class, 'violations']);
            Route::post('/violations', [AdminController::class, 'storeViolation']);
            Route::post('/violations/{violation}/resolve', [AdminController::class, 'resolveViolation']);
            Route::get('/violation-types', [AdminController::class, 'violationTypes']);

            // Jadwal kitab
            Route::get('/schedules', [AdminScheduleController::class, 'index']);
            Route::post('/schedules', [AdminScheduleController::class, 'store']);
            Route::put('/schedules/{schedule}', [AdminScheduleController::class, 'update']);
            Route::delete('/schedules/{schedule}', [AdminScheduleController::class, 'destroy']);

            // Kehadiran santri
            Route::get('/attendances', [AdminAttendanceController::class, 'index']);
            Route::put('/attendances/{attendance}', [AdminAttendanceController::class, 'update']);
        });

        // Admin Master Management (super_admin only)
        Route::middleware('role:super_admin')->prefix('admin')->group(function () {
            Route::get('/roles', [AdminUserManagementController::class, 'roles']);
            Route::get('/users', [AdminUserManagementController::class, 'users']);
            Route::post('/users', [AdminUserManagementController::class, 'storeUser']);
            Route::put('/users/{user}', [AdminUserManagementController::class, 'updateUser']);
            Route::post('/users/{user}/toggle-active', [AdminUserManagementController::class, 'toggleActive']);
            Route::post('/users/{user}/reset-password', [AdminUserManagementController::class, 'resetPassword']);
            Route::post('/notifications/announcement', [AdminUserManagementController::class, 'sendAnnouncement']);
            Route::get('/notifications/token-diagnostics', [AdminUserManagementController::class, 'notificationTokenDiagnostics']);
        });

        // Admin Keuangan (super_admin, admin_keuangan)
        Route::middleware(['role:super_admin,admin_keuangan', 'permission:invoice.view,payment.view,payment.report.view'])->prefix('admin')->group(function () {
            Route::get('/invoices', [AdminKeuanganController::class, 'indexInvoices']);
            Route::get('/invoices/generate-meta', [AdminKeuanganController::class, 'generateMeta']);
            Route::post('/invoices/bulk-generate', [AdminKeuanganController::class, 'bulkGenerate']);
            Route::post('/invoices', [AdminKeuanganController::class, 'storeInvoice']);
            Route::get('/invoices/{invoice}', [AdminKeuanganController::class, 'showInvoice']);
            Route::post('/invoices/{invoice}/cancel', [AdminKeuanganController::class, 'cancelInvoice']);
            Route::get('/payments', [AdminKeuanganController::class, 'indexPayments']);
            Route::post('/payments/{payment}/verify', [AdminKeuanganController::class, 'verifyPayment']);
            Route::post('/payments/{payment}/reject', [AdminKeuanganController::class, 'rejectPayment']);
            Route::get('/payment-reports/summary', [AdminKeuanganController::class, 'reportSummary']);
            Route::get('/payment-reports/arrears', [AdminKeuanganController::class, 'reportArrears']);
        });
    });
});
