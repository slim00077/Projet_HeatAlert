@extends('layouts.back')

@section('title', 'HeatAlert - Manage Advice')

@section('content')

<div class="page-header">
    <div>
        <h1>Advice</h1>
        <p class="subtitle">Review submissions and manage published heat-safety advice. {{ $pendingAdviceCount }} pending.</p>
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
            <select name="approval" aria-label="Filter by approval status">
                <option value="">All advice</option>
                <option value="pending" @selected(($filters['approval'] ?? '') === 'pending')>Pending review</option>
                <option value="approved" @selected(($filters['approval'] ?? '') === 'approved')>Published</option>
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
                <th>Risk</th>
                <th>Status</th>
                <th>Summary</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($advices as $advice)
                <tr>
                    <td><a href="{{ route('admin.advices.show', $advice) }}">{{ $advice->title }}</a></td>
                    <td>{{ $advice->category->name }}</td>
                    <td><x-severity-badge :severity="$advice->risk_level" /></td>
                    <td>
                        @if ($advice->is_approved)
                            <span class="chip">Published</span>
                        @else
                            <span class="chip approval-pending">Pending review</span>
                        @endif
                    </td>
                    <td class="wrap">{{ \Illuminate\Support\Str::limit($advice->summary ?? $advice->content, 100) }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.advices.show', $advice) }}" class="icon-action" title="View"><x-icon name="eye" /></a>
                        <a href="{{ route('admin.advices.edit', $advice) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                        @unless ($advice->is_approved)
                            <form action="{{ route('admin.advices.approve', $advice) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                            </form>
                        @endunless
                        <form action="{{ route('admin.advices.destroy', $advice) }}" method="POST" onsubmit="return confirm('Delete this advice?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-action is-danger" title="Delete"><x-icon name="trash" /></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No advice found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $advices->links() }}

@endsection
