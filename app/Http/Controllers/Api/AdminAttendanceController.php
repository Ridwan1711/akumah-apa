<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLessonAttendanceRequest;
use App\Models\LessonAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LessonAttendance::query()
            ->with([
                'lessonSession.schedule.schoolClass:id,name,level',
                'lessonSession.schedule.subject:id,name',
                'student:id,nis,full_name',
            ])
            ->orderByDesc('lesson_session_id')
            ->orderBy('student_id');

        if ($request->filled('class_id')) {
            $classId = (int) $request->class_id;
            $query->whereHas('lessonSession.schedule', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', (int) $request->student_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereHas('lessonSession', function ($q) use ($request) {
                $q->where('date', '>=', $request->date_from);
            });
        }

        if ($request->filled('date_to')) {
            $query->whereHas('lessonSession', function ($q) use ($request) {
                $q->where('date', '<=', $request->date_to);
            });
        }

        $attendances = $query->paginate(50);

        return response()->json($attendances);
    }

    public function update(UpdateLessonAttendanceRequest $request, LessonAttendance $attendance): JsonResponse
    {
        $attendance->update($request->validated() + [
            'marked_at' => now(),
        ]);

        return response()->json([
            'message' => 'Kehadiran berhasil diperbarui.',
            'attendance' => $attendance->fresh(),
        ]);
    }
}
