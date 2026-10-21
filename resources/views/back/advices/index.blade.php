@extends('layouts.back')

@section('title', 'HeatAlert - Manage Advice')

@section('content')

<div class="page-header advice-admin-header">
    <div>
        <p class="home-eyebrow">Content moderation</p>
        <h1>Advice management</h1>
        <p class="subtitle">Review community submissions, publish trusted guidance, and manage the advice library.</p>
    </div>
    <a href="{{ route('admin.advices.create') }}" class="btn btn-primary"><x-icon name="plus" /> Create advice</a>
</div>

<x-flash-messages />

<section class="advice-admin-stats" aria-label="Advice overview">
    <article class="advice-admin-stat is-pending"><div><p>Needs review</p><strong>{{ $pendingAdviceCount }}</strong><small>Pending or changes requested</small></div></article>
    <article class="advice-admin-stat is-published"><div><p>Published</p><strong>{{ $statusCounts->get('approved', 0) }}</strong><small>Visible in the advice library</small></div></article>
    <article class="advice-admin-stat is-rejected"><div><p>Rejected</p><strong>{{ $statusCounts->get('rejected', 0) }}</strong><small>Submissions declined</small></div></article>
</section>

<section class="advice-admin-queue">
    <div class="advice-admin-queue-heading">
        <div><h2>Advice library</h2><p>Search, filter, and take action on advice submissions.</p></div>
        <span class="advice-admin-result-count">{{ $advices->total() }} {{ \Illuminate\Support\Str::plural('item', $advices->total()) }}</span>
    </div>
    <form action="{{ route('admin.advices.index') }}" method="GET" class="advice-admin-filters">
            <label class="advice-admin-search"><span class="sr-only">Search advice</span><x-icon name="search" /><input type="search" name="search" value="{{ $search }}" placeholder="Search by title or category..."></label>
            <select name="category" aria-label="Filter by category">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['category'] ?? '') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="approval" aria-label="Filter by approval status">
                <option value="">All statuses</option>
                <option value="pending" @selected(($filters['approval'] ?? '') === 'pending')>Pending review</option>
                <option value="approved" @selected(($filters['approval'] ?? '') === 'approved')>Published</option>
                <option value="changes_requested" @selected(($filters['approval'] ?? '') === 'changes_requested')>Changes requested</option>
                <option value="rejected" @selected(($filters['approval'] ?? '') === 'rejected')>Rejected</option>
            </select>
            <button type="submit" class="btn btn-primary">Apply filters</button>
            @if ($search !== '' || isset($filters['category']) || isset($filters['approval']))
                <a href="{{ route('admin.advices.index') }}" class="btn btn-outline">Clear</a>
            @endif
    </form>

<div class="table-card advice-admin-table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Advice</th>
                <th>Submitted by</th>
                <th>Category</th>
                <th>Risk</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($advices as $advice)
                <tr>
                    <td class="advice-admin-title-cell"><a href="{{ route('admin.advices.show', $advice) }}">{{ $advice->title }}</a><small>{{ \Illuminate\Support\Str::limit($advice->summary ?? $advice->content, 90) }}</small></td>
                    <td>{{ $advice->submitter?->name ?? 'Admin' }}</td>
                    <td>{{ $advice->category->name }}</td>
                    <td><x-severity-badge :severity="$advice->risk_level" /></td>
                    <td>
                        <span @class(['chip', 'advice-status-chip', 'is-pending' => in_array($advice->moderation_status, ['pending', 'changes_requested'], true), 'is-approved' => $advice->moderation_status === 'approved', 'is-rejected' => $advice->moderation_status === 'rejected'])>
                            {{ \Illuminate\Support\Str::headline($advice->moderation_status) }}
                        </span>
                    </td>
                    <td>{{ $advice->created_at->format('M j, Y') }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.advices.show', $advice) }}" class="btn btn-outline btn-sm">Review</a>
                        @if (in_array($advice->moderation_status, ['pending', 'changes_requested'], true))
                            <form action="{{ route('admin.advices.approve', $advice) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                            </form>
                        @endif
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
                    <td colspan="7"><div class="advice-admin-empty"><strong>No advice matches these filters</strong><span>Try another search or clear the filters to see all submissions.</span></div></td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>
    {{ $advices->links() }}
</section>

@endsection
