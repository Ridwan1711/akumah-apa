<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Diniyyah\SchoolClass;
use App\Models\FeeSchedule;
use App\Models\PaymentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeeScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $perPageOptions = [25, 50, 75, 100, 1000];
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 25;
        }

        $query = FeeSchedule::with(['paymentType:id,name,code,category', 'academicYear:id,name'])
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->payment_type_id, fn ($q, $id) => $q->where('payment_type_id', $id))
            ->orderByDesc('academic_year_id');

        return Inertia::render('admin/fee-schedules/index', [
            'feeSchedules' => $query->paginate($perPage)->withQueryString(),
            'paymentTypes' => PaymentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'category', 'default_amount']),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']),
            'classLevels' => SchoolClass::LEVELS,
            'filters' => $request->only(['academic_year_id', 'payment_type_id', 'per_page']),
            'perPageOptions' => $perPageOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_type_id' => ['required', 'exists:payment_types,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'class_level' => ['nullable', Rule::in(SchoolClass::LEVELS)],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $exists = FeeSchedule::where('payment_type_id', $validated['payment_type_id'])
            ->where('academic_year_id', $validated['academic_year_id'])
            ->where('class_level', $validated['class_level'] ?? null)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'Tarif untuk kombinasi jenis bayar, tahun ajaran, dan level kelas ini sudah ada.');
        }

        FeeSchedule::create($validated);

        return redirect()->route('admin.fee-schedules.index')
            ->with('success', 'Tarif berhasil ditambahkan.');
    }

    public function update(Request $request, FeeSchedule $feeSchedule): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $feeSchedule->update($validated);

        return redirect()->route('admin.fee-schedules.index')
            ->with('success', 'Tarif berhasil diperbarui.');
    }

    public function destroy(FeeSchedule $feeSchedule): RedirectResponse
    {
        $feeSchedule->delete();

        return redirect()->route('admin.fee-schedules.index')
            ->with('success', 'Tarif berhasil dihapus.');
    }
}
