<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentDiscount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentDiscountController extends Controller
{
    public function index(Request $request): Response
    {
        $query = StudentDiscount::with([
            'student:id,nis,full_name',
            'paymentType:id,name,code',
            'academicYear:id,name',
            'approver:id,name',
        ])
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->orderByDesc('created_at');

        return Inertia::render('admin/student-discounts/index', [
            'discounts' => $query->paginate(20)->withQueryString(),
            'students' => Student::where('status', Student::STATUS_ACTIVE)->orderBy('full_name')->get(['id', 'nis', 'full_name']),
            'paymentTypes' => PaymentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']),
            'filters' => $request->only(['academic_year_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'payment_type_id' => ['required', 'exists:payment_types,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'discount_type' => ['required', Rule::in(StudentDiscount::TYPES)],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['approved_by'] = $request->user()->id;

        $exists = StudentDiscount::where('student_id', $validated['student_id'])
            ->where('payment_type_id', $validated['payment_type_id'])
            ->where('academic_year_id', $validated['academic_year_id'])
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'Diskon untuk santri ini pada jenis bayar dan tahun ajaran tersebut sudah ada.');
        }

        StudentDiscount::create($validated);

        return redirect()->route('admin.student-discounts.index')
            ->with('success', 'Diskon santri berhasil ditambahkan.');
    }

    public function destroy(StudentDiscount $studentDiscount): RedirectResponse
    {
        $studentDiscount->delete();

        return redirect()->route('admin.student-discounts.index')
            ->with('success', 'Diskon santri berhasil dihapus.');
    }
}
