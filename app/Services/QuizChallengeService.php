<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizBadge;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuizChallengeService
{
    /**
     * @param  list<int>  $questionIds
     */
    public function start(Quiz $quiz, array $questionIds): string
    {
        $validIds = $quiz->questions()->whereKey($questionIds)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($questionIds === [] || count(array_unique($questionIds)) !== count($questionIds) || count($validIds) !== count($questionIds)) {
            abort(404);
        }

        $questions = $quiz->questions()->whereKey($questionIds)->get()->keyBy('id');
        $optionOrders = [];

        foreach ($questionIds as $questionId) {
            $optionOrder = array_keys($questions->get($questionId)->options);
            shuffle($optionOrder);
            $optionOrders[$questionId] = $optionOrder;
        }

        $runId = (string) Str::uuid();

        session()->put("quiz_runs.{$runId}", [
            'quiz_id' => $quiz->id,
            'question_ids' => $questionIds,
            'option_orders' => $optionOrders,
            'position' => 0,
            'answers' => [],
            'score' => 0,
            'started_at' => now()->timestamp,
            'question_started_at' => now()->timestamp,
            'awaiting_next' => false,
            'pending_feedback' => null,
            'completed' => false,
        ]);

        return $runId;
    }

    /**
     * @return array<string, mixed>
     */
    public function run(Quiz $quiz, string $runId): array
    {
        $run = session()->get("quiz_runs.{$runId}");

        abort_unless(is_array($run) && $run['quiz_id'] === $quiz->id, 404);

        if (! $run['completed'] && ! array_key_exists('option_orders', $run)) {
            $questions = $quiz->questions()->whereKey($run['question_ids'])->get()->keyBy('id');
            $run['option_orders'] = [];

            foreach ($run['question_ids'] as $questionId) {
                $optionOrder = array_keys($questions->get($questionId)->options);
                shuffle($optionOrder);
                $run['option_orders'][$questionId] = $optionOrder;
            }

            $run['awaiting_next'] = false;
            $run['pending_feedback'] = null;
            $run['question_started_at'] = now()->timestamp;
            session()->put("quiz_runs.{$runId}", $run);
        }

        return $run;
    }

    /**
     * @return array<string, mixed>
     */
    public function answer(Quiz $quiz, string $runId, int $questionId, int $selectedOption): array
    {
        $run = $this->run($quiz, $runId);

        abort_if(
            $run['completed'] || $run['awaiting_next'] || $run['question_ids'][$run['position']] !== $questionId,
            409,
        );

        $question = QuizQuestion::query()
            ->where('quiz_id', $quiz->id)
            ->findOrFail($questionId);
        $optionOrder = $run['option_orders'][$questionId] ?? [];

        if (! array_key_exists($selectedOption, $optionOrder)) {
            throw ValidationException::withMessages([
                'selected_option' => 'Choose one of the available answers.',
            ]);
        }

        $selectedOriginalOption = $optionOrder[$selectedOption];
        $correctDisplayOption = array_search($question->correct_option, $optionOrder, true);

        if ($correctDisplayOption === false) {
            abort(409);
        }

        $displayOptions = array_map(
            fn (int $optionIndex) => $question->options[$optionIndex],
            $optionOrder,
        );
        $isCorrect = $selectedOriginalOption === $question->correct_option;
        $answer = [
            'question_id' => $question->id,
            'selected_option' => $selectedOriginalOption,
            'selected_display_option' => $selectedOption,
            'is_correct' => $isCorrect,
            'correct_option' => $question->correct_option,
            'correct_display_option' => $correctDisplayOption,
            'selected_answer' => $displayOptions[$selectedOption],
            'correct_answer' => $question->options[$question->correct_option],
            'display_options' => $displayOptions,
            'explanation' => $question->explanation,
            'topic' => $question->topic,
            'response_seconds' => max(0, now()->timestamp - $run['question_started_at']),
        ];

        $run['answers'][] = $answer;
        $run['awaiting_next'] = true;
        $run['pending_feedback'] = $answer;

        if ($isCorrect) {
            $run['score'] += 10;
        }

        session()->put("quiz_runs.{$runId}", $run);

        return $answer;
    }

    public function continue(Quiz $quiz, string $runId): bool
    {
        $run = $this->run($quiz, $runId);

        if ($run['completed']) {
            return true;
        }

        if (! $run['awaiting_next']) {
            return false;
        }

        $run['position']++;
        $run['awaiting_next'] = false;
        $run['pending_feedback'] = null;

        if ($run['position'] === count($run['question_ids'])) {
            $run['completed'] = true;
            $run['public_id'] = $runId;
            $run['completed_at'] = now()->timestamp;
            $run['attempt_id'] = $this->persistAttempt($quiz, $run);
        } else {
            $run['question_started_at'] = now()->timestamp;
        }

        session()->put("quiz_runs.{$runId}", $run);

        return $run['completed'];
    }

    public function startReview(Quiz $quiz, string $runId): ?string
    {
        $run = $this->run($quiz, $runId);
        abort_unless($run['completed'], 409);

        $questionIds = collect($run['answers'])
            ->reject(fn (array $answer) => $answer['is_correct'])
            ->pluck('question_id')
            ->all();

        return $questionIds === [] ? null : $this->start($quiz, $questionIds);
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function persistAttempt(Quiz $quiz, array $run): ?int
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        return DB::transaction(function () use ($quiz, $run, $user): int {
            $correctAnswers = collect($run['answers'])->where('is_correct', true)->count();
            $totalQuestions = count($run['question_ids']);

            $attempt = $user->quizAttempts()->create([
                'public_id' => $run['public_id'],
                'quiz_id' => $quiz->id,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'score' => (int) round($correctAnswers / $totalQuestions * 100),
                'points_earned' => $run['score'],
                'duration_seconds' => max(0, $run['completed_at'] - $run['started_at']),
                'completed_at' => now(),
            ]);

            $attempt->answers()->createMany(
                collect($run['answers'])->map(fn (array $answer) => [
                    'quiz_question_id' => $answer['question_id'],
                    'selected_option' => $answer['selected_option'],
                    'is_correct' => $answer['is_correct'],
                    'response_seconds' => $answer['response_seconds'],
                ])->all(),
            );

            $this->awardBadges($attempt);

            return $attempt->id;
        });
    }

    private function awardBadges(QuizAttempt $attempt): void
    {
        $eligibleBadges = [];

        if ($attempt->user->quizAttempts()->count() === 1) {
            $eligibleBadges[] = 'first-quiz';
        }

        if ($attempt->quiz->slug === 'hydration' && $attempt->correct_answers >= 8) {
            $eligibleBadges[] = 'hydration-expert';
        }

        if ($attempt->quiz->slug === 'heatwave-actions' && $attempt->correct_answers >= 8) {
            $eligibleBadges[] = 'heatwave-specialist';
        }

        if ($attempt->correct_answers === $attempt->total_questions) {
            $eligibleBadges[] = 'perfect-quiz';
        }

        $badges = QuizBadge::query()->whereIn('slug', $eligibleBadges)->get();

        foreach ($badges as $badge) {
            $attempt->user->quizBadges()->syncWithoutDetaching([
                $badge->id => ['awarded_at' => now()],
            ]);
        }
    }
}
