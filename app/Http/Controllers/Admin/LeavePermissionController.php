<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeavePermission;
use App\Models\Student;
use App\Notifications\LeaveStatusChangedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeavePermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = LeavePermission::with(['student:id,nis,full_name', 'approver:id,name'])
            ->when($request->status && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q, $s) => $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$s}%")))
            ->orderByDesc('created_at');

        return Inertia::render('admin/leave-permissions/index', [
            'permissions' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['status', 'search']),
            'students' => Student::where('status', Student::STATUS_ACTIVE)
                ->orderBy('full_name')
                ->get(['id', 'nis', 'full_name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/leave-permissions/create', [
            'students' => Student::where('status', Student::STATUS_ACTIVE)->orderBy('full_name')->get(['id', 'nis', 'full_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'reason' => ['required', 'string'],
            'leave_date' => ['required', 'date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:leave_date'],
        ]);

        LeavePermission::create($validated);

        return redirect()->route('admin.leave-permissions.index')->with('success', 'Permohonan izin berhasil diajukan.');
    }

    public function approve(Request $request, LeavePermission $leavePermission): RedirectResponse
    {
        $leavePermission->update([
            'status' => LeavePermission::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
        ]);
        $leavePermission->loadMissing('student.user', 'student.guardians.user');
        $student = $leavePermission->student;
        if ($student?->user) {
            $student->user->notify(new LeaveStatusChangedNotification($leavePermission));
        }
        if ($student) {
            $student->guardians
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->each(fn ($user) => $user->notify(new LeaveStatusChangedNotification($leavePermission)));
        }

        return redirect()->back()->with('success', 'Izin disetujui.');
    }

    public function reject(Request $request, LeavePermission $leavePermission): RedirectResponse
    {
        $request->validate(['rejection_reason' => 'required|string']);

        $leavePermission->update([
            'status' => LeavePermission::STATUS_REJECTED,
            'approved_by' => $request->user()->id,
            'rejection_reason' => $request->rejection_reason,
        ]);
        $leavePermission->loadMissing('student.user', 'student.guardians.user');
        $student = $leavePermission->student;
        if ($student?->user) {
            $student->user->notify(new LeaveStatusChangedNotification($leavePermission));
        }
        if ($student) {
            $student->guardians
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->each(fn ($user) => $user->notify(new LeaveStatusChangedNotification($leavePermission)));
        }

        return redirect()->back()->with('success', 'Izin ditolak.');
    }

    public function markReturned(LeavePermission $leavePermission): RedirectResponse
    {
        $leavePermission->update(['actual_return_date' => now()->toDateString()]);

        return redirect()->back()->with('success', 'Santri sudah kembali.');
    }
}
