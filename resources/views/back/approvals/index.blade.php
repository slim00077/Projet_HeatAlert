@extends('layouts.back')

@section('title', 'HeatAlert - Residence Approvals')

@section('content')

<div class="page-header">
    <div>
        <h1>Residence Approvals</h1>
        <p class="subtitle">Residences submitted by residence managers wait here until you approve them</p>
    </div>
</div>

<x-flash-messages />

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Residence</th>
                <th>Address</th>
                <th>Neighborhood</th>
                <th>Submitted by</th>
                <th>Submitted</th>
                <th>Decision</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pending as $residence)
                <tr>
                    <td><a href="{{ route('residences.show', $residence) }}">{{ $residence->name }}</a></td>
                    <td>{{ $residence->address }}</td>
                    <td>{{ $residence->neighborhood }}</td>
                    <td>{{ $residence->user->name }}</td>
                    <td>{{ $residence->created_at->diffForHumans() }}</td>
                    <td>
                        <div class="toolbar">
                            <form action="{{ route('admin.approvals.approve', $residence) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                            </form>
                            <form action="{{ route('admin.approvals.reject', $residence) }}" method="POST" class="toolbar">
                                @csrf
                                <input type="text" name="rejection_reason" placeholder="Reason for rejection" required maxlength="500" class="inline-input">
                                <button type="submit" class="btn btn-outline btn-sm">Reject</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Nothing to approve. All submitted residences were reviewed.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="page-header" style="margin-top: 2rem">
    <h2>Recent decisions</h2>
</div>

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Residence</th>
                <th>Submitted by</th>
                <th>Status</th>
                <th>Reviewed by</th>
                <th>When</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reviewed as $residence)
                <tr>
                    <td><a href="{{ route('residences.show', $residence) }}">{{ $residence->name }}</a></td>
                    <td>{{ $residence->user->name }}</td>
                    <td><x-status-badge :status="ucfirst($residence->status)" /></td>
                    <td>{{ $residence->reviewer?->name ?? '-' }}</td>
                    <td>{{ $residence->reviewed_at->diffForHumans() }}</td>
                    <td class="wrap">{{ $residence->rejection_reason ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No decisions yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
