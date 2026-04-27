<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Student;
use App\Models\TahfidzProgress;
use App\Models\TahfidzSummary;
use App\Models\TahfidzTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TahfidzController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Student::where('status', Student::STATUS_ACTIVE)
            ->with(['tahfidzSummary', 'currentClass:id,name'])
            ->when($request->class_id, fn ($q, $id) => $q->where('current_class_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where('full_name', 'ilike', "%{$s}%"))
            ->orderBy('full_name');

        return Inertia::render('admin/tahfidz/index', [
            'students' => $query->paginate(15)->withQueryString(),
            'classes' => SchoolClass::orderBy('name')->get(['id', 'name', 'level']),
            'filters' => $request->only(['class_id', 'search']),
        ]);
    }

    public function show(Student $student): Response
    {
        $student->load(['tahfidzSummary', 'currentClass:id,name']);

        return Inertia::render('admin/tahfidz/show', [
            'student' => $student,
            'targets' => TahfidzTarget::where('student_id', $student->id)->orderByDesc('created_at')->get(),
            'progress' => TahfidzProgress::where('student_id', $student->id)
                ->with('validator:id,name')
                ->orderByDesc('created_at')
                ->paginate(15),
        ]);
    }

    public function storeTarget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'target_juz' => ['required', 'integer', 'min:1', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        TahfidzTarget::create($validated);

        return redirect()->back()->with('success', 'Target tahfidz berhasil ditetapkan.');
    }

    public function updateTarget(Request $request, TahfidzTarget $target): RedirectResponse
    {
        $validated = $request->validate([
            'target_juz' => ['required', 'integer', 'min:1', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', Rule::in(TahfidzTarget::STATUSES)],
        ]);

        $target->update($validated);

        return redirect()->back()->with('success', 'Target tahfidz berhasil diperbarui.');
    }

    public function destroyTarget(TahfidzTarget $target): RedirectResponse
    {
        $target->delete();

        return redirect()->back()->with('success', 'Target tahfidz berhasil dihapus.');
    }

    public function storeProgress(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'juz' => ['required', 'integer', 'min:1', 'max:30'],
            'surah_from' => ['required', 'string', 'max:100'],
            'surah_to' => ['nullable', 'string', 'max:100'],
            'ayat_from' => ['required', 'integer', 'min:1'],
            'ayat_to' => ['required', 'integer', 'min:1', 'gte:ayat_from'],
            'type' => ['required', Rule::in(TahfidzProgress::TYPES)],
            'grade' => ['required', Rule::in(TahfidzProgress::GRADES)],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['surah_to'] = $validated['surah_to'] ?: $validated['surah_from'];
        $validated['validated_by'] = $request->user()->id;
        $validated['validated_at'] = now();

        TahfidzProgress::create($validated);

        $this->recalculateSummary($validated['student_id']);

        return redirect()->back()->with('success', 'Setoran tahfidz berhasil dicatat.');
    }

    public function updateProgress(Request $request, TahfidzProgress $progress): RedirectResponse
    {
        $validated = $request->validate([
            'juz' => ['required', 'integer', 'min:1', 'max:30'],
            'surah_from' => ['required', 'string', 'max:100'],
            'surah_to' => ['nullable', 'string', 'max:100'],
            'ayat_from' => ['required', 'integer', 'min:1'],
            'ayat_to' => ['required', 'integer', 'min:1', 'gte:ayat_from'],
            'type' => ['required', Rule::in(TahfidzProgress::TYPES)],
            'grade' => ['required', Rule::in(TahfidzProgress::GRADES)],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['surah_to'] = $validated['surah_to'] ?: $validated['surah_from'];
        $validated['validated_by'] = $request->user()->id;
        $validated['validated_at'] = now();

        $progress->update($validated);
        $this->recalculateSummary((int) $progress->student_id);

        return redirect()->back()->with('success', 'Setoran tahfidz berhasil diperbarui.');
    }

    public function destroyProgress(TahfidzProgress $progress): RedirectResponse
    {
        $studentId = (int) $progress->student_id;
        $progress->delete();
        $this->recalculateSummary($studentId);

        return redirect()->back()->with('success', 'Setoran tahfidz berhasil dihapus.');
    }

    private function recalculateSummary(int $studentId): void
    {
        $completedJuz = TahfidzProgress::where('student_id', $studentId)
            ->where('type', TahfidzProgress::TYPE_ZIYADAH)
            ->distinct('juz')
            ->count('juz');

        $lastDate = TahfidzProgress::where('student_id', $studentId)
            ->latest('validated_at')
            ->value('validated_at');

        TahfidzSummary::updateOrCreate(
            ['student_id' => $studentId],
            [
                'total_juz_completed' => $completedJuz,
                'last_hafalan_date' => $lastDate?->toDateString(),
            ]
        );
    }
}
