<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    public function uploadPhoto(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:official,custom'],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $type = (string) $validated['type'];
        /** @var UploadedFile $photo */
        $photo = $validated['photo'];

        if ($type === 'official' && $user->has_official_photo && ! $user->isAdmin()) {
            return back()->with('error', 'Foto resmi sudah terisi dan hanya admin yang dapat menggantinya.');
        }

        $field = $type === 'official' ? 'official_photo_path' : 'custom_photo_path';
        $oldPath = $user->{$field};
        if (is_string($oldPath) && $oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $stored = $photo->store("profile-photos/{$user->id}", 'public');
        $user->forceFill([$field => $stored])->save();

        return back()->with('success', $type === 'official' ? 'Foto resmi berhasil diunggah.' : 'Foto kustom berhasil diunggah.');
    }

    public function removeCustomPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $oldPath = $user->custom_photo_path;
        if (is_string($oldPath) && $oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
        $user->forceFill(['custom_photo_path' => null])->save();

        return back()->with('success', 'Foto kustom dihapus. Avatar kembali ke foto resmi.');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
