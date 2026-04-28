<?php

namespace App\Http\Controllers;

use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\LeavePermission;
use App\Models\Musyrif;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentViolation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $user->load('roles');

        if ($user->must_complete_profile && $user->hasRole(Role::WALI_SANTRI)) {
            return redirect()->route('wali.profile.complete');
        }

        $roleNames = $user->roles->pluck('name')->all();
        $primaryRoleName = in_array(Role::SUPER_ADMIN, $roleNames, true)
            ? Role::SUPER_ADMIN
            : (in_array(Role::ADMIN_AKADEMIK, $roleNames, true)
                ? Role::ADMIN_AKADEMIK
                : (in_array(Role::ADMIN_KEUANGAN, $roleNames, true)
                    ? Role::ADMIN_KEUANGAN
                    : ($roleNames[0] ?? null)));
        $data = ['roleName' => $primaryRoleName];

        if ($user->isAdmin()) {
            $data += $this->adminData();
        } elseif ($user->teacherAssignments()->exists()) {
            $data += $this->guruData($user);
        }
        if ($user->homeroomAssignments()->exists()) {
            $data += $this->waliKelasData($user);
        }
        if ($user->hasRole(Role::MUSYRIF)) {
            $data += $this->musyrifData($user);
        } elseif ($user->hasRole(Role::SANTRI)) {
            $data += $this->santriData($user);
        } elseif ($user->hasRole(Role::WALI_SANTRI)) {
            $data += $this->waliData($user);
        }

        return Inertia::render('dashboard', $data);
    }

    private function adminData(): array
    {
        $totalStudents = Student::where('status', Student::STATUS_ACTIVE)->count();
        $totalClasses = SchoolClass::count();
        $totalGuru = User::whereHas('roles', fn ($q) => $q->where('name', Role::GURU))->where('is_active', true)->count();
        $totalMusyrif = User::whereHas('roles', fn ($q) => $q->where('name', Role::MUSYRIF))->where('is_active', true)->count();

        $recentViolations = StudentViolation::with(['student:id,full_name,nis', 'violationType:id,name,category'])
            ->latest('date')->limit(5)->get();

        $pendingLeaves = LeavePermission::where('status', 'pending')
            ->with('student:id,full_name,nis')
            ->latest()->limit(5)->get();

        $classCounts = SchoolClass::withCount(['students' => fn ($q) => $q->where('status', Student::STATUS_ACTIVE)])
            ->orderBy('order')->orderBy('name')
            ->get(['id', 'name', 'grade_level_id']);

        return [
            'stats' => compact('totalStudents', 'totalClasses', 'totalGuru', 'totalMusyrif'),
            'recentViolations' => $recentViolations,
            'pendingLeaves' => $pendingLeaves,
            'classCounts' => $classCounts,
        ];
    }

    private function guruData(User $user): array
    {
        $assignments = TeacherAssignment::where('teacher_id', $user->id)
            ->with(['schoolClass:id,name', 'subject:id,name'])
            ->get();

        return ['assignments' => $assignments];
    }

    private function musyrifData(User $user): array
    {
        $musyrif = Musyrif::where('user_id', $user->id)->first();
        $roomId = $musyrif?->assigned_room_id;

        $recentViolations = StudentViolation::where('handled_by', $user->id)
            ->with(['student:id,full_name', 'violationType:id,name'])
            ->latest('date')->limit(5)->get();

        $pendingLeaves = LeavePermission::where('status', 'pending')
            ->with('student:id,full_name')
            ->latest()->limit(5)->get();

        return [
            'assignedRoomId' => $roomId,
            'recentViolations' => $recentViolations,
            'pendingLeaves' => $pendingLeaves,
        ];
    }

    private function santriData(User $user): array
    {
        $student = $user->student;
        if (! $student) {
            return ['student' => null];
        }

        $student->load([
            'currentClass:id,name',
            'violationSummary',
        ]);

        $recentGrades = $student->scores()
            ->with('subject:id,name')
            ->latest()->limit(5)->get(['id', 'student_id', 'subject_id', 'score', 'created_at']);

        $activeLeave = $student->leavePermissions()
            ->whereIn('status', ['pending', 'approved'])
            ->whereNull('actual_return_date')
            ->latest()->first();

        return [
            'student' => $student,
            'recentGrades' => $recentGrades,
            'activeLeave' => $activeLeave,
        ];
    }

    private function waliKelasData(User $user): array
    {
        $classIds = $user->homeroomAssignments()->pluck('class_id')->unique();
        $classes = SchoolClass::whereIn('id', $classIds)
            ->withCount(['students' => fn ($q) => $q->where('status', Student::STATUS_ACTIVE)])
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'grade_level_id']);

        return ['waliKelasClasses' => $classes];
    }

    private function waliData(User $user): array
    {
        $guardian = $user->primaryGuardian();
        if (! $guardian) {
            return ['children' => []];
        }

        $children = $guardian->students()
            ->with(['currentClass:id,name', 'violationSummary'])
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->get();

        return ['children' => $children];
    }
}
