@extends('layouts.front')

@section('title', 'HeatAlert - ' . $quiz->title)

@section('content')
<section class="fx-page page-section">
    <div class="container quiz-play-wrap">
        <div class="quiz-play-heading">
            <a href="{{ route('quizzes.show', $quiz) }}" class="card-link">Exit quiz</a>
            <h1>{{ $quiz->title }}</h1>
            <p>Question {{ $questionNumber }} of {{ $totalQuestions }} <span>·</span> {{ $score }} points</p>
            <div class="quiz-progress-track" role="progressbar" aria-label="Quiz progress" aria-valuenow="{{ $answered ? $questionNumber : $questionNumber - 1 }}" aria-valuemin="0" aria-valuemax="{{ $totalQuestions }}">
                <span style="width: {{ ($answered ? $questionNumber : $questionNumber - 1) / $totalQuestions * 100 }}%"></span>
            </div>
        </div>

        @if ($feedback)
            <div class="quiz-feedback {{ $feedback['is_correct'] ? 'is-correct' : 'is-incorrect' }}" role="status" aria-live="polite">
                <strong>{{ $feedback['is_correct'] ? 'Correct! +10 points' : 'Not quite — let’s review this answer.' }}</strong>
                <p><strong>Your answer:</strong> {{ $feedback['selected_answer'] }}</p>
                <p><strong>Correct answer:</strong> {{ $feedback['correct_answer'] }}</p>
                @unless ($feedback['is_correct'])
                    <p>Your choice is not the safest response to this situation. {{ $feedback['explanation'] }}</p>
                @else
                    <p>{{ $feedback['explanation'] }}</p>
                @endunless
                <p class="quiz-response-time">You answered in {{ $feedback['response_seconds'] }} seconds.</p>
            </div>
        @endif

        <article class="quiz-question-card">
            <span class="home-eyebrow">Question {{ $questionNumber }}</span>
            <h2>{{ $question['prompt'] }}</h2>
            @if ($answered)
                <fieldset class="quiz-options quiz-options-locked" disabled>
                    <legend class="sr-only">Your submitted answer</legend>
                    @foreach ($question['options'] as $optionIndex => $option)
                        <label class="quiz-option {{ $optionIndex === $feedback['correct_display_option'] ? 'is-answer-correct' : '' }} {{ $optionIndex === $feedback['selected_display_option'] && ! $feedback['is_correct'] ? 'is-answer-incorrect' : '' }}">
                            <input type="radio" name="selected_option" value="{{ $optionIndex }}" @checked($optionIndex === $feedback['selected_display_option'])>
                            <span class="quiz-option-letter">{{ chr(65 + $optionIndex) }}</span>
                            <span>{{ $option }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <form action="{{ route('quizzes.next', [$quiz, $runId]) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-block">
                        {{ $questionNumber === $totalQuestions ? 'View my results' : 'Continue to the next question' }}
                    </button>
                </form>
            @else
                <form action="{{ route('quizzes.answer', [$quiz, $runId]) }}" method="POST">
                    @csrf
                    <input type="hidden" name="question_id" value="{{ $question['id'] }}">
                    <fieldset class="quiz-options">
                        <legend class="sr-only">Choose one answer</legend>
                        @foreach ($question['options'] as $optionIndex => $option)
                            <label class="quiz-option">
                                <input type="radio" name="selected_option" value="{{ $optionIndex }}" required>
                                <span class="quiz-option-letter">{{ chr(65 + $optionIndex) }}</span>
                                <span>{{ $option }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                    @error('selected_option')<p class="field-error">{{ $message }}</p>@enderror
                    @error('question_id')<p class="field-error">{{ $message }}</p>@enderror
                    <button type="submit" class="btn btn-primary btn-block">Validate my answer</button>
                </form>
            @endif
        </article>
    </div>
</section>
@endsection
