@extends('layouts.back')

@section('title', 'HeatAlert - Residences')

@section('content')

<div class="page-header">
    <div>
        <h1>Residences</h1>
        <p class="subtitle">Manage all residences</p>
    </div>
    <div class="toolbar">
        <form action="{{ route('residences.index') }}" method="GET" class="search-form">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, address or neighborhood...">
            <button type="submit" class="btn btn-outline" aria-label="Search"><x-icon name="search" /></button>
        </form>
        <a href="{{ route('residences.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Residence</a>
    </div>
</div>

<x-flash-messages />

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Address</th>
                <th>Neighborhood</th>
                <th>Reports</th>
                <th>Owner</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($residences as $residence)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><a href="{{ route('residences.show', $residence) }}">{{ $residence->name }}</a></td>
                    <td>{{ $residence->address }}</td>
                    <td>{{ $residence->neighborhood }}</td>
                    <td>{{ $residence->resident_reports_count }}</td>
                    <td>{{ $residence->user->name }}</td>
                    <td class="actions">
                        <a href="{{ route('residences.show', $residence) }}" class="icon-action" title="View"><x-icon name="eye" /></a>
                        <a href="{{ route('residences.edit', $residence) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                        <form action="{{ route('residences.destroy', $residence) }}" method="POST" onsubmit="return confirm('Delete this residence and its reports?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No residences found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
