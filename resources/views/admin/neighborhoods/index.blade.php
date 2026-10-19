@extends('layouts.back')

@section('title', 'HeatAlert - Neighborhoods')

@section('content')

<div class="page-header">
    <div>
        <h1>Neighborhoods</h1>
        <p class="subtitle">Areas monitored for heat risk</p>
    </div>
    <div class="toolbar">
        <form action="{{ route('admin.neighborhoods.index') }}" method="GET" class="search-form">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by name...">
            <button type="submit" class="btn btn-outline" aria-label="Search"><x-icon name="search" /></button>
        </form>
        <a href="{{ route('admin.neighborhoods.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Neighborhood</a>
    </div>
</div>

<x-flash-messages />

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Current risk</th>
                <th>Population</th>
                <th>Residences</th>
                <th>Alerts</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($neighborhoods as $neighborhood)
                @php($risk = $neighborhood->currentRisk())
                <tr>
                    <td><a href="{{ route('admin.neighborhoods.show', $neighborhood) }}">{{ $neighborhood->name }}</a></td>
                    <td>
                        @if ($risk)
                            <x-severity-badge :severity="$risk" />
                        @else
                            <span class="severity-badge soft-green">None</span>
                        @endif
                    </td>
                    <td>{{ $neighborhood->population !== null ? number_format($neighborhood->population) : '-' }}</td>
                    <td>{{ $neighborhood->residences_count }}</td>
                    <td>{{ $neighborhood->heat_alerts_count }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.neighborhoods.show', $neighborhood) }}" class="icon-action" title="View"><x-icon name="eye" /></a>
                        <a href="{{ route('admin.neighborhoods.edit', $neighborhood) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                        <form action="{{ route('admin.neighborhoods.destroy', $neighborhood) }}" method="POST" onsubmit="return confirm('Delete this neighborhood and its heat alerts?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No neighborhoods found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="pagination-wrap">{{ $neighborhoods->links() }}</div>

@endsection
