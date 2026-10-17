<?php

namespace App\Http\Controllers;

use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::with('neighborhood')->orderBy('role')->orderBy('name')->get();

        return view('back.users.index', compact('users'));
    }

    public function edit(User $user): View
    {
        return view('back.users.edit', [
            'user' => $user,
            'neighborhoods' => Neighborhood::orderBy('name')->get(),
        ]);
    }

    /**
     * Admins promote accounts to residence manager (with the neighborhood they are responsible for).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(User::ROLES)],
            'neighborhood_id' => ['nullable', 'required_if:role,'.User::ROLE_MANAGER, 'exists:neighborhoods,id'],
        ]);

        if ($user->is($request->user()) && $data['role'] !== User::ROLE_ADMIN) {
            return back()->withErrors(['role' => 'You cannot remove your own admin access.']);
        }

        $user->forceFill([
            'role' => $data['role'],
            'neighborhood_id' => $data['role'] === User::ROLE_MANAGER ? $data['neighborhood_id'] : null,
        ])->save();

        return redirect()->route('admin.users.index')->with('success', "{$user->name} is now {$user->role}.");
    }
}
