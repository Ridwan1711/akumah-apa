<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGuardianRequest;
use App\Http\Requests\Admin\UpdateGuardianRequest;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    public function attach(Student $student): Response
    {
        $existingGuardians = Guardian::whereHas('students')
            ->whereNotIn('id', $student->guardians->pluck('id'))
            ->withCount('students')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'phone', 'email']);

        return Inertia::render('admin/guardians/attach', [
            'student' => $student,
            'existingGuardians' => $existingGuardians,
        ]);
    }

    public function storeAttach(Request $request, Student $student): RedirectResponse
    {
        $request->validate([
            'guardian_id' => ['required', 'exists:guardians,id'],
            'relationship' => ['required', \Illuminate\Validation\Rule::in(Guardian::RELATIONSHIPS)],
        ]);

        $guardian = Guardian::findOrFail($request->guardian_id);
        abort_if($student->guardians()->where('guardians.id', $guardian->id)->exists(), 422, 'Wali ini sudah terdaftar untuk santri ini.');

        $guardian->students()->attach($student->id, ['relationship' => $request->relationship]);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Wali berhasil ditambahkan ke santri ini.');
    }

    public function create(Student $student): Response
    {
        return Inertia::render('admin/guardians/create', [
            'student' => $student,
        ]);
    }

    public function store(StoreGuardianRequest $request, Student $student): RedirectResponse
    {
        $data = $request->validated();
        $relationship = $data['relationship'] ?? 'wali';
        unset($data['relationship']);

        $guardian = Guardian::create(array_merge($data, ['relationship' => $relationship]));
        $guardian->students()->attach($student->id, ['relationship' => $relationship]);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Data wali santri berhasil ditambahkan.');
    }

    public function edit(Student $student, Guardian $guardian): Response
    {
        $pivot = $guardian->students()->where('students.id', $student->id)->first()?->pivot;
        $guardian->relationship = $pivot?->relationship ?? 'wali';

        return Inertia::render('admin/guardians/edit', [
            'student' => $student,
            'guardian' => $guardian,
        ]);
    }

    public function update(UpdateGuardianRequest $request, Student $student, Guardian $guardian): RedirectResponse
    {
        $data = $request->validated();
        $relationship = $data['relationship'] ?? 'wali';
        unset($data['relationship']);

        $guardian->update(array_merge($data, ['relationship' => $relationship]));
        $guardian->students()->updateExistingPivot($student->id, ['relationship' => $relationship]);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Data wali santri berhasil diperbarui.');
    }

    public function destroy(Student $student, Guardian $guardian): RedirectResponse
    {
        $guardian->students()->detach($student->id);
        if ($guardian->students()->count() === 0 && ! $guardian->user_id) {
            $guardian->delete();
        }

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Data wali santri berhasil dihapus.');
    }
}
