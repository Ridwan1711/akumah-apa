<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentGatewayController;
use App\Models\Diniyyah\AcademicSchedule;
use App\Models\Diniyyah\Score;
use App\Models\EmProfile;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentViolation;
use App\Models\TahfidzProgress;
use App\Notifications\PaymentPendingNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaliController extends Controller
{
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

    private function getChildStudentIds(Request $request): array
    {
        $guardian = $request->user()->guardian;
        abort_unless($guardian, 404, 'Data wali tidak ditemukan.');

        return $guardian->students()->pluck('students.id')->toArray();
    }

    public function dashboard(Request $request): JsonResponse
    {
        $guardian = $request->user()->guardian;
        abort_unless($guardian, 404, 'Data wali tidak ditemukan.');

        $children = $guardian->students()
            ->with(['currentClass:id,name', 'tahfidzSummary', 'violationSummary'])
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->get();

        return response()->json(['children' => $children]);
    }

    public function children(Request $request): JsonResponse
    {
        $guardian = $request->user()->guardian;
        abort_unless($guardian, 404, 'Data wali tidak ditemukan.');

        $children = $guardian->students()
            ->with(['currentClass:id,name', 'tahfidzSummary', 'violationSummary'])
            ->where('status', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->get();

        return response()->json(['children' => $children]);
    }

    public function childDetail(Request $request, Student $student): JsonResponse
    {
        $guardian = $request->user()->guardian;
        abort_unless($guardian && $guardian->students()->where('students.id', $student->id)->exists(), 403, 'Anda tidak memiliki akses ke data santri ini.');

        $student->load([
            'currentClass:id,name,level',
            'tahfidzSummary',
            'violationSummary',
            'currentDormAssignment.room.building',
            'guardians' => fn ($q) => $q->withPivot('relationship'),
            'emisProfile',
        ]);

        $activeSemester = \App\Models\Semester::where('is_active', true)->first();
        $semesterId = $request->semester_id ?? $activeSemester?->id;

        $grades = [];
        $semester = null;
        if ($semesterId) {
            $semester = \App\Models\Semester::with('academicYear:id,name')->find($semesterId);
            $grades = Score::where('student_id', $student->id)
                ->whereHas('period', fn ($q) => $q->where('semester_id', $semesterId))
                ->with(['subject:id,name', 'component:id,name'])
                ->get();
        }

        $recentTahfidz = TahfidzProgress::where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->limit(10)->get();

        $recentViolations = StudentViolation::where('student_id', $student->id)
            ->with('violationType:id,name,points,category')
            ->orderByDesc('date')
            ->limit(10)->get();

        $semesters = \App\Models\Semester::with('academicYear:id,name')->orderByDesc('id')->get(['id', 'name', 'academic_year_id']);

        return response()->json([
            'student' => $this->studentPayload($student),
            'dorm_label' => $this->dormLabel($student),
            'semesters' => $semesters,
            'currentSemesterId' => $semesterId,
            'semester' => $semester,
            'grades' => $grades,
            'recentTahfidz' => $recentTahfidz,
            'recentViolations' => $recentViolations,
        ]);
    }

    public function updateChildProfile(Request $request, Student $student): JsonResponse
    {
        $guardian = $request->user()->guardian;
        abort_unless($guardian && $guardian->students()->where('students.id', $student->id)->exists(), 403, 'Anda tidak memiliki akses ke data santri ini.');

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
            'tahfidzSummary',
            'violationSummary',
            'currentDormAssignment.room.building',
            'guardians' => fn ($q) => $q->withPivot('relationship'),
            'emisProfile',
        ]);

        return response()->json([
            'student' => $this->studentPayload($student),
            'dorm_label' => $this->dormLabel($student),
            'message' => 'Data santri berhasil disimpan.',
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

    private function dormLabel(Student $student): ?string
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

    public function childSchedule(Request $request, Student $student): JsonResponse
    {
        $guardian = $request->user()->guardian;
        abort_unless($guardian && $guardian->students()->where('students.id', $student->id)->exists(), 403, 'Anda tidak memiliki akses ke data santri ini.');

        $student->load('currentClass:id,name,level');
        abort_unless($student->currentClass, 404, 'Kelas santri tidak ditemukan.');

        $schedules = AcademicSchedule::query()
            ->where('class_id', $student->currentClass->id)
            ->with([
                'subject:id,name',
                'teacher:id,name',
            ])
            ->orderBy('day')
            ->orderBy('time_start')
            ->get();

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
                    'id' => $item->subject?->id,
                    'name' => $item->subject?->name,
                ],
                'teacher' => [
                    'id' => $item->teacher?->id,
                    'name' => $item->teacher?->name,
                ],
                'start_time' => $item->time_start,
                'end_time' => $item->time_end,
                'room' => null,
            ];
        }

        ksort($week);

        return response()->json([
            'student' => $student->only(['id', 'full_name', 'nis']),
            'class' => $student->currentClass,
            'week' => array_values($week),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $studentIds = $this->getChildStudentIds($request);

        $query = Invoice::with([
            'student:id,nis,full_name',
            'paymentType:id,name,code,category',
            'academicYear:id,name',
        ])
            ->whereIn('student_id', $studentIds)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at');

        $invoices = $query->paginate(15)->withQueryString();

        return response()->json([
            'invoices' => $invoices,
            'filters' => $request->only(['status']),
        ]);
    }

    public function invoiceDetail(Request $request, Invoice $invoice): JsonResponse
    {
        $studentIds = $this->getChildStudentIds($request);
        abort_unless(in_array($invoice->student_id, $studentIds), 403);

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

    public function createCharge(Request $request, Invoice $invoice): JsonResponse
    {
        $studentIds = $this->getChildStudentIds($request);
        abort_unless(in_array($invoice->student_id, $studentIds), 403);

        $request->merge(['invoice_id' => $invoice->id]);

        return app(PaymentGatewayController::class)->createCharge($request);
    }

    public function uploadProof(Request $request, Invoice $invoice): JsonResponse
    {
        $studentIds = $this->getChildStudentIds($request);
        abort_unless(in_array($invoice->student_id, $studentIds), 403);

        $request->validate([
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

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
        $studentIds = $this->getChildStudentIds($request);

        $payments = Payment::whereHas('invoice', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->with([
                'invoice:id,invoice_number,student_id,payment_type_id,final_amount',
                'invoice.student:id,full_name',
                'invoice.paymentType:id,name',
            ])
            ->orderByDesc('payment_date')
            ->paginate(15);

        return response()->json(['payments' => $payments]);
    }
}
