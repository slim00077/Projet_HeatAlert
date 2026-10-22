@extends('layouts.back')

@section('title', 'Power Outages - Admin')

@section('content')
    <div class="page-header">
        <div>
            <h1>Power outages</h1>
            <p class="subtitle">Outage management: planned and ongoing power interruptions by neighborhood</p>
        </div>
        <div class="toolbar">
            <a href="{{ route('admin.power-outages.create') }}" class="btn btn-primary"><x-icon name="plus" /> Create outage</a>
        </div>
    </div>

    <x-flash-messages />

    @include('partials.outage-map', [
        'mapId' => 'admin-outage-map',
        'history' => false,
        'mapHeading' => 'All outage locations',
        'mapDescription' => 'Explore every outage alert by its assigned neighborhoods.',
    ])

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Neighborhoods</th>
                    <th>Status</th>
                    <th>Severity</th>
                    <th>Reports</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($outages as $outage)
                    <tr>
                        <td><a href="{{ route('admin.power-outages.show', $outage) }}">{{ $outage->title }}</a></td>
                        <td class="wrap">{{ $outage->affectedAreasLabel() }}</td>
                        <td><span class="status-badge status-{{ $outage->status }}">{{ ucfirst(str_replace('_', ' ', $outage->status)) }}</span></td>
                        <td><span class="severity-pill severity-{{ $outage->severity }}">{{ ucfirst($outage->severity) }}</span></td>
                        <td>{{ $outage->reports_count }}</td>
                        <td class="actions">
                            <a href="{{ route('admin.power-outages.show', $outage) }}" class="icon-action" title="View" aria-label="View outage"><x-icon name="eye" /></a>
                            <a href="{{ route('admin.power-outages.edit', $outage) }}" class="icon-action is-edit" title="Edit" aria-label="Edit outage"><x-icon name="pencil" /></a>
                            <form method="POST" action="{{ route('admin.power-outages.destroy', $outage) }}" onsubmit="return confirm('Delete this outage and its reports?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-action is-danger" title="Delete" aria-label="Delete outage"><x-icon name="trash" /></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No outage has been created yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">
        {{ $outages->links() }}
    </div>
@endsection
