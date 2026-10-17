@extends('layouts.back')

@section('title', 'HeatAlert - Users')

@section('content')

<div class="page-header">
    <div>
        <h1>Users</h1>
        <p class="subtitle">All registered accounts, their roles and the neighborhood of each residence manager</p>
    </div>
</div>

<x-flash-messages />

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Neighborhood</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td><span class="status-badge {{ $user->isAdmin() ? 'status-admin' : ($user->isManager() ? 'status-in-progress' : 'status-resolved') }}">{{ ucfirst($user->role) }}</span></td>
                    <td>{{ $user->neighborhood?->name ?? '-' }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.users.edit', $user) }}" class="icon-action is-edit" title="Change role"><x-icon name="pencil" /></a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No users yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
