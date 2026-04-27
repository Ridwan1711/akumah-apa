<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForceChangePasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/force-change-password');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        if ($user->must_complete_profile && $user->hasRole(Role::WALI_SANTRI)) {
            return redirect()->route('wali.profile.complete')->with('success', 'Password berhasil diubah. Lengkapi profil wali Anda.');
        }

        return redirect()->route('dashboard')->with('success', 'Password berhasil diubah.');
    }
}
