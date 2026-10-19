@extends('layouts.back')

@section('title', 'Power Outages - Admin')

@section('content')
    <div class="page-shell">
        <div class="page-header">
            <div>
                <span class="eyebrow">Power outages</span>
                <h1>Outage management</h1>
            </div>
            <a href="{{ route('admin.power-outages.create') }}" class="btn btn-primary">Create outage</a>
        </div>

        @if (session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif

        @include('partials.outage-map', [
            'mapId' => 'admin-outage-map',
            'history' => false,
            'mapHeading' => 'All outage locations',
            'mapDescription' => 'Explore every outage alert by its assigned neighborhoods.',
        ])

        <div class="table-card">
            <table>
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
                            <td>{{ $outage->title }}</td>
                            <td>{{ $outage->affectedAreasLabel() }}</td>
                            <td><span class="status-badge status-{{ $outage->status }}">{{ ucfirst(str_replace('_', ' ', $outage->status)) }}</span></td>
                            <td><span class="severity-pill severity-{{ $outage->severity }}">{{ ucfirst($outage->severity) }}</span></td>
                            <td>{{ $outage->reports_count }}</td>
                            <td class="table-actions">
                                <a href="{{ route('admin.power-outages.show', $outage) }}">View</a>
                                <a href="{{ route('admin.power-outages.edit', $outage) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.power-outages.destroy', $outage) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">No outage has been created yet.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $outages->links() }}
        </div>
    </div>
@endsection
