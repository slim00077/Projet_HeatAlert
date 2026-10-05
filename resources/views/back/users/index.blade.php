@extends('layouts.back')

@section('title', 'HeatAlert - Users')

@section('content')

<div class="page-header">
    <div>
        <h1>Users</h1>
        <p class="subtitle">All registered accounts and their roles</p>
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
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td><span class="status-badge {{ $user->isAdmin() ? 'status-admin' : 'status-resolved' }}">{{ ucfirst($user->role) }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No users yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
