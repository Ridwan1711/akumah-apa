<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLessonAttendanceRequest;
use App\Models\Diniyyah\SchoolClass;
use App\Models\LessonAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $query = LessonAttendance::with([
            'lessonSession.schedule.schoolClass:id,name,level',
            'lessonSession.schedule.subject:id,name',
            'student:id,nis,full_name',
        ])
            ->when($request->class_id, function ($q, $classId) {
                $q->whereHas('lessonSession.schedule', fn ($sq) => $sq->where('class_id', $classId));
            })
            ->when($request->status && $request->status !== 'all', fn ($q, $status) => $q->where('status', $status))
            ->when($request->date_from, function ($q, $from) {
                $q->whereHas('lessonSession', fn ($sq) => $sq->where('date', '>=', $from));
            })
            ->when($request->date_to, function ($q, $to) {
                $q->whereHas('lessonSession', fn ($sq) => $sq->where('date', '<=', $to));
            })
            ->orderByDesc('lesson_session_id');

        return Inertia::render('admin/attendances/index', [
            'attendances' => $query->paginate(25)->withQueryString(),
            'classes' => SchoolClass::orderBy('level_order')->orderBy('name')->get(['id', 'name', 'level']),
            'filters' => $request->only(['class_id', 'student_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function update(UpdateLessonAttendanceRequest $request, LessonAttendance $attendance): RedirectResponse
    {
        $attendance->update($request->validated() + [
            'marked_at' => now(),
        ]);

        return back()->with('success', 'Kehadiran berhasil diperbarui.');
    }
}
