@extends('layouts.front')

@section('title', 'HeatAlert - ' . $quiz->title)

@section('content')
<section class="fx-page page-section">
    <div class="container">
        <article class="quiz-detail-card">
            <a href="{{ route('quizzes.index') }}" class="card-link">← All quizzes</a>
            <span class="quiz-icon" aria-hidden="true">{{ $quiz->icon }}</span>
            <h1>{{ $quiz->title }}</h1>
            <p>{{ $quiz->description }}</p>
            <ul class="quiz-facts">
                <li>{{ $quiz->questions_count }} questions in the bank · 10 questions per attempt</li>
                <li>Instant correction and explanation after each answer</li>
                <li>10 points for every correct answer</li>
                <li>Missed questions can be practised again at the end</li>
            </ul>
            @if ($bestAttempt)
                <p class="quiz-best-score">Your best score: <strong>{{ $bestAttempt->score }}%</strong> · {{ $bestAttempt->correct_answers }}/{{ $bestAttempt->total_questions }} correct</p>
            @endif
            @error('quiz')<p class="field-error">{{ $message }}</p>@enderror
            <form action="{{ route('quizzes.start', $quiz) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary btn-large">Start the quiz</button>
            </form>
        </article>

        @if ($recentAttempts->isNotEmpty())
            <section class="quiz-recent">
                <h2>Your recent attempts</h2>
                <ul>
                    @foreach ($recentAttempts as $attempt)
                        <li><span>{{ $attempt->completed_at->format('d/m/Y H:i') }}</span><strong>{{ $attempt->score }}%</strong><span>{{ $attempt->correct_answers }}/{{ $attempt->total_questions }} correct</span></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</section>
@endsection
