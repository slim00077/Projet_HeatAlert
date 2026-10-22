@extends('layouts.front')

@section('title', 'HeatAlert - Heatwave Challenge')

@section('content')
<section class="page-section">
    <div class="container">
        <div class="page-title">
            <div>
                <span class="home-eyebrow">Learn by playing</span>
                <h1>HeatAlert Challenge</h1>
                <p>Test what you know about heat safety. Get instant explanations and practical advice.</p>
            </div>
            @auth
                <a href="{{ route('quizzes.history') }}" class="btn btn-outline">My progress and history</a>
            @endauth
        </div>

        @if ($progress)
            <section class="quiz-progress-card">
                <div>
                    <span class="chip">Your level</span>
                    <h2>{{ $progress['level'] }}</h2>
                    <p>{{ $progress['points'] }} points earned across completed quizzes.</p>
                </div>
                @if ($progress['next_level'])
                    <p>{{ $progress['next_level'] - $progress['points'] }} points to the next level</p>
                @endif
            </section>

            @if ($badges->isNotEmpty())
                <section class="quiz-badges" aria-label="Your badges">
                    @foreach ($badges as $badge)
                        <span class="quiz-badge" title="{{ $badge->description }}"><span>{{ $badge->icon }}</span>{{ $badge->name }}</span>
                    @endforeach
                </section>
            @endif
        @else
            <p class="flash flash-info">Play without an account, or sign in to save your history, collect points and earn badges.</p>
        @endif

        <div class="quiz-grid">
            @foreach ($quizzes as $quiz)
                <article class="quiz-card">
                    <span class="quiz-icon" aria-hidden="true">{{ $quiz->icon }}</span>
                    <p class="home-eyebrow">Quiz {{ str_pad((string) $quiz->position, 2, '0', STR_PAD_LEFT) }}</p>
                    <h2>{{ $quiz->title }}</h2>
                    <p>{{ $quiz->description }}</p>
                    <div class="quiz-card-meta">{{ $quiz->questions_count }} questions in the bank <span>·</span> 10 per attempt</div>
                    <a href="{{ route('quizzes.show', $quiz) }}" class="btn btn-primary">Choose this quiz</a>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endsection
