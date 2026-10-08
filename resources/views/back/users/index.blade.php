@extends('layouts.back')

@section('title', 'HeatAlert - Users')

@section('content')

<div class="page-header">
    <div>
        <h1>Users</h1>
        <p class="subtitle">All registered accounts and their roles</p>
    </div>
    <div class="toolbar">
        <form action="{{ route('admin.users.index') }}" method="GET" class="search-form">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or email...">
            <button type="submit" class="btn btn-outline" aria-label="Search"><x-icon name="search" /></button>
        </form>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add User</a>
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
                <th>Residences</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td><span class="status-badge {{ $user->isAdmin() ? 'status-admin' : 'status-resolved' }}">{{ ucfirst($user->role) }}</span></td>
                    <td>{{ $user->residences_count }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.users.edit', $user) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                        @unless ($user->is(auth()->user()))
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Delete this user and all of their residences and reports?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No users found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
