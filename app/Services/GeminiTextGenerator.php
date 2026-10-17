<?php

namespace App\Services;

use App\Contracts\AiTextGenerator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini (Generative Language REST API) behind the AiTextGenerator contract.
 */
class GeminiTextGenerator implements AiTextGenerator
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(private readonly ?string $apiKey, private readonly string $model) {}

    public function isAvailable(): bool
    {
        return filled($this->apiKey);
    }

    public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->withOptions(['verify' => config('services.open_meteo.ca_bundle') ?: true])
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->post(self::ENDPOINT.$this->model.':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    // Thinking models spend part of this budget on reasoning, so leave room for the answer.
                    'generationConfig' => ['maxOutputTokens' => max($maxTokens * 4, 2048), 'temperature' => 0.4],
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Gemini request failed: '.$exception->getMessage());

            return null;
        }

        $parts = $response->json('candidates.0.content.parts', []);
        $text = trim(collect($parts)->pluck('text')->filter()->implode(''));

        return $text !== '' ? $text : null;
    }
}
