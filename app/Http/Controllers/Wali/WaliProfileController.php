<?php

namespace App\Http\Controllers\Wali;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wali\UpdateWaliProfileRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class WaliProfileController extends Controller
{
    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::WALI_SANTRI), 403);

        if (! $user->must_complete_profile) {
            return redirect()->route('dashboard');
        }

        $guardian = $user->guardian;
        abort_unless($guardian, 404, 'Data wali tidak ditemukan.');

        return Inertia::render('wali/profile-complete', [
            'guardian' => $guardian,
        ]);
    }

    public function update(UpdateWaliProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::WALI_SANTRI), 403);

        $guardian = $user->guardian;
        abort_unless($guardian, 404);

        $validated = $request->validated();
        $guardian->update([
            'full_name' => $validated['full_name'],
            'nik' => $validated['nik'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'occupation' => $validated['occupation'] ?? null,
            'income_band' => $validated['income_band'] ?? null,
            'relationship' => $validated['relationship'],
        ]);

        $user->update([
            'name' => $validated['full_name'],
            'must_complete_profile' => false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Profil wali berhasil dilengkapi.');
    }
}
