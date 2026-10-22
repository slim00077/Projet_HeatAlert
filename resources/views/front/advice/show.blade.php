@extends('layouts.front')

@section('title', 'HeatAlert - ' . $advice->title)

@section('content')

<section class="fx-page page-section">
    <div class="container">
        <article class="advice-detail">
            <a href="{{ route('advice.index', ['category' => $advice->advice_category_id]) }}" class="chip">{{ $advice->category->name }}</a>
            <h1>{{ $advice->title }}</h1>
            @if ($advice->summary)
                <p class="advice-summary">{{ $advice->summary }}</p>
            @endif
            <div class="advice-content">{{ $advice->content }}</div>
            @if ($advice->source_url)
                <p class="advice-source">
                    <strong>Submitted reference:</strong>
                    <a href="{{ $advice->source_url }}" target="_blank" rel="noopener noreferrer">{{ $advice->source_url }}</a>
                    <small>This source link is provided for reference and is not independently verified.</small>
                </p>
            @endif
            <a href="{{ route('advice.index') }}" class="btn btn-outline"><x-icon name="arrow" /> Back to advice</a>
        </article>

        @if ($relatedAdvices->isNotEmpty())
            <section class="related-advice" aria-labelledby="related-advice-heading">
                <h2 id="related-advice-heading">More in {{ $advice->category->name }}</h2>
                <div class="advice-grid">
                    @foreach ($relatedAdvices as $relatedAdvice)
                        <article class="advice-card">
                            <h3>{{ $relatedAdvice->title }}</h3>
                            <p class="advice-summary">{{ \Illuminate\Support\Str::limit($relatedAdvice->summary ?? $relatedAdvice->content, 140) }}</p>
                            <a href="{{ route('advice.show', $relatedAdvice) }}" class="card-link">Read advice <x-icon name="arrow" /></a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</section>

@endsection
