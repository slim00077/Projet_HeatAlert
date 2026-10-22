@extends('layouts.front')

@section('title', 'HeatAlert - Quiz results')

@section('content')
<section class="page-section">
    <div class="container">
        <x-flash-messages />
        <header class="quiz-result-header">
            <span class="quiz-icon" aria-hidden="true">{{ $quiz->icon }}</span>
            <p class="home-eyebrow">Challenge complete</p>
            <h1>{{ $quiz->title }}</h1>
            <div class="quiz-score-circle"><strong>{{ $score }}%</strong><span>{{ $correctAnswers }}/{{ count($answers) }} correct</span></div>
            <div class="quiz-score-summary">
                <span><strong>{{ $correctAnswers }}</strong> correct</span>
                <span><strong>{{ $incorrectAnswers }}</strong> incorrect</span>
                <span><strong>{{ $correctAnswers }}/{{ count($answers) }}</strong> correct this round</span>
            </div>
            <p class="quiz-result-summary">You earned <strong>{{ $pointsEarned }} points</strong> in {{ gmdate('i\m s\s', $durationSeconds) }}.</p>
            @if ($level)
                <p>Current level: <strong>{{ $level }}</strong></p>
            @else
                <p><a href="{{ route('register') }}">Create an account</a> to save your history, earn badges and keep your points.</p>
            @endif
        </header>

        <section class="quiz-analysis-card">
            <div class="quiz-analysis-heading">
                <div>
                    <p class="home-eyebrow">Based on your answers</p>
                    <h2>Your learning summary</h2>
                </div>
                <span class="quiz-analysis-count">{{ count($answers) }} answers analysed</span>
            </div>

            @if ($masteredCategories->isNotEmpty())
                <div class="quiz-insight is-mastered">
                    <strong>What you know well</strong>
                    <p>{{ $masteredCategories->pluck('name')->join(', ') }}. We have not included these areas in your recommendations.</p>
                </div>
            @else
                <div class="quiz-insight">
                    <strong>Keep building your knowledge</strong>
                    <p>Use the explanations and approved advice below to reinforce the topics covered in this quiz.</p>
                </div>
            @endif

            @if ($needsWork->isNotEmpty())
                <div class="quiz-insight is-review">
                    <strong>Topics to review</strong>
                    <p>{{ $needsWork->pluck('name')->join(', ') }}. Recommendations are prioritised by your errors
                        @if ($recommendations->contains(fn (array $recommendation) => $recommendation['historical_misses'] > 0))
                            and recurring mistakes from your recent attempts.
                        @else
                            in this attempt.
                        @endif
                    </p>
                </div>
            @endif

            <div class="quiz-category-results">
                @foreach ($categoryResults as $category)
                    <article class="quiz-category-result">
                        <div><strong>{{ $category['name'] }}</strong><span>{{ $category['correct'] }}/{{ $category['total'] }} correct · {{ $category['percentage'] }}%</span></div>
                        <div class="quiz-category-track" role="progressbar" aria-label="{{ $category['name'] }} result" aria-valuenow="{{ $category['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                            <span style="width: {{ $category['percentage'] }}%"></span>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($categoryResults->count() > 1)
                <div class="quiz-strength-summary">
                    <p><strong>Strongest result:</strong> {{ $strongestCategories->pluck('name')->join(', ') }} ({{ $bestPercentage }}%).</p>
                    <p><strong>Most difficult:</strong> {{ $weakestCategories->pluck('name')->join(', ') }} ({{ $lowestPercentage }}%).</p>
                </div>
            @else
                <p class="quiz-history-note">This quiz focused on {{ $categoryResults->first()['name'] }}; your score above shows your result in that area.</p>
            @endif
        </section>

        @if ($newBadges->isNotEmpty())
            <section class="quiz-new-badges">
                <h2>Badge{{ $newBadges->count() > 1 ? 's' : '' }} unlocked!</h2>
                <div class="quiz-badges">
                    @foreach ($newBadges as $badge)
                        <span class="quiz-badge"><span>{{ $badge->icon }}</span>{{ $badge->name }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($recommendations->isNotEmpty())
            <section class="quiz-recommendations" id="quiz-recommendations">
                <div class="quiz-analysis-heading">
                    <div><p class="home-eyebrow">Personalised next steps</p><h2>Approved advice to review</h2></div>
                    <span class="quiz-analysis-count">Prioritised for you</span>
                </div>
                <p>Advice is selected only for categories where your result shows room to improve. Repeated errors from your history raise a category's priority.</p>
                <div class="quiz-recommendation-list">
                    @foreach ($recommendations as $recommendation)
                        <article class="quiz-recommendation-group">
                            <div class="quiz-recommendation-priority">
                                <strong>{{ $recommendation['category'] }}</strong>
                                <span>{{ $recommendation['incorrect'] }} mistake{{ $recommendation['incorrect'] === 1 ? '' : 's' }} in this quiz
                                    @if ($recommendation['historical_misses'] > 0)
                                        · {{ $recommendation['historical_misses'] }} recent repeated error{{ $recommendation['historical_misses'] === 1 ? '' : 's' }}
                                    @endif
                                </span>
                            </div>
                            @forelse ($recommendation['advices'] as $advice)
                                <a href="{{ route('advice.show', $advice) }}"><strong>{{ $advice->title }}</strong><span>Read approved advice →</span></a>
                            @empty
                                <p>No approved advice is available for this category yet. Browse the full <a href="{{ route('advice.index') }}">advice catalogue</a>.</p>
                            @endforelse
                            @if ($recommendation['advices']->isNotEmpty())
                                <a class="quiz-category-link" href="{{ route('advice.index', ['category' => $recommendation['advices']->first()->advice_category_id]) }}">View all {{ $recommendation['category'] }} advice →</a>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="quiz-answer-review">
            <h2>Review your answers</h2>
            @foreach ($answers as $index => $answer)
                <article class="quiz-review-answer {{ $answer['is_correct'] ? 'is-correct' : 'is-incorrect' }}">
                    <div class="quiz-review-heading">
                        <h3>{{ $index + 1 }}. {{ $answer['question']->prompt }}</h3>
                        <span>{{ $answer['is_correct'] ? 'Correct' : 'Review this' }} · {{ $topicResults->get($answer['topic'])['label'] }}</span>
                    </div>
                    <p><strong>Your answer:</strong> {{ $answer['question']->options[$answer['selected_option']] }}</p>
                    @unless ($answer['is_correct'])
                        <p><strong>Correct answer:</strong> {{ $answer['question']->options[$answer['correct_option']] }}</p>
                    @endunless
                    <p>{{ $answer['explanation'] }}</p>
                    <span class="quiz-response-time">Response time: {{ $answer['response_seconds'] ?? 0 }} seconds</span>
                </article>
            @endforeach
        </section>

        <div class="quiz-result-actions">
            @if ($incorrectTopics->isNotEmpty())
                <form action="{{ route('quizzes.review', [$quiz, $publicId]) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline">Practise my mistakes ({{ $incorrectAnswers }})</button>
                </form>
            @endif
            @if ($recommendations->isNotEmpty())
                <a href="#quiz-recommendations" class="btn btn-outline">View recommended advice</a>
            @endif
            <form action="{{ route('quizzes.start', $quiz) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary">Restart with new questions</button>
            </form>
            <a href="{{ route('quizzes.index') }}" class="btn btn-outline">Choose another quiz</a>
            @auth
                <a href="{{ route('quizzes.history') }}" class="btn btn-outline">My history</a>
            @endauth
        </div>
    </div>
</section>
@endsection
