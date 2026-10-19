<?php

namespace App\Http\Controllers;

use App\Models\Advice;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\QuizChallengeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class QuizChallengeController extends Controller
{
    public function index(): View
    {
        $quizzes = Quiz::query()
            ->withCount('questions')
            ->orderBy('position')
            ->get();
        $progress = auth()->user() ? $this->progress(auth()->user()) : null;
        $badges = auth()->user()?->quizBadges()->orderByPivot('awarded_at', 'desc')->get() ?? collect();

        return view('front.quizzes.index', compact('quizzes', 'progress', 'badges'));
    }

    public function show(Quiz $quiz): View
    {
        $quiz->loadCount('questions');
        $bestAttempt = auth()->user()?->quizAttempts()
            ->where('quiz_id', $quiz->id)
            ->orderByDesc('score')
            ->first();
        $recentAttempts = auth()->user()?->quizAttempts()
            ->where('quiz_id', $quiz->id)
            ->latest('completed_at')
            ->limit(5)
            ->get() ?? collect();

        return view('front.quizzes.show', compact('quiz', 'bestAttempt', 'recentAttempts'));
    }

    public function start(Quiz $quiz, QuizChallengeService $challenge): RedirectResponse
    {
        $seenIds = [];
        $user = request()->user();

        if ($user !== null) {
            $seenIds = QuizAttemptAnswer::query()
                ->whereHas('attempt', fn (Builder $query) => $query->whereBelongsTo($user)->where('quiz_id', $quiz->id))
                ->whereHas('question', fn (Builder $query) => $query->where('quiz_id', $quiz->id))
                ->distinct()
                ->pluck('quiz_question_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $seenIds = array_values(array_unique(array_merge(
            $seenIds,
            array_map('intval', session()->get("quiz_seen.{$quiz->id}", [])),
        )));

        $unseenQuery = $quiz->questions();
        if ($seenIds !== []) {
            $unseenQuery->whereNotIn('id', $seenIds);
        }

        $questionIds = $unseenQuery->inRandomOrder()->limit(10)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($questionIds) < 10 && $seenIds !== []) {
            $questionIds = array_merge(
                $questionIds,
                $quiz->questions()
                    ->whereIn('id', $seenIds)
                    ->whereNotIn('id', $questionIds)
                    ->inRandomOrder()
                    ->limit(10 - count($questionIds))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            );
        }

        if (count($questionIds) < 10) {
            $message = $quiz->questions()->count() === 0
                ? 'This quiz does not have any questions yet.'
                : 'This quiz needs at least 10 questions before it can be played.';

            return back()->withErrors(['quiz' => $message]);
        }

        shuffle($questionIds);
        session()->put("quiz_seen.{$quiz->id}", array_values(array_unique(array_merge($seenIds, $questionIds))));
        $runId = $challenge->start($quiz, $questionIds);

        return redirect()->route('quizzes.play', [$quiz, $runId]);
    }

    public function play(Quiz $quiz, string $run, QuizChallengeService $challenge): View|RedirectResponse
    {
        $state = $challenge->run($quiz, $run);

        if ($state['completed']) {
            return redirect()->route('quizzes.results', [$quiz, $run]);
        }

        $question = QuizQuestion::query()
            ->where('quiz_id', $quiz->id)
            ->findOrFail($state['question_ids'][$state['position']]);
        $optionOrder = $state['option_orders'][$question->id];
        $displayOptions = array_map(
            fn (int $optionIndex) => $question->options[$optionIndex],
            $optionOrder,
        );
        $feedback = $state['pending_feedback'];

        return view('front.quizzes.play', [
            'quiz' => $quiz,
            'runId' => $run,
            'question' => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'options' => $feedback['display_options'] ?? $displayOptions,
            ],
            'questionNumber' => $state['position'] + 1,
            'totalQuestions' => count($state['question_ids']),
            'score' => $state['score'],
            'feedback' => $feedback,
            'answered' => $state['awaiting_next'],
        ]);
    }

    public function answer(Request $request, Quiz $quiz, string $run, QuizChallengeService $challenge): RedirectResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:quiz_questions,id'],
            'selected_option' => ['required', 'integer', 'min:0'],
        ]);

        $challenge->answer($quiz, $run, (int) $data['question_id'], (int) $data['selected_option']);

        return redirect()->route('quizzes.play', [$quiz, $run]);
    }

    public function next(Quiz $quiz, string $run, QuizChallengeService $challenge): RedirectResponse
    {
        if ($challenge->continue($quiz, $run)) {
            return redirect()->route('quizzes.results', [$quiz, $run]);
        }

        return redirect()->route('quizzes.play', [$quiz, $run]);
    }

    public function result(Quiz $quiz, string $run): View
    {
        $state = session()->get("quiz_runs.{$run}");
        $attempt = null;

        if (is_array($state) && $state['quiz_id'] === $quiz->id && $state['completed']) {
            if (auth()->user() && ($state['attempt_id'] ?? null) !== null) {
                $attempt = auth()->user()->quizAttempts()
                    ->with(['answers.question'])
                    ->whereKey($state['attempt_id'])
                    ->firstOrFail();
            }

            $answers = $this->answersForRun($state);
            $correctAnswers = $answers->where('is_correct', true)->count();
            $score = (int) round($correctAnswers / count($state['question_ids']) * 100);
            $pointsEarned = $state['score'];
            $durationSeconds = max(0, $state['completed_at'] - $state['started_at']);
            $publicId = $run;
        } else {
            $attempt = auth()->user()?->quizAttempts()
                ->with(['answers.question'])
                ->where('public_id', $run)
                ->where('quiz_id', $quiz->id)
                ->firstOrFail();
            abort_unless($attempt !== null, 404);

            $answers = $attempt->answers->map(fn ($answer) => [
                'question' => $answer->question,
                'selected_option' => $answer->selected_option,
                'is_correct' => $answer->is_correct,
                'correct_option' => $answer->question->correct_option,
                'explanation' => $answer->question->explanation,
                'topic' => $answer->question->topic,
                'response_seconds' => $answer->response_seconds,
            ]);
            $score = $attempt->score;
            $correctAnswers = $attempt->correct_answers;
            $pointsEarned = $attempt->points_earned;
            $durationSeconds = $attempt->duration_seconds;
            $publicId = $attempt->public_id;
        }

        $incorrectAnswers = $answers->where('is_correct', false)->count();
        $incorrectTopics = $answers
            ->reject(fn (array $answer) => $answer['is_correct'])
            ->pluck('topic')
            ->unique();
        $topicResults = $answers->groupBy('topic')->map(function (Collection $topicAnswers, string $topic): array {
            $correct = $topicAnswers->where('is_correct', true)->count();
            $total = $topicAnswers->count();

            return [
                'topic' => $topic,
                'label' => $this->topicLabel($topic),
                'category' => $this->categoryNameForTopic($topic),
                'correct' => $correct,
                'incorrect' => $total - $correct,
                'total' => $total,
                'percentage' => (int) round($correct / $total * 100),
            ];
        })->sortBy('label');

        $categoryResults = $topicResults->groupBy('category')->map(function (Collection $topics, string $category): array {
            $correct = $topics->sum('correct');
            $total = $topics->sum('total');

            return [
                'name' => $category,
                'correct' => $correct,
                'incorrect' => $total - $correct,
                'total' => $total,
                'percentage' => (int) round($correct / $total * 100),
                'topics' => $topics->pluck('topic')->all(),
            ];
        });
        $bestPercentage = $categoryResults->max('percentage');
        $lowestPercentage = $categoryResults->min('percentage');
        $strongestCategories = $categoryResults
            ->filter(fn (array $category) => $category['percentage'] === $bestPercentage)
            ->values();
        $weakestCategories = $categoryResults
            ->filter(fn (array $category) => $category['percentage'] === $lowestPercentage)
            ->values();

        $masteredCategories = $categoryResults
            ->filter(fn (array $category) => $category['percentage'] >= 80)
            ->values();
        $needsWork = $categoryResults
            ->filter(fn (array $category) => $category['incorrect'] > 0 && $category['percentage'] < 80);
        $historyMisses = $this->historicalMisses(
            auth()->user(),
            $attempt?->id,
            $topicResults->keys()->all(),
        );
        $adviceByCategory = collect();

        if ($needsWork->isNotEmpty()) {
            $adviceByCategory = Advice::approved()
                ->with('category')
                ->whereHas('category', fn (Builder $query) => $query->whereIn('name', $needsWork->keys()))
                ->orderBy('title')
                ->get()
                ->groupBy(fn (Advice $advice) => $advice->category->name);
        }

        $recommendations = $needsWork->map(function (array $category, string $categoryName) use ($historyMisses, $adviceByCategory): array {
            $historicalMisses = collect($category['topics'])
                ->sum(fn (string $topic) => $historyMisses->get($topic, 0));

            return [
                'category' => $categoryName,
                'incorrect' => $category['incorrect'],
                'historical_misses' => $historicalMisses,
                'priority' => $category['incorrect'] + $historicalMisses,
                'advices' => $adviceByCategory->get($categoryName, collect()),
            ];
        })->sortByDesc('priority')->values();

        $newBadges = $attempt?->user?->quizBadges()
            ->wherePivot('awarded_at', '>=', $attempt->completed_at)
            ->get() ?? collect();
        $level = auth()->user() ? $this->progress(auth()->user())['level'] : null;

        return view('front.quizzes.result', compact(
            'quiz',
            'attempt',
            'answers',
            'score',
            'correctAnswers',
            'incorrectAnswers',
            'pointsEarned',
            'durationSeconds',
            'publicId',
            'incorrectTopics',
            'topicResults',
            'categoryResults',
            'bestPercentage',
            'lowestPercentage',
            'strongestCategories',
            'weakestCategories',
            'masteredCategories',
            'needsWork',
            'recommendations',
            'newBadges',
            'level',
        ));
    }

    public function review(Quiz $quiz, string $run, QuizChallengeService $challenge): RedirectResponse
    {
        $state = session()->get("quiz_runs.{$run}");

        if (is_array($state) && $state['quiz_id'] === $quiz->id && $state['completed']) {
            $reviewRun = $challenge->startReview($quiz, $run);
        } else {
            $attempt = auth()->user()?->quizAttempts()
                ->with('answers')
                ->where('public_id', $run)
                ->where('quiz_id', $quiz->id)
                ->firstOrFail();
            abort_unless($attempt !== null, 404);
            $questionIds = $attempt->answers
                ->where('is_correct', false)
                ->pluck('quiz_question_id')
                ->all();
            $reviewRun = $questionIds === [] ? null : $challenge->start($quiz, $questionIds);
        }

        if ($reviewRun === null) {
            return redirect()->route('quizzes.results', [$quiz, $run])
                ->with('success', 'Perfect score! There are no missed questions to review.');
        }

        return redirect()->route('quizzes.play', [$quiz, $reviewRun]);
    }

    public function history(): View
    {
        $user = request()->user();
        $attempts = $user->quizAttempts()
            ->with('quiz')
            ->latest('completed_at')
            ->paginate(15);
        $progress = $this->progress($user);
        $badges = $user->quizBadges()->orderByPivot('awarded_at', 'desc')->get();
        $recentAttempts = $user->quizAttempts()
            ->with('quiz')
            ->latest('completed_at')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();
        $scoreChange = $recentAttempts->count() > 1
            ? $recentAttempts->last()->score - $recentAttempts->get($recentAttempts->count() - 2)->score
            : null;
        $recentAnswers = $recentAttempts->isEmpty()
            ? collect()
            : QuizAttemptAnswer::query()
                ->whereIn('quiz_attempt_id', $recentAttempts->modelKeys())
                ->where('is_correct', false)
                ->with('question:id,topic')
                ->get();
        $recurringDifficulties = $recentAnswers
            ->groupBy(fn (QuizAttemptAnswer $answer) => $answer->question->topic)
            ->map(fn (Collection $answers, string $topic) => [
                'label' => $this->topicLabel($topic),
                'count' => $answers->count(),
            ])
            ->filter(fn (array $difficulty) => $difficulty['count'] >= 2)
            ->sortByDesc('count')
            ->values();

        return view('front.quizzes.history', compact(
            'attempts',
            'progress',
            'badges',
            'recentAttempts',
            'scoreChange',
            'recurringDifficulties',
        ));
    }

    public function statistics(): View
    {
        $quizzes = Quiz::withCount('questions')->orderBy('position')->get();
        $completedAttempts = QuizAttempt::count();
        $participants = QuizAttempt::whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $topicStats = QuizAttempt::query()
            ->selectRaw('quiz_id, COUNT(*) as attempts_count, AVG(score) as average_score')
            ->groupBy('quiz_id')
            ->with('quiz')
            ->get()
            ->keyBy('quiz_id');
        $commonlyMissed = QuizQuestion::query()
            ->with('quiz')
            ->withCount([
                'attemptAnswers as incorrect_answers_count' => fn (Builder $query) => $query->where('is_correct', false),
            ])
            ->orderByDesc('incorrect_answers_count')
            ->limit(10)
            ->get();

        return view('back.quizzes.statistics', compact(
            'quizzes',
            'completedAttempts',
            'participants',
            'topicStats',
            'commonlyMissed',
        ));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return Collection<int, array<string, mixed>>
     */
    private function answersForRun(array $state): Collection
    {
        $questions = QuizQuestion::query()
            ->whereKey(collect($state['answers'])->pluck('question_id'))
            ->get()
            ->keyBy('id');

        return collect($state['answers'])->map(fn (array $answer) => $answer + [
            'question' => $questions->get($answer['question_id']),
        ]);
    }

    /**
     * @param  list<string>  $topics
     * @return Collection<string, int>
     */
    private function historicalMisses(?User $user, ?int $exceptAttemptId, array $topics): Collection
    {
        if ($user === null || $topics === []) {
            return collect();
        }

        $recentAttemptIds = $user->quizAttempts()
            ->when($exceptAttemptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptAttemptId))
            ->latest('completed_at')
            ->limit(10)
            ->pluck('id');

        if ($recentAttemptIds->isEmpty()) {
            return collect();
        }

        return QuizAttemptAnswer::query()
            ->whereIn('quiz_attempt_id', $recentAttemptIds)
            ->where('is_correct', false)
            ->whereHas('question', fn (Builder $query) => $query->whereIn('topic', $topics))
            ->with('question:id,topic')
            ->latest('id')
            ->limit(500)
            ->get()
            ->groupBy(fn (QuizAttemptAnswer $answer) => $answer->question->topic)
            ->map(fn (Collection $answers) => $answers->count());
    }

    private function topicLabel(string $topic): string
    {
        return match ($topic) {
            'hydration' => 'Hydration',
            'sun-protection' => 'Sun protection',
            'cooling' => 'Home cooling',
            'vulnerable-people' => 'Children and older adults',
            'emergency-response' => 'Emergency response',
            'preparation' => 'Heatwave preparation',
            default => str($topic)->replace('-', ' ')->title()->toString(),
        };
    }

    private function categoryNameForTopic(string $topic): string
    {
        return match ($topic) {
            'hydration' => 'Hydration',
            'sun-protection', 'cooling' => 'Cooling and sun protection',
            'vulnerable-people', 'emergency-response' => 'Health and wellbeing',
            'preparation' => 'Equipment',
            default => 'General safety',
        };
    }

    /**
     * @return array{points: int, level: string, next_level: int|null}
     */
    private function progress(User $user): array
    {
        $points = (int) $user->quizAttempts()->sum('points_earned');

        return [
            'points' => $points,
            'level' => match (true) {
                $points >= 300 => 'Heat Safety Expert',
                $points >= 100 => 'Heat-Aware',
                default => 'Beginner',
            },
            'next_level' => match (true) {
                $points >= 300 => null,
                $points >= 100 => 300,
                default => 100,
            },
        ];
    }
}
