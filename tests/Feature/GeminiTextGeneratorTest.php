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
