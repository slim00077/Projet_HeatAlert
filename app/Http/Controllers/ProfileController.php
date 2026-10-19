<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']], [], ['photo' => 'profile photo']);

        $user = $request->user();

        if ($user->profile_photo_path !== null) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->forceFill(['profile_photo_path' => $request->file('photo')->store('profile-photos', 'public')])->save();

        return back()->with('success', 'Profile photo updated.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile_photo_path !== null) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->forceFill(['profile_photo_path' => null])->save();
        }

        return back()->with('success', 'Profile photo removed.');
    }

    /**
     * Delete the account after the password is confirmed. The last admin cannot delete theirs.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('deleteAccount', ['password' => ['required', 'current_password']]);

        $user = $request->user();

        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() === 1) {
            return back()->withErrors(['password' => 'You are the only admin, so this account cannot be deleted.'], 'deleteAccount');
        }

        if ($user->profile_photo_path !== null) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Your account has been deleted.');
    }
}
