<?php

use App\Contracts\AiTextGenerator;
use App\Services\AnthropicTextGenerator;
use App\Services\GeminiTextGenerator;
use Illuminate\Support\Facades\Http;

it('returns the generated text from the Gemini API and sends the key as a header', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => 'Stay '], ['text' => 'hydrated.']]]]],
    ])]);

    $text = (new GeminiTextGenerator('test-key', 'gemini-2.5-flash'))->generate('system rules', 'what now?', 300);

    expect($text)->toBe('Stay hydrated.');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')
        && $request->hasHeader('x-goog-api-key', 'test-key')
        && $request['systemInstruction']['parts'][0]['text'] === 'system rules'
        && $request['contents'][0]['parts'][0]['text'] === 'what now?'
        && $request['generationConfig']['maxOutputTokens'] >= 2048);
});

it('returns null without a key, on API errors and when the answer is blocked', function () {
    Http::fake();
    expect((new GeminiTextGenerator(null, 'gemini-2.5-flash'))->generate('s', 'p'))->toBeNull();
    Http::assertNothingSent();

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);
    expect((new GeminiTextGenerator('bad', 'gemini-2.5-flash'))->generate('s', 'p'))->toBeNull();

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])]);
    expect((new GeminiTextGenerator('key', 'gemini-2.5-flash'))->generate('s', 'p'))->toBeNull();
});

it('picks Gemini when its key is configured and Claude otherwise', function () {
    config(['services.ai.provider' => 'gemini', 'services.gemini.key' => 'abc']);
    app()->forgetInstance(AiTextGenerator::class);
    expect(app(AiTextGenerator::class))->toBeInstanceOf(GeminiTextGenerator::class)->and(app(AiTextGenerator::class)->isAvailable())->toBeTrue();

    config(['services.ai.provider' => 'anthropic']);
    app()->forgetInstance(AiTextGenerator::class);
    expect(app(AiTextGenerator::class))->toBeInstanceOf(AnthropicTextGenerator::class);
});

it('falls back to the next model when the main one is overloaded and reports which model answered', function () {
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/main-model:*' => Http::response(['error' => ['code' => 503, 'message' => 'This model is currently experiencing high demand.']], 503),
        'generativelanguage.googleapis.com/v1beta/models/backup-model:*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'pong']]]]]]),
    ]);

    $generator = new GeminiTextGenerator('secret-key', 'main-model', ['backup-model']);

    expect($generator->generate('s', 'p'))->toBe('pong')
        ->and($generator->usedModel())->toBe('backup-model')
        ->and($generator->lastError())->toBeNull();
});

it('explains why a request failed without leaking the key', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid: secret-key']], 400)]);

    $generator = new GeminiTextGenerator('secret-key', 'main-model', ['backup-model']);

    expect($generator->generate('s', 'p'))->toBeNull()
        ->and($generator->lastError())->toContain('HTTP 400')->toContain('API key not valid')->not->toContain('secret-key');
    Http::assertSentCount(1);
});

it('retries a lone model once when it is overloaded', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'overloaded']], 503)]);

    expect((new GeminiTextGenerator('secret-key', 'only-model'))->generate('s', 'p'))->toBeNull();
    Http::assertSentCount(2);
});

it('ignores a configured CA bundle path that does not exist', function () {
    expect(config('services.open_meteo.ca_bundle'))->toBeNull();
});
