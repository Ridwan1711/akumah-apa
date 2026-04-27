<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diniyyah\AssessmentComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentComponentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/assessment-components/index', [
            'components' => AssessmentComponent::query()
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'weight', 'created_at', 'updated_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        AssessmentComponent::create($validated);

        return redirect()->route('admin.assessment-components.index')
            ->with('success', 'Komponen penilaian berhasil ditambahkan.');
    }

    public function update(Request $request, AssessmentComponent $assessment_component): RedirectResponse
    {
        $validated = $this->validated($request, $assessment_component);

        $assessment_component->update($validated);

        return redirect()->route('admin.assessment-components.index')
            ->with('success', 'Komponen penilaian berhasil diperbarui.');
    }

    public function destroy(AssessmentComponent $assessment_component): RedirectResponse
    {
        if ($assessment_component->scores()->exists()) {
            return redirect()->back()->with('error', 'Tidak bisa menghapus komponen yang sudah memiliki nilai siswa.');
        }

        $assessment_component->delete();

        return redirect()->route('admin.assessment-components.index')
            ->with('success', 'Komponen penilaian berhasil dihapus.');
    }

    /**
     * @return array{name: string, type: string, weight: float|null}
     */
    protected function validated(Request $request, ?AssessmentComponent $existing = null): array
    {
        if ($request->input('weight') === '' || $request->input('weight') === null) {
            $request->merge(['weight' => null]);
        }

        $uniqueName = Rule::unique('assessment_components', 'name')
            ->where('type', $request->input('type'));

        if ($existing) {
            $uniqueName->ignore($existing->id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'type' => ['required', 'string', Rule::in([
                AssessmentComponent::TYPE_DAILY,
                AssessmentComponent::TYPE_EXAM,
            ])],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
        ]);

        $validated['weight'] = $validated['weight'] !== null && $validated['weight'] !== ''
            ? (float) $validated['weight']
            : null;

        return $validated;
    }
}
