@extends('layouts.back')

@section('title', 'HeatAlert - Cooling Points')

@section('content')

<div class="page-header">
    <div>
        <h1>Cooling Points</h1>
        <p class="subtitle">Places where residents can cool down</p>
    </div>
    <div class="toolbar">
        <form action="{{ route('admin.cool-spots.index') }}" method="GET" class="search-form">
            <select name="type" aria-label="Filter by type">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected(request('type') == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name...">
            <button type="submit" class="btn btn-outline" aria-label="Search"><x-icon name="search" /></button>
        </form>
        @if (request()->hasAny(['q', 'type']))
            <a href="{{ route('admin.cool-spots.index') }}" class="btn btn-outline">Reset</a>
        @endif
        <a href="{{ route('admin.cool-spots.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Cooling Point</a>
    </div>
</div>

<x-flash-messages />

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Type</th>
                <th>Address</th>
                <th>Hours</th>
                <th>Accessible</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($spots as $spot)
                <tr>
                    <td>{{ $spots->firstItem() + $loop->index }}</td>
                    <td>{{ $spot->name }}</td>
                    <td><span class="chip">{{ $spot->type->name }}</span></td>
                    <td class="wrap">{{ $spot->address }}</td>
                    <td>{{ $spot->opening_hours ?? '—' }}</td>
                    <td>
                        <span class="status-badge {{ $spot->is_accessible ? 'status-resolved' : 'status-pending' }}">
                            {{ $spot->is_accessible ? 'Yes' : 'No' }}
                        </span>
                    </td>
                    <td>
                        <span class="status-badge {{ $spot->is_active ? 'status-resolved' : 'status-pending' }}">
                            {{ $spot->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="actions">
                        @if ($spot->is_active)
                            <a href="{{ route('cool-spots.show', $spot) }}" class="icon-action" title="View on site"><x-icon name="eye" /></a>
                        @endif
                        <a href="{{ route('admin.cool-spots.edit', $spot) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                        <form action="{{ route('admin.cool-spots.destroy', $spot) }}" method="POST" onsubmit="return confirm('Delete this cooling point?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No cooling points found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top: 1.2rem">
    {{ $spots->links() }}
</div>

@endsection