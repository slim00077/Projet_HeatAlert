@extends('layouts.back')

@section('title', 'HeatAlert - Cooling Types')

@section('content')

<div class="page-header">
    <div>
        <h1>Cooling Types</h1>
        <p class="subtitle">Categories of cooling points</p>
    </div>
    <div class="toolbar">
        <a href="{{ route('admin.cool-spot-types.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Type</a>
    </div>
</div>

<x-flash-messages />

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Description</th>
                <th>Cooling Points</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($types as $type)
                <tr>
                    <td>{{ $types->firstItem() + $loop->index }}</td>
                    <td>{{ $type->name }}</td>
                    <td class="wrap">{{ $type->description ?? '—' }}</td>
                    <td><span class="chip">{{ $type->cool_spots_count }} point(s)</span></td>
                    <td class="actions">
                        <a href="{{ route('admin.cool-spot-types.edit', $type) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                        <form action="{{ route('admin.cool-spot-types.destroy', $type) }}" method="POST" onsubmit="return confirm('Delete this type?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No types found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top: 1.2rem">
    {{ $types->links() }}
</div>

@endsection