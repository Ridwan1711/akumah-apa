<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Student;
use App\Models\StudentViolation;
use App\Models\ViolationSummary;
use App\Models\ViolationType;
use App\Notifications\ViolationRecordedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ViolationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Student::where('status', Student::STATUS_ACTIVE)
            ->with(['violationSummary', 'currentClass:id,name'])
            ->when($request->class_id, fn ($q, $id) => $q->where('current_class_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where('full_name', 'ilike', "%{$s}%"))
            ->orderBy('full_name');

        return Inertia::render('admin/violations/index', [
            'students' => $query->paginate(15)->withQueryString(),
            'classes' => SchoolClass::orderBy('name')->get(['id', 'name']),
            'recentViolations' => StudentViolation::with(['student:id,full_name,nis', 'violationType:id,name,points'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
            'filters' => $request->only(['class_id', 'search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/violations/create', [
            'students' => Student::where('status', Student::STATUS_ACTIVE)->orderBy('full_name')->get(['id', 'nis', 'full_name']),
            'violationTypes' => ViolationType::orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'violation_type_id' => ['required', 'exists:violation_types,id'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['handled_by'] = $request->user()->id;
        $validated['status'] = StudentViolation::STATUS_OPEN;

        $violation = StudentViolation::create($validated);
        $this->recalculateSummary($validated['student_id']);

        $violation->loadMissing('student.user', 'student.guardians.user', 'violationType');
        $student = $violation->student;
        if ($student?->user) {
            $student->user->notify(new ViolationRecordedNotification($violation));
        }
        if ($student) {
            $student->guardians
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->each(fn ($user) => $user->notify(new ViolationRecordedNotification($violation)));
        }

        return redirect()->route('admin.violations.index')->with('success', 'Pelanggaran berhasil dicatat.');
    }

    public function resolve(Request $request, StudentViolation $violation): RedirectResponse
    {
        $request->validate(['resolution_notes' => 'nullable|string']);
        $violation->update([
            'status' => StudentViolation::STATUS_RESOLVED,
            'resolution_notes' => $request->resolution_notes,
        ]);

        return redirect()->back()->with('success', 'Pelanggaran berhasil diselesaikan.');
    }

    public function types(): Response
    {
        return Inertia::render('admin/violations/types', [
            'types' => ViolationType::orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'points' => ['required', 'integer', 'min:1'],
            'category' => ['required', Rule::in(ViolationType::CATEGORIES)],
        ]);

        ViolationType::create($request->only('name', 'points', 'category'));

        return redirect()->back()->with('success', 'Tipe pelanggaran berhasil ditambahkan.');
    }

    public function destroyType(ViolationType $violationType): RedirectResponse
    {
        $violationType->delete();

        return redirect()->back()->with('success', 'Tipe pelanggaran berhasil dihapus.');
    }

    private function recalculateSummary(int $studentId): void
    {
        $totalPoints = StudentViolation::where('student_id', $studentId)
            ->join('violation_types', 'student_violations.violation_type_id', '=', 'violation_types.id')
            ->sum('violation_types.points');

        $lastDate = StudentViolation::where('student_id', $studentId)->max('date');

        ViolationSummary::updateOrCreate(
            ['student_id' => $studentId],
            ['total_points' => $totalPoints, 'last_violation_date' => $lastDate]
        );
    }
}
