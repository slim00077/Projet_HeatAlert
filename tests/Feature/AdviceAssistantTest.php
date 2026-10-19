<?php

use App\Contracts\AiTextGenerator;
use App\Models\Advice;
use App\Models\AdviceCategory;
use App\Models\AdviceConversation;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class FakeAdviceAssistantAiGenerator implements AiTextGenerator
{
    /**
     * @var list<array{system: string, prompt: string, max_tokens: int}>
     */
    public array $calls = [];

    public function __construct(private readonly ?string $answer = 'Drink water regularly and rest in a cool place.') {}

    public function isAvailable(): bool
    {
        return $this->answer !== null;
    }

    public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string
    {
        $this->calls[] = [
            'system' => $system,
            'prompt' => $prompt,
            'max_tokens' => $maxTokens,
        ];

        return $this->answer;
    }
}

function fakeAdviceAssistantAi(?string $answer = 'Drink water regularly and rest in a cool place.'): FakeAdviceAssistantAiGenerator
{
    $generator = new FakeAdviceAssistantAiGenerator($answer);
    app()->instance(AiTextGenerator::class, $generator);

    return $generator;
}

it('sends the question and approved advice to the configured AI and stores a linked conversation', function () {
    $generator = fakeAdviceAssistantAi('Keep water nearby and drink throughout the day.');
    $category = AdviceCategory::factory()->create(['name' => 'Hydration']);
    $advice = Advice::factory()->for($category, 'category')->create([
        'title' => 'Drink water regularly',
        'summary' => 'Drink water throughout hot weather.',
        'content' => 'Keep a water bottle nearby and take regular drinks.',
        'is_approved' => true,
    ]);
    Advice::factory()->for($category, 'category')->create([
        'title' => 'Pending hydration advice',
        'content' => 'This content must not reach the AI.',
        'is_approved' => false,
    ]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('assistant.conversations.store'), [
        'message' => 'How should I stay hydrated during a heatwave?',
    ]);

    $response->assertCreated()
        ->assertJsonPath('messages.0.role', 'user')
        ->assertJsonPath('messages.0.content', 'How should I stay hydrated during a heatwave?')
        ->assertJsonPath('messages.1.role', 'assistant')
        ->assertJsonPath('messages.1.content', 'Keep water nearby and drink throughout the day.')
        ->assertJsonPath('messages.1.references.0.title', 'Drink water regularly')
        ->assertJsonPath('messages.1.references.0.category', 'Hydration')
        ->assertJsonPath('messages.1.references.0.url', route('advice.show', $advice));

    $conversation = AdviceConversation::query()->sole();
    expect($conversation->user->is($user))->toBeTrue()
        ->and($conversation->title)->toBe('How should I stay hydrated during a heatwave?')
        ->and($conversation->messages()->count())->toBe(2)
        ->and($generator->calls)->toHaveCount(1)
        ->and($generator->calls[0]['prompt'])
        ->toContain('How should I stay hydrated during a heatwave?')
        ->toContain('Drink water regularly')
        ->not->toContain('Pending hydration advice')
        ->and($generator->calls[0]['system'])->toContain('same language');
});

it('keeps recent conversation history when answering a follow-up', function () {
    $generator = fakeAdviceAssistantAi('Yes, keep a bottle nearby.');
    $user = User::factory()->create();
    $conversation = $user->adviceConversations()->create(['title' => 'Hydration']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'How can I drink more water?']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Drink regularly throughout the day.']);

    $this->actingAs($user)->postJson(route('assistant.messages.store', $conversation), [
        'message' => 'What can help me remember?',
    ])->assertOk()
        ->assertJsonPath('conversation_id', $conversation->id)
        ->assertJsonPath('messages.1.content', 'Yes, keep a bottle nearby.');

    expect($generator->calls[0]['prompt'])
        ->toContain('How can I drink more water?')
        ->toContain('Drink regularly throughout the day.')
        ->toContain('What can help me remember?');
    expect($conversation->messages()->count())->toBe(4);
});

it('retrieves relevant approved advice for a Tunisian dialect question', function () {
    $generator = fakeAdviceAssistantAi('Check on your grandfather and help him stay cool.');
    $category = AdviceCategory::factory()->create(['name' => 'Health and wellbeing']);
    Advice::factory()->for($category, 'category')->create([
        'title' => 'Check on older neighbours',
        'content' => 'Contact older people regularly during hot weather.',
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('assistant.conversations.store'), [
        'message' => 'Kifech n7mi jeddi mel 7arr?',
    ])->assertCreated()
        ->assertJsonPath('messages.0.content', 'Kifech n7mi jeddi mel 7arr?')
        ->assertJsonPath('messages.1.references.0.title', 'Check on older neighbours');

    expect($generator->calls[0]['prompt'])
        ->toContain('Kifech n7mi jeddi mel 7arr?')
        ->toContain('Check on older neighbours');
});

it('requires a signed-in user and hides other users conversations', function () {
    $conversation = User::factory()->create()->adviceConversations()->create(['title' => 'Private']);

    $this->get(route('assistant.index'))->assertRedirect(route('login'));
    $this->postJson(route('assistant.conversations.store'), ['message' => 'How can I stay safe in the heat?'])
        ->assertUnauthorized();
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser)
        ->postJson(route('assistant.messages.store', $conversation), ['message' => 'Tell me more about heat safety.'])
        ->assertNotFound();
    $this->get(route('advice.index', ['conversation' => $conversation->id]))
        ->assertNotFound();
});

it('returns a clear unavailable response without saving a fake answer when no API key is configured', function () {
    fakeAdviceAssistantAi(null);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('assistant.conversations.store'), [
        'message' => 'How can I stay safe in the heat?',
    ])->assertServiceUnavailable()
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'GEMINI_API_KEY'));

    expect($user->adviceConversations()->count())->toBe(0);
});

it('validates questions and does not call the AI for an empty message', function () {
    $generator = fakeAdviceAssistantAi();

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.conversations.store'), ['message' => ''])
        ->assertJsonValidationErrors('message');

    expect($generator->calls)->toBeEmpty();
});

it('renders the chat and safely escapes stored message text', function () {
    $user = User::factory()->create();
    $conversation = $user->adviceConversations()->create(['title' => 'Safety']);
    $conversation->messages()->create([
        'role' => 'user',
        'content' => '<script>alert("xss")</script>',
    ]);

    $this->actingAs($user)->get(route('advice.index', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertSee('HeatAlert Assistant')
        ->assertSee('data-assistant-form', false)
        ->assertSee('id="ai-assistant"', false)
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert("xss")</script>', false);
});

it('sends configured Gemini requests from the backend and surfaces provider failures', function () {
    config([
        'services.ai.provider' => 'gemini',
        'services.gemini.key' => 'test-secret',
        'services.gemini.model' => 'gemini-2.5-flash',
    ]);
    app()->forgetInstance(AiTextGenerator::class);
    Http::preventStrayRequests();
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Drink water regularly.']]]]],
        ]),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('assistant.conversations.store'), [
        'message' => 'How can I prevent dehydration?',
    ])->assertCreated()
        ->assertJsonPath('messages.1.content', 'Drink water regularly.');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')
        && $request->hasHeader('x-goog-api-key', 'test-secret')
        && str_contains($request['contents'][0]['parts'][0]['text'], 'How can I prevent dehydration?'));
});

it('shows the assistant in Advice and redirects the legacy assistant URL', function () {
    fakeAdviceAssistantAi();

    $this->actingAs(User::factory()->create())
        ->get(route('advice.index'))
        ->assertOk()
        ->assertSee(route('advice.index', ['new' => 1]).'#ai-assistant')
        ->assertDontSee('>AI Assistant</a>', false);

    $this->get(route('assistant.index'))
        ->assertRedirect(route('advice.index'));
});
