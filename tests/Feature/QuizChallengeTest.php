<?php

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Database\Seeders\AdviceSeeder;
use Database\Seeders\QuizSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function quizChallengeFixture(string $slug = 'heat-protection', int $questionCount = 10, string $topic = 'hydration'): Quiz
{
    $quiz = Quiz::query()->create([
        'slug' => $slug,
        'title' => 'Heat protection',
        'description' => 'A short heat safety quiz.',
        'icon' => 'sun',
    ]);

    for ($position = 1; $position <= $questionCount; $position++) {
        QuizQuestion::query()->create([
            'quiz_id' => $quiz->id,
            'topic' => $topic,
            'prompt' => "Question {$position}?",
            'options' => [
                "Incorrect option {$position}",
                "Correct option {$position}",
                "Another option {$position}",
                "Last option {$position}",
            ],
            'correct_option' => 1,
            'explanation' => "Explanation for question {$position}.",
            'position' => $position,
        ]);
    }

    return $quiz;
}

function completeQuizChallengeRun(TestCase $test, Quiz $quiz, string $runId, array $run, int $incorrectCount = 0): void
{
    foreach ($run['question_ids'] as $position => $questionId) {
        $question = QuizQuestion::query()->findOrFail($questionId);
        $optionOrder = $run['option_orders'][$questionId];
        $correctDisplayOption = array_search($question->correct_option, $optionOrder, true);
        $selectedOption = $position < $incorrectCount
            ? ($correctDisplayOption + 1) % count($optionOrder)
            : $correctDisplayOption;

        $test->post(route('quizzes.answer', [$quiz, $runId]), [
            'question_id' => $question->id,
            'selected_option' => $selectedOption,
        ])->assertRedirect(route('quizzes.play', [$quiz, $runId]));

        $nextResponse = $test->post(route('quizzes.next', [$quiz, $runId]));
        if ($position === array_key_last($run['question_ids'])) {
            $nextResponse->assertRedirect(route('quizzes.results', [$quiz, $runId]));
        } else {
            $nextResponse->assertRedirect(route('quizzes.play', [$quiz, $runId]));
        }
    }
}

it('selects ten distinct shuffled questions and prioritises unseen questions for the next attempt', function () {
    $this->seed(QuizSeeder::class);
    $quiz = Quiz::query()->where('slug', 'heat-protection')->firstOrFail();

    expect($quiz->questions()->count())->toBe(15)
        ->and(QuizQuestion::query()->count())->toBe(60);

    $this->post(route('quizzes.start', $quiz))->assertRedirect();
    $firstRunId = array_key_last(session('quiz_runs'));
    $firstRun = session("quiz_runs.{$firstRunId}");

    $this->post(route('quizzes.start', $quiz))->assertRedirect();
    $secondRunId = array_key_last(session('quiz_runs'));
    $secondRun = session("quiz_runs.{$secondRunId}");

    expect($firstRun['question_ids'])->toHaveCount(10)
        ->and(array_unique($firstRun['question_ids']))->toHaveCount(10)
        ->and($secondRun['question_ids'])->toHaveCount(10)
        ->and(array_unique($secondRun['question_ids']))->toHaveCount(10)
        ->and(array_intersect($firstRun['question_ids'], $secondRun['question_ids']))->toHaveCount(5);

    foreach ($secondRun['option_orders'] as $optionOrder) {
        expect($optionOrder)->toHaveCount(4)
            ->and(array_unique($optionOrder))->toHaveCount(4)
            ->and(array_diff($optionOrder, [0, 1, 2, 3]))->toBe([]);
    }
});

it('shows immediate feedback and prevents changing an answered question before continuing', function () {
    $quiz = quizChallengeFixture();

    $this->post(route('quizzes.start', $quiz))->assertRedirect();
    $runId = array_key_last(session('quiz_runs'));
    $run = session("quiz_runs.{$runId}");
    $question = QuizQuestion::query()->findOrFail($run['question_ids'][0]);
    $correctDisplayOption = array_search($question->correct_option, $run['option_orders'][$question->id], true);
    $wrongDisplayOption = ($correctDisplayOption + 1) % 4;

    $this->post(route('quizzes.answer', [$quiz, $runId]), [
        'question_id' => $question->id,
        'selected_option' => $wrongDisplayOption,
    ])->assertRedirect(route('quizzes.play', [$quiz, $runId]));

    $this->get(route('quizzes.play', [$quiz, $runId]))
        ->assertSee('Not quite')
        ->assertSee('Your answer:')
        ->assertSee('Correct answer:')
        ->assertSee($question->options[$question->correct_option])
        ->assertSee($question->explanation)
        ->assertSee('Continue to the next question')
        ->assertDontSee('Validate my answer');

    $this->post(route('quizzes.answer', [$quiz, $runId]), [
        'question_id' => $question->id,
        'selected_option' => $correctDisplayOption,
    ])->assertStatus(409);

    $this->post(route('quizzes.next', [$quiz, $runId]))
        ->assertRedirect(route('quizzes.play', [$quiz, $runId]));

    expect(session("quiz_runs.{$runId}.position"))->toBe(1);
});

it('redirects duplicate or premature next requests without skipping questions or returning 409', function () {
    $quiz = quizChallengeFixture();

    $this->post(route('quizzes.start', $quiz))->assertRedirect();
    $runId = array_key_last(session('quiz_runs'));
    $run = session("quiz_runs.{$runId}");
    $firstQuestion = QuizQuestion::query()->findOrFail($run['question_ids'][0]);

    $this->post(route('quizzes.next', [$quiz, $runId]))
        ->assertRedirect(route('quizzes.play', [$quiz, $runId]));

    expect(session("quiz_runs.{$runId}.position"))->toBe(0)
        ->and(session("quiz_runs.{$runId}.answers"))->toBe([]);

    $correctDisplayOption = array_search($firstQuestion->correct_option, $run['option_orders'][$firstQuestion->id], true);
    $this->post(route('quizzes.answer', [$quiz, $runId]), [
        'question_id' => $firstQuestion->id,
        'selected_option' => $correctDisplayOption,
    ])->assertRedirect();

    $this->post(route('quizzes.next', [$quiz, $runId]))
        ->assertRedirect(route('quizzes.play', [$quiz, $runId]));
    $this->post(route('quizzes.next', [$quiz, $runId]))
        ->assertRedirect(route('quizzes.play', [$quiz, $runId]));

    expect(session("quiz_runs.{$runId}.position"))->toBe(1)
        ->and(count(session("quiz_runs.{$runId}.answers")))->toBe(1);
});

it('saves exact scores, selected answers and response times for signed-in users', function () {
    $this->seed(QuizSeeder::class);
    $user = User::factory()->create();
    $quiz = Quiz::query()->where('slug', 'hydration')->firstOrFail();

    $this->freezeTime();
    $this->actingAs($user)->post(route('quizzes.start', $quiz))->assertRedirect();
    $runId = array_key_last(session('quiz_runs'));
    $run = session("quiz_runs.{$runId}");
    $firstQuestionId = $run['question_ids'][0];
    $firstQuestion = QuizQuestion::query()->findOrFail($firstQuestionId);
    $correctDisplayOption = array_search($firstQuestion->correct_option, $run['option_orders'][$firstQuestionId], true);

    $this->travel(5)->seconds();
    $this->post(route('quizzes.answer', [$quiz, $runId]), [
        'question_id' => $firstQuestionId,
        'selected_option' => $correctDisplayOption,
    ])->assertRedirect();

    $this->get(route('quizzes.play', [$quiz, $runId]))
        ->assertSee('Correct! +10 points')
        ->assertSee('Correct answer:')
        ->assertSee($firstQuestion->options[$firstQuestion->correct_option])
        ->assertSee($firstQuestion->explanation);

    $firstResponseTime = session("quiz_runs.{$runId}.answers.0.response_seconds");
    expect($firstResponseTime)->toBe(5);

    $this->post(route('quizzes.next', [$quiz, $runId]))->assertRedirect();
    $run = session("quiz_runs.{$runId}");
    foreach (array_slice($run['question_ids'], 1) as $questionId) {
        $question = QuizQuestion::query()->findOrFail($questionId);
        $correctIndex = array_search($question->correct_option, $run['option_orders'][$questionId], true);
        $this->post(route('quizzes.answer', [$quiz, $runId]), [
            'question_id' => $questionId,
            'selected_option' => $correctIndex,
        ])->assertRedirect();
        $this->post(route('quizzes.next', [$quiz, $runId]))->assertRedirect();
    }

    $attempt = QuizAttempt::query()->where('user_id', $user->id)->firstOrFail();

    expect($attempt->correct_answers)->toBe(10)
        ->and($attempt->score)->toBe(100)
        ->and($attempt->points_earned)->toBe(100)
        ->and($attempt->answers()->count())->toBe(10)
        ->and($attempt->answers()->where('response_seconds', 5)->exists())->toBeTrue()
        ->and($user->quizBadges()->pluck('slug')->all())
        ->toContain('first-quiz', 'hydration-expert', 'perfect-quiz');

    $this->get(route('quizzes.history'))
        ->assertSee('Heat-Aware')
        ->assertSee($quiz->title)
        ->assertSee('Score progression')
        ->assertSee('100%');
});

it('builds personalised recommendations from actual weak categories and approved advice', function () {
    $this->seed(AdviceSeeder::class);
    $quiz = quizChallengeFixture();

    $this->post(route('quizzes.start', $quiz))->assertRedirect();
    $runId = array_key_last(session('quiz_runs'));
    $run = session("quiz_runs.{$runId}");
    completeQuizChallengeRun($this, $quiz, $runId, $run, incorrectCount: 3);

    $this->get(route('quizzes.results', [$quiz, $runId]))
        ->assertSee('70%')
        ->assertSee('7/10 correct')
        ->assertSee('3 mistakes in this quiz')
        ->assertSee('Hydration')
        ->assertSee('Approved advice to review')
        ->assertSee('Drink water regularly')
        ->assertSee('Restart with new questions')
        ->assertSee('View recommended advice');
});

it('renders strongest and most difficult categories when a quiz covers multiple categories', function () {
    $quiz = quizChallengeFixture();

    $this->post(route('quizzes.start', $quiz))->assertRedirect();
    $runId = array_key_last(session('quiz_runs'));
    $run = session("quiz_runs.{$runId}");

    QuizQuestion::query()
        ->whereIn('id', array_slice($run['question_ids'], 0, 5))
        ->update(['topic' => 'sun-protection']);

    completeQuizChallengeRun($this, $quiz, $runId, $run, incorrectCount: 3);

    $this->get(route('quizzes.results', [$quiz, $runId]))
        ->assertSee('Strongest result:')
        ->assertSee('Most difficult:')
        ->assertSee('Cooling and sun protection')
        ->assertSee('Hydration');
});

it('does not recommend basic advice for a category the user has mastered', function () {
    $this->seed(AdviceSeeder::class);
    $quiz = quizChallengeFixture();

    $this->post(route('quizzes.start', $quiz))->assertRedirect();
    $runId = array_key_last(session('quiz_runs'));
    $run = session("quiz_runs.{$runId}");
    completeQuizChallengeRun($this, $quiz, $runId, $run, incorrectCount: 1);

    $response = $this->get(route('quizzes.results', [$quiz, $runId]));

    $response
        ->assertSee('90%')
        ->assertSee('What you know well');

    expect(str_contains($response->getContent(), 'id="quiz-recommendations"'))->toBeFalse();
});

it('repeats only missed questions in a personalised review', function () {
    $quiz = quizChallengeFixture();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('quizzes.start', $quiz))->assertRedirect();
    $runId = array_key_last(session('quiz_runs'));
    $run = session("quiz_runs.{$runId}");
    $missedQuestionId = $run['question_ids'][0];
    completeQuizChallengeRun($this, $quiz, $runId, $run, incorrectCount: 1);

    $this->post(route('quizzes.review', [$quiz, $runId]))->assertRedirect();
    $reviewRunId = array_key_last(session('quiz_runs'));
    $reviewRun = session("quiz_runs.{$reviewRunId}");

    expect($reviewRun['question_ids'])->toBe([$missedQuestionId]);
});

it('shows score improvement and recurring weaknesses in authenticated history', function () {
    $this->seed(QuizSeeder::class);
    $user = User::factory()->create();
    $quiz = Quiz::query()->where('slug', 'hydration')->firstOrFail();

    foreach ([5, 3] as $incorrectCount) {
        $this->actingAs($user)->post(route('quizzes.start', $quiz))->assertRedirect();
        $runId = array_key_last(session('quiz_runs'));
        $run = session("quiz_runs.{$runId}");
        completeQuizChallengeRun($this, $quiz, $runId, $run, $incorrectCount);
        $this->travel(1)->seconds();
    }

    $this->get(route('quizzes.results', [$quiz, $runId]))
        ->assertSee('5 recent repeated errors');

    $this->get(route('quizzes.history'))
        ->assertSee('+20 points since your previous attempt')
        ->assertSee('Topics to revisit')
        ->assertSee('Hydration');
});

it('does not start quizzes without at least ten questions', function () {
    $quiz = quizChallengeFixture(questionCount: 5);
    $emptyQuiz = quizChallengeFixture(slug: 'empty-quiz', questionCount: 0);

    $this->post(route('quizzes.start', $quiz))
        ->assertRedirect()
        ->assertSessionHasErrors(['quiz' => 'This quiz needs at least 10 questions before it can be played.']);

    $this->post(route('quizzes.start', $emptyQuiz))
        ->assertRedirect()
        ->assertSessionHasErrors(['quiz' => 'This quiz does not have any questions yet.']);
});

it('does not expose a completed attempt to another signed-in user', function () {
    $quiz = quizChallengeFixture();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($owner)->post(route('quizzes.start', $quiz))->assertRedirect();
    $runId = array_key_last(session('quiz_runs'));
    $run = session("quiz_runs.{$runId}");
    completeQuizChallengeRun($this, $quiz, $runId, $run);

    $this->actingAs($otherUser)
        ->get(route('quizzes.results', [$quiz, $runId]))
        ->assertNotFound();
});

it('restricts quiz statistics to administrators', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.quizzes.statistics'))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.quizzes.statistics'))
        ->assertSee('Quiz Statistics')
        ->assertSee('Most-missed questions');
});
