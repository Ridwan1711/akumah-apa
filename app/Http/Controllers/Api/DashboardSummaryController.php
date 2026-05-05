<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\Guardian;
use App\Models\LeavePermission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\Authorization\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSummaryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles');

        $cards = [];

        if ($this->canAccessAdmin($user)) {
            $cards[] = [
                'id' => 'admin',
                'title' => 'Admin Dashboard',
                'route' => '/admin/dashboard',
                'data_available' => true,
                'summary' => [
                    'total_students' => Student::query()
                        ->where('status', Student::STATUS_ACTIVE)
                        ->count(),
                    'total_classes' => SchoolClass::query()->count(),
                ],
            ];
        }

        if ($this->canAccessGuru($user)) {
            $assignmentCount = TeacherAssignment::query()
                ->where('teacher_id', $user->id)
                ->count();
            $pendingLeaveCount = LeavePermission::query()
                ->where('status', LeavePermission::STATUS_PENDING)
                ->count();

            $cards[] = [
                'id' => 'guru',
                'title' => 'Guru Dashboard',
                'route' => '/guru/dashboard',
                'data_available' => true,
                'summary' => [
                    'assignments_count' => $assignmentCount,
                    'pending_leaves_count' => $pendingLeaveCount,
                ],
            ];
        }

        if ($this->canAccessWali($user)) {
            $guardian = $user->primaryGuardian();
            $childrenCount = $guardian
                ? $guardian->students()->where('status', Student::STATUS_ACTIVE)->count()
                : 0;

            $cards[] = [
                'id' => 'wali',
                'title' => 'Wali Dashboard',
                'route' => '/wali/dashboard',
                'data_available' => $guardian !== null,
                'summary' => [
                    'children_count' => $childrenCount,
                    'guardian_id' => $guardian?->id,
                ],
            ];
        }

        if ($this->canAccessSantri($user)) {
            $student = $user->student()->with('currentClass:id,name')->first();

            $cards[] = [
                'id' => 'santri',
                'title' => 'Santri Dashboard',
                'route' => '/santri/dashboard',
                'data_available' => $student !== null,
                'summary' => [
                    'student_id' => $student?->id,
                    'student_name' => $student?->full_name,
                    'class_name' => $student?->currentClass?->name,
                ],
            ];
        }

        return response()->json([
            'cards' => $cards,
        ]);
    }

    private function canAccessAdmin(User $user): bool
    {
        return $user->hasRole(Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK)
            || $user->hasPermission(Permissions::DASHBOARD_ADMIN);
    }

    private function canAccessGuru(User $user): bool
    {
        return $user->hasRole(Role::GURU)
            || $user->hasPermission(Permissions::DASHBOARD_GURU);
    }

    private function canAccessWali(User $user): bool
    {
        return $user->hasRole(Role::WALI_SANTRI)
            || $user->hasPermission(Permissions::DASHBOARD_WALI);
    }

    private function canAccessSantri(User $user): bool
    {
        return $user->hasRole(Role::SANTRI)
            || $user->hasPermission(Permissions::DASHBOARD_SANTRI);
    }
}
