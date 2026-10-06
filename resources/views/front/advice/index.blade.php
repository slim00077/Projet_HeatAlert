@extends('layouts.front')

@section('title', 'HeatAlert - Practical Advice')

@section('content')

<section class="page-section">
    <div class="container">
        <div class="page-title">
            <div>
                <h1>Practical Advice</h1>
                <p>Simple ways to stay safe during hot weather.</p>
            </div>
            <form action="{{ route('advice.index') }}" method="GET" class="toolbar advice-toolbar">
                <div class="search-form">
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search advice..." aria-label="Search advice">
                    <button type="submit" class="btn btn-primary" aria-label="Search"><x-icon name="search" /></button>
                </div>
                <select name="category" aria-label="Filter by category" onchange="this.form.requestSubmit()">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(($filters['category'] ?? '') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <nav class="filter-tabs" aria-label="Advice categories">
            <a href="{{ route('advice.index', request()->except('category', 'page')) }}" @class(['is-active' => ! isset($filters['category'])])>All advice</a>
            @foreach ($categories as $category)
                <a href="{{ route('advice.index', array_merge(request()->except('page'), ['category' => $category->id])) }}"
                   @class(['is-active' => ($filters['category'] ?? null) == $category->id])>
                    {{ $category->name }} <span>{{ $category->advices_count }}</span>
                </a>
            @endforeach
        </nav>

        <div class="advice-grid">
            @forelse ($advices as $advice)
                <article class="advice-card">
                    <span class="chip">{{ $advice->category->name }}</span>
                    <h2>{{ $advice->title }}</h2>
                    <p class="advice-summary">{{ \Illuminate\Support\Str::limit($advice->summary ?? $advice->content, 180) }}</p>
                    <a href="{{ route('advice.show', $advice) }}" class="card-link">Read advice <x-icon name="arrow" /></a>
                </article>
            @empty
                <div class="empty-state">
                    <p>No advice matches your search. Try another word or category.</p>
                    <a href="{{ route('advice.index') }}" class="btn btn-outline">Clear filters</a>
                </div>
            @endforelse
        </div>

        {{ $advices->links() }}
    </div>
</section>

@endsection
