<?php

namespace App\Http\Controllers;

use App\Models\Residence;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ResidenceMemberController extends Controller
{
    public function store(Request $request, Residence $residence): RedirectResponse
    {
        Gate::authorize('manageMembers', $residence);

        $data = $request->validate(['email' => ['required', 'email']]);

        $resident = User::where('email', $data['email'])->where('role', User::ROLE_RESIDENT)->first();

        if ($resident === null) {
            return back()->withErrors(['email' => 'No resident account uses this email. Ask the person to register first.'])->withInput();
        }

        if ($residence->members()->whereKey($resident->id)->exists()) {
            return back()->withErrors(['email' => "{$resident->name} already lives in this residence."])->withInput();
        }

        $residence->members()->attach($resident->id);

        return back()->with('success', "{$resident->name} was added to {$residence->name}.");
    }

    public function destroy(Residence $residence, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $residence);

        $residence->members()->detach($user->id);

        return back()->with('success', "{$user->name} was removed from {$residence->name}.");
    }
}
