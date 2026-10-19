@extends('layouts.back')

@section('title', 'HeatAlert - Advice Categories')

@section('content')

<div class="page-header">
    <div>
        <h1>Advice Categories</h1>
        <p class="subtitle">Group related heat-safety advice.</p>
    </div>
    <div class="toolbar">
        <form action="{{ route('admin.advice-categories.index') }}" method="GET" class="search-form">
            <input type="search" name="search" value="{{ $search }}" placeholder="Search categories...">
            <button type="submit" class="btn btn-outline" aria-label="Search"><x-icon name="search" /></button>
        </form>
        <a href="{{ route('admin.advice-categories.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Category</a>
    </div>
</div>

<x-flash-messages />

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th>Description</th>
                <th>Advice</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td><a href="{{ route('admin.advice-categories.show', $category) }}">{{ $category->name }}</a></td>
                    <td class="wrap">{{ $category->description ?: '—' }}</td>
                    <td>{{ $category->advices_count }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.advice-categories.show', $category) }}" class="icon-action" title="View"><x-icon name="eye" /></a>
                        <a href="{{ route('admin.advice-categories.edit', $category) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                        <form action="{{ route('admin.advice-categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Delete this category? Categories that contain advice cannot be deleted.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No advice categories found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $categories->links() }}

@endsection
