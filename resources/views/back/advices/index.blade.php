@extends('layouts.back')

@section('title', 'HeatAlert - Manage Advice')

@section('content')

<div class="page-header">
    <div>
        <h1>Advice</h1>
        <p class="subtitle">Manage public heat-safety advice.</p>
    </div>
    <div class="toolbar">
        <form action="{{ route('admin.advices.index') }}" method="GET" class="search-form advice-toolbar">
            <input type="search" name="search" value="{{ $search }}" placeholder="Search advice...">
            <select name="category" aria-label="Filter by category">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['category'] ?? '') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-outline" aria-label="Search"><x-icon name="search" /></button>
        </form>
        <a href="{{ route('admin.advices.create') }}" class="btn btn-primary"><x-icon name="plus" /> Add Advice</a>
    </div>
</div>

<x-flash-messages />

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Summary</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($advices as $advice)
                <tr>
                    <td><a href="{{ route('admin.advices.show', $advice) }}">{{ $advice->title }}</a></td>
                    <td>{{ $advice->category->name }}</td>
                    <td class="wrap">{{ \Illuminate\Support\Str::limit($advice->summary ?? $advice->content, 100) }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.advices.show', $advice) }}" class="icon-action" title="View"><x-icon name="eye" /></a>
                        <a href="{{ route('admin.advices.edit', $advice) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                        <form action="{{ route('admin.advices.destroy', $advice) }}" method="POST" onsubmit="return confirm('Delete this advice?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No advice found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $advices->links() }}

@endsection
