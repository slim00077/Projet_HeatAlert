@extends('layouts.front')

@section('title', 'HeatAlert - My quiz progress')

@section('content')
<section class="page-section">
    <div class="container">
        <div class="page-title">
            <div>
                <h1>My quiz progress</h1>
                <p>Review your results and see how your heat-safety knowledge is growing.</p>
            </div>
            <a href="{{ route('quizzes.index') }}" class="btn btn-primary">Take a quiz</a>
        </div>

        <section class="quiz-progress-card">
            <div><span class="chip">Current level</span><h2>{{ $progress['level'] }}</h2><p>{{ $progress['points'] }} total points</p></div>
            @if ($progress['next_level'])
                <p>{{ $progress['next_level'] - $progress['points'] }} points to the next level</p>
            @endif
        </section>

        <section class="quiz-history-section">
            <div class="quiz-analysis-heading">
                <div><p class="home-eyebrow">Your last {{ $recentAttempts->count() }} attempts</p><h2>Score progression</h2></div>
                @if ($scoreChange !== null)
                    <span class="quiz-score-change {{ $scoreChange >= 0 ? 'is-positive' : 'is-negative' }}">
                        {{ $scoreChange > 0 ? '+' : '' }}{{ $scoreChange }} points since your previous attempt
                    </span>
                @endif
            </div>
            @if ($recentAttempts->isNotEmpty())
                <div class="quiz-score-history">
                    @foreach ($recentAttempts as $attempt)
                        <article class="quiz-score-history-item">
                            <div class="quiz-score-history-bar"><span style="height: {{ max(4, $attempt->score) }}%"></span></div>
                            <strong>{{ $attempt->score }}%</strong>
                            <span>{{ $attempt->quiz->title }}</span>
                            <small>{{ $attempt->completed_at->format('d/m') }}</small>
                        </article>
                    @endforeach
                </div>
                <p class="quiz-history-note">Scores are shown from oldest to newest. Compare the latest attempt with the one before it to see your progress.</p>
            @else
                <p>Complete a quiz to start tracking your progress.</p>
            @endif
        </section>

        @if ($recurringDifficulties->isNotEmpty())
            <section class="quiz-history-section">
                <h2>Topics to revisit</h2>
                <p>These are topics you have answered incorrectly more than once in your latest attempts.</p>
                <div class="quiz-badges">
                    @foreach ($recurringDifficulties as $difficulty)
                        <span class="quiz-badge">{{ $difficulty['label'] }} · {{ $difficulty['count'] }} errors</span>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="quiz-history-section">
            <h2>Badges and achievements</h2>
            @if ($badges->isNotEmpty())
                <div class="quiz-badges">
                    @foreach ($badges as $badge)
                        <span class="quiz-badge" title="{{ $badge->description }}"><span>{{ $badge->icon }}</span>{{ $badge->name }}</span>
                    @endforeach
                </div>
            @else
                <p>Complete quizzes to unlock your first badge.</p>
            @endif
        </section>

        <section class="quiz-history-section">
            <h2>Completed quizzes</h2>
            <div class="table-card">
                <table class="data-table">
                    <thead><tr><th>Quiz</th><th>Date</th><th>Score</th><th>Points</th><th>Duration</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($attempts as $attempt)
                            <tr>
                                <td>{{ $attempt->quiz->title }}</td>
                                <td>{{ $attempt->completed_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $attempt->score }}% ({{ $attempt->correct_answers }}/{{ $attempt->total_questions }})</td>
                                <td>{{ $attempt->points_earned }}</td>
                                <td>{{ gmdate('i\m s\s', $attempt->duration_seconds) }}</td>
                                <td><a href="{{ route('quizzes.results', [$attempt->quiz, $attempt->public_id]) }}">Review</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No completed quizzes yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $attempts->links() }}
        </section>
    </div>
</section>
@endsection
