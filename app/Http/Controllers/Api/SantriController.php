<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentGatewayController;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\Score;
use App\Models\EmProfile;
use App\Models\Invoice;
use App\Models\LeavePermission;
use App\Models\LessonAttendance;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\Student;
use App\Notifications\PaymentPendingNotification;
use App\Services\Finance\InstallmentService;
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
            'currentClass:id,name,grade_level_id',
            'guardians' => function ($q) {
                $q->withPivot('relationship');
            },
            'currentDormAssignment.room.building',
            'emisProfile',
        ]);

        return response()->json([
            'student' => $this->studentPayload($student),
            'user' => $request->user()->load('roles'),
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
            'currentClass:id,name,grade_level_id',
            'guardians' => function ($q) {
                $q->withPivot('relationship');
            },
            'currentDormAssignment.room.building',
            'emisProfile',
        ]);

        return response()->json([
            'student' => $this->studentPayload($student),
            'user' => $request->user()->load('roles'),
            'dorm_label' => $this->dormLabel($student),
            'role_label' => $request->user()->roles()->orderBy('roles.id')->value('roles.name'),
            'photo_url' => $this->publicPhotoUrl($student->photo),
            'message' => 'Profil berhasil disimpan.',
        ]);
    }

    private function upsertEmProfile(Student $student, array $incoming): void
    {
        if (isset($incoming['santri']) && is_array($incoming['santri'])) {
            unset($incoming['santri']['nism']);
        }

        $student->loadMissing('emisProfile');
        $current = $student->emProfilePayload();
        $merged = array_replace_recursive($current, $incoming);
        $attributes = EmProfile::fromPayload($merged);
        $student->emisProfile()->updateOrCreate([], $attributes);
        $student->forceFill(['em_profile' => $merged])->save();
        $student->unsetRelation('emisProfile');
        $student->load('emisProfile');
    }

    private function studentPayload(Student $student): array
    {
        $payload = $student->toArray();
        $payload['em_profile'] = $student->emProfilePayload() ?: [
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

        $activeSemester = AcademicPeriod::query()->active()->with('semester:id,name')->first()?->semester;

        $schedulesQuery = AcademicSchedule::query()
            ->forActivePublishedSet()
            ->where('class_id', $class->id)
            ->whereIn('day', AcademicSchedule::TEACHING_DAYS)
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
            'class' => $class->only(['id', 'name', 'grade_level_id']),
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
            ->withCount('payments')
            ->withSum([
                'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->withSum([
                'payments as pending_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_PENDING),
            ], 'amount')
            ->where('student_id', $student->id)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at');

        $invoices = $query->paginate(15)->withQueryString();
        $invoices->getCollection()->transform(function (Invoice $invoice): Invoice {
            $invoice->total_paid = (float) ($invoice->verified_paid_amount ?? 0);
            $invoice->pending_amount = (float) ($invoice->pending_paid_amount ?? 0);
            $invoice->remaining = max(0, (float) $invoice->final_amount - $invoice->total_paid);

            return $invoice;
        });

        return response()->json([
            'invoices' => $invoices,
            'filters' => $request->only(['status']),
        ]);
    }

    public function invoiceDetail(Request $request, Invoice $invoice): JsonResponse
    {
        $student = $this->getStudent($request);
        abort_unless($invoice->student_id === $student->id, 403, 'Anda tidak memiliki akses ke tagihan ini.');

        $invoice->load([
            'student:id,nis,full_name',
            'paymentType:id,name,code,default_breakdown',
            'academicYear:id,name',
            'payments' => fn ($q) => $q->orderByDesc('payment_date'),
        ]);

        $invoice->total_paid = $invoice->totalPaid();
        $invoice->pending_amount = $invoice->pendingAmount();
        $invoice->remaining = $invoice->remainingAmount();
        $invoice->breakdown_items = $invoice->resolvedBreakdown();

        return response()->json(['invoice' => $invoice]);
    }

    public function createCharge(Request $request, Invoice $invoice): JsonResponse
    {
        $student = $this->getStudent($request);
        abort_unless($invoice->student_id === $student->id, 403, 'Anda tidak memiliki akses ke tagihan ini.');

        $validated = $request->validate([
            'payment_method' => ['required', 'in:bri_va,qris'],
            'amount' => ['nullable', 'numeric', 'min:1'],
        ]);
        $payload = [
            'invoice_id' => $invoice->id,
            'payment_method' => $validated['payment_method'],
        ];
        if (isset($validated['amount'])) {
            $payload['amount'] = (float) $validated['amount'];
        }
        $request->merge($payload);

        return app(PaymentGatewayController::class)->createCharge($request);
    }

    public function uploadProof(Request $request, Invoice $invoice): JsonResponse
    {
        $student = $this->getStudent($request);
        abort_unless($invoice->student_id === $student->id, 403, 'Anda tidak memiliki akses ke tagihan ini.');

        $request->validate([
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);
        app(InstallmentService::class)->validateAmount($invoice, (float) $request->amount);

        $proofPath = $request->file('proof_file')->store('payment-proofs', 'public');

        $payment = Payment::create([
            'payment_number' => Payment::generateNumber(),
            'invoice_id' => $invoice->id,
            'amount' => $request->amount,
            'payment_method' => Payment::METHOD_BANK_TRANSFER,
            'payment_date' => now()->toDateString(),
            'proof_file' => $proofPath,
            'status' => Payment::STATUS_PENDING,
            'notes' => $request->notes,
        ]);

        \App\Models\User::whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin_keuangan']))
            ->where('is_active', true)
            ->each(fn ($user) => $user->notify(new PaymentPendingNotification($payment)));

        return response()->json([
            'message' => 'Bukti pembayaran berhasil diupload. Menunggu verifikasi admin.',
            'payment' => $payment->load('invoice:id,invoice_number'),
        ], 201);
    }

    public function paymentHistory(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        $payments = Payment::whereHas('invoice', fn ($q) => $q->where('student_id', $student->id))
            ->with([
                'invoice:id,invoice_number,student_id,payment_type_id,final_amount',
                'invoice.student:id,full_name',
                'invoice.paymentType:id,name',
            ])
            ->orderByDesc('payment_date')
            ->paginate(15);

        return response()->json(['payments' => $payments]);
    }

    public function attendances(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);

        $query = LessonAttendance::where('student_id', $student->id)
            ->with([
                'lessonSession.schedule.schoolClass:id,name,grade_level_id',
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
