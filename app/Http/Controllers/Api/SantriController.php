<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\Score;
use App\Models\EmProfile;
use App\Models\Invoice;
use App\Models\LessonAttendance;
use App\Models\Student;
use App\Models\LessonSession;
use App\Models\LeavePermission;
use App\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SantriController extends Controller
{
    private function getStudent(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student, 404, 'Data santri tidak ditemukan.');

        return $student;
    }

    public function dashboard(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        $student->load([
            'currentClass:id,name',
            'tahfidzSummary',
            'violationSummary',
        ]);

        $recentGrades = $student->scores()
            ->with('subject:id,name')
            ->latest()->limit(5)->get(['id', 'student_id', 'subject_id', 'score', 'created_at']);

        $activeLeave = $student->leavePermissions()
            ->whereIn('status', ['pending', 'approved'])
            ->whereNull('actual_return_date')
            ->latest()->first();

        return response()->json([
            'student' => $student,
            'recentGrades' => $recentGrades,
            'activeLeave' => $activeLeave,
        ]);
    }

    public function grades(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        $semesterId = $request->semester_id;
        $semesters = Semester::with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']);

        $grades = [];
        if ($semesterId) {
            $grades = Score::where('student_id', $student->id)
                ->whereHas('period', fn ($q) => $q->where('semester_id', $semesterId))
                ->with(['subject:id,name', 'component:id,name'])
                ->get();
        }

        return response()->json([
            'student' => $student->only('id', 'full_name', 'nis'),
            'semesters' => $semesters,
            'grades' => $grades,
            'filters' => $request->only('semester_id'),
        ]);
    }

    public function tahfidz(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        $student->load('tahfidzSummary');

        $progress = $student->tahfidzProgress()
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'student' => $student->only('id', 'full_name', 'nis'),
            'summary' => $student->tahfidzSummary,
            'progress' => $progress,
        ]);
    }

    public function violations(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        $student->load('violationSummary');

        $violations = $student->violations()
            ->with('violationType:id,name,points,category')
            ->orderByDesc('date')
            ->paginate(15);

        return response()->json([
            'student' => $student->only('id', 'full_name', 'nis'),
            'summary' => $student->violationSummary,
            'violations' => $violations,
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        $student->load([
            'currentClass:id,name,level',
            'guardians' => function ($q) {
                $q->withPivot('relationship');
            },
            'currentDormAssignment.room.building',
            'emisProfile',
        ]);

        return response()->json([
            'student' => $this->studentPayload($student),
            'dorm_label' => $this->dormLabel($student),
            'role_label' => $request->user()->roles()->orderBy('roles.id')->value('roles.name'),
            'photo_url' => $this->publicPhotoUrl($student->photo),
        ]);
    }

    /**
     * Menyimpan data EMIS/extended profile ke tabel `em_profiles`.
     * Field inti tabel `students` tetap boleh ikut (nama, nik, ttl, gender, alamat, dll).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        $validated = $request->validate([
            'em_profile' => 'nullable|array',
            'full_name' => 'sometimes|nullable|string|max:255',
            'nik' => 'sometimes|nullable|string|max:32',
            'nis' => 'sometimes|nullable|string|max:32',
            'birth_place' => 'sometimes|nullable|string|max:120',
            'birth_date' => 'sometimes|nullable|date',
            'gender' => 'sometimes|nullable|in:'.implode(',', [Student::GENDER_MALE, Student::GENDER_FEMALE]),
            'address' => 'sometimes|nullable|string|max:2000',
        ]);

        $incoming = is_array($validated['em_profile'] ?? null) ? $validated['em_profile'] : [];
        if ($incoming !== []) {
            $this->upsertEmProfile($student, $incoming);
        }

        if (array_key_exists('full_name', $validated)) {
            $student->full_name = $validated['full_name'] ?? $student->full_name;
        }
        if (array_key_exists('nik', $validated)) {
            $student->nik = $validated['nik'];
        }
        if (array_key_exists('nis', $validated)) {
            $student->nis = $validated['nis'];
        }
        if (array_key_exists('birth_place', $validated)) {
            $student->birth_place = $validated['birth_place'];
        }
        if (array_key_exists('birth_date', $validated)) {
            $student->birth_date = $validated['birth_date'];
        }
        if (array_key_exists('gender', $validated)) {
            $student->gender = $validated['gender'];
        }
        if (array_key_exists('address', $validated)) {
            $student->address = $validated['address'];
        }
        $student->save();

        $student->load([
            'currentClass:id,name,level',
            'guardians' => function ($q) {
                $q->withPivot('relationship');
            },
            'currentDormAssignment.room.building',
            'emisProfile',
        ]);

        return response()->json([
            'student' => $this->studentPayload($student),
            'dorm_label' => $this->dormLabel($student),
            'role_label' => $request->user()->roles()->orderBy('roles.id')->value('roles.name'),
            'photo_url' => $this->publicPhotoUrl($student->photo),
            'message' => 'Profil berhasil disimpan.',
        ]);
    }

    private function upsertEmProfile(Student $student, array $incoming): void
    {
        $student->loadMissing('emisProfile');
        $current = $student->emisProfile?->toPayload() ?? [];
        $merged = array_replace_recursive($current, $incoming);
        $attributes = EmProfile::fromPayload($merged);
        $student->emisProfile()->updateOrCreate([], $attributes);
        $student->unsetRelation('emisProfile');
        $student->load('emisProfile');
    }

    private function studentPayload(Student $student): array
    {
        $payload = $student->toArray();
        $payload['em_profile'] = $student->emisProfile?->toPayload() ?? [
            'santri' => [],
            'alamat' => [],
        ];

        return $payload;
    }

    private function publicPhotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        return null;
    }

    private function dormLabel($student): ?string
    {
        $a = $student->currentDormAssignment;
        if (! $a || ! $a->room) {
            return null;
        }
        $room = $a->room;
        $building = $room->building;
        $roomNo = $room->room_number ?? '';
        $name = $building?->name ?? '';

        if ($roomNo === '' && $name === '') {
            return null;
        }

        return 'Kobong '.trim($roomNo.' '.$name);
    }

    public function leaves(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        $leaves = LeavePermission::where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'leaves' => $leaves,
        ]);
    }

    public function schedule(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        $student->load(['currentClass']);

        $class = $student->currentClass;
        abort_unless($class, 404, 'Kelas santri tidak ditemukan.');

        $activeSemester = Semester::where('is_active', true)->first();

        $schedulesQuery = AcademicSchedule::query()
            ->where('class_id', $class->id)
            ->with([
                'subject:id,name',
                'teacher:id,name',
            ])
            ->orderBy('day')
            ->orderBy('time_start');

        $schedules = $schedulesQuery->get();

        $week = [];
        foreach ($schedules as $item) {
            $day = (int) $item->day;
            if (! isset($week[$day])) {
                $week[$day] = [
                    'day_of_week' => $day,
                    'day_name' => self::dayName($day),
                    'entries' => [],
                ];
            }
            $week[$day]['entries'][] = [
                'id' => $item->id,
                'subject' => [
                    'id' => $item->subject->id,
                    'name' => $item->subject->name,
                ],
                'teacher' => [
                    'id' => $item->teacher->id,
                    'name' => $item->teacher->name,
                ],
                'start_time' => $item->time_start,
                'end_time' => $item->time_end,
                'room' => null,
            ];
        }

        ksort($week);

        return response()->json([
            'class' => $class->only(['id', 'name', 'level']),
            'semester' => $activeSemester ? $activeSemester->only(['id', 'name']) : null,
            'week' => array_values($week),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        $query = Invoice::with([
            'student:id,nis,full_name',
            'paymentType:id,name,code,category',
            'academicYear:id,name',
        ])
            ->where('student_id', $student->id)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at');

        return response()->json([
            'invoices' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['status']),
        ]);
    }

    public function invoiceDetail(Request $request, Invoice $invoice): JsonResponse
    {
        $student = $this->getStudent($request);
        abort_unless($invoice->student_id === $student->id, 403, 'Anda tidak memiliki akses ke tagihan ini.');

        $invoice->load([
            'student:id,nis,full_name',
            'paymentType:id,name,code',
            'academicYear:id,name',
            'payments' => fn ($q) => $q->orderByDesc('payment_date'),
        ]);

        $invoice->total_paid = $invoice->totalPaid();
        $invoice->remaining = $invoice->remainingAmount();

        return response()->json(['invoice' => $invoice]);
    }

    public function attendances(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        $query = LessonAttendance::where('student_id', $student->id)
            ->with([
                'lessonSession.schedule.schoolClass:id,name,level',
                'lessonSession.schedule.subject:id,name',
            ])
            ->orderByDesc('lesson_session_id');

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

    private static function dayName(int $day): string
    {
        return match ($day) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Ahad',
            default => 'Unknown',
        };
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        $validated = $request->validate([
            'reason' => ['required', 'string'],
            'leave_date' => ['required', 'date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:leave_date'],
        ]);

        $validated['student_id'] = $student->id;

        $leave = LeavePermission::create($validated);

        return response()->json([
            'message' => 'Permohonan izin berhasil diajukan.',
            'leave' => $leave,
        ], 201);
    }
}
