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
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Fill only email in user model
        $user->fill($request->safe()->only(['email']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Update full_name in the associated profile
        if ($request->has('name')) {
            $user->profile()->update([
                'full_name' => $request->input('name'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Download the authenticated user's uploaded document securely.
     */
    public function downloadDocument(string $type): StreamedResponse
    {
        $user = Auth::user();
        $profile = $user?->profile;

        if (! $profile) {
            abort(404);
        }

        $path = match ($type) {
            'birth_certificate' => $profile->birth_certificate_path,
            'nic_front' => $profile->nic_front_path,
            'nic_back' => $profile->nic_back_path,
            default => abort(404),
        };

        if (! Storage::exists($path)) {
            abort(404);
        }

        return Storage::download($path);
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $profile = $user->profile;

        if ($profile) {
            foreach ([$profile->birth_certificate_path, $profile->nic_front_path, $profile->nic_back_path] as $path) {
                if ($path && Storage::exists($path)) {
                    Storage::delete($path);
                }
            }
        }

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
