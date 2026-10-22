@extends('layouts.back')

@section('title', 'HeatAlert - Quiz Statistics')

@section('content')
<div class="page-header">
    <div><h1>Quiz Statistics</h1><p class="subtitle">See participation, topic performance and questions that may need clearer advice.</p></div>
</div>

<div class="dashboard-grid">
    <x-stat-card label="Completed attempts" :value="$completedAttempts" icon="check" tone="coral" />
    <x-stat-card label="Registered participants" :value="$participants" icon="users" tone="blue" />
    <x-stat-card label="Quiz topics" :value="$quizzes->count()" icon="grid" tone="green" />
</div>

<section class="quiz-history-section">
    <h2>Results by topic</h2>
    <div class="table-card">
        <table class="data-table">
            <thead><tr><th>Topic</th><th>Questions</th><th>Completed attempts</th><th>Average score</th></tr></thead>
            <tbody>
                @foreach ($quizzes as $quiz)
                    @php($topicStat = $topicStats->get($quiz->id))
                    <tr>
                        <td>{{ $quiz->title }}</td>
                        <td>{{ $quiz->questions_count }}</td>
                        <td>{{ $topicStat?->attempts_count ?? 0 }}</td>
                        <td>{{ $topicStat ? round($topicStat->average_score).'%' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="quiz-history-section">
    <h2>Most-missed questions</h2>
    <div class="table-card">
        <table class="data-table">
            <thead><tr><th>Question</th><th>Quiz</th><th>Incorrect answers</th></tr></thead>
            <tbody>
                @forelse ($commonlyMissed->where('incorrect_answers_count', '>', 0) as $question)
                    <tr>
                        <td class="wrap">{{ $question->prompt }}</td>
                        <td>{{ $question->quiz->title }}</td>
                        <td>{{ $question->incorrect_answers_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">No quiz answers have been recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
