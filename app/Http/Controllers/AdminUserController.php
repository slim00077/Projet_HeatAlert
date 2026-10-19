<?php

namespace App\Http\Controllers;

use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::with('neighborhood')
            ->withCount(['residences', 'residentReports'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return view('back.users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        return view('back.users.create', ['neighborhoods' => Neighborhood::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'neighborhood_id' => ['nullable', 'required_if:role,'.User::ROLE_MANAGER, 'exists:neighborhoods,id'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = new User(collect($data)->only(['name', 'email', 'password'])->all());
        $user->role = $data['role'];
        $user->neighborhood_id = $data['role'] === User::ROLE_MANAGER ? $data['neighborhood_id'] : null;
        $user->email_verified_at = now();
        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('back.users.edit', [
            'user' => $user,
            'neighborhoods' => Neighborhood::orderBy('name')->get(),
        ]);
    }

    /**
     * Admins edit accounts and promote them to residence manager (with the neighborhood they are responsible for).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(User::ROLES)],
            'neighborhood_id' => ['nullable', 'required_if:role,'.User::ROLE_MANAGER, 'exists:neighborhoods,id'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        if ($user->is($request->user()) && $data['role'] !== User::ROLE_ADMIN) {
            return back()->withInput()->withErrors(['role' => 'You cannot remove your own administrator role.']);
        }

        $user->fill(collect($data)->only(['name', 'email'])->all());
        $user->role = $data['role'];
        $user->neighborhood_id = $data['role'] === User::ROLE_MANAGER ? $data['neighborhood_id'] : null;

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }
}
