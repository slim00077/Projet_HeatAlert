<?php

namespace App\Services;

use App\Contracts\AiEmbedder;
use App\Contracts\AiTextGenerator;
use App\Contracts\AiVisionGenerator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Google Gemini (Generative Language REST API) behind the AiTextGenerator contract.
 *
 * Transient failures (overload 503, quota 429, 5xx, unknown model 404, timeouts) fall through to the
 * fallback models; configuration errors (bad key, bad request) stop immediately. The reason of the last
 * failure and the model that produced the last answer are exposed for the UI, never the API key.
 */
class GeminiTextGenerator implements AiEmbedder, AiTextGenerator, AiVisionGenerator
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** HTTP statuses worth trying another model (or the same one again) for. */
    private const TRANSIENT_STATUSES = [404, 429, 500, 502, 503, 504];

    private ?string $lastError = null;

    private ?string $usedModel = null;

    /**
     * @param  list<string>  $fallbackModels
     */
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly array $fallbackModels = [],
        private readonly string $embeddingModelName = 'gemini-embedding-001',
    ) {}

    public function isAvailable(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Why the last generate() call returned null, or null when it succeeded.
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * The model that produced the last successful answer.
     */
    public function usedModel(): ?string
    {
        return $this->usedModel;
    }

    public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string
    {
        return $this->request($system, [['text' => $prompt]], $maxTokens, 30);
    }

    /**
     * Multimodal request: the image is sent inline (base64) before the text prompt.
     */
    public function generateWithImage(string $system, string $prompt, string $imageBytes, string $mimeType, int $maxTokens = 1200): ?string
    {
        return $this->request($system, [
            ['inline_data' => ['mime_type' => $mimeType, 'data' => base64_encode($imageBytes)]],
            ['text' => $prompt],
        ], $maxTokens, 45);
    }

    public function embeddingModel(): string
    {
        return $this->embeddingModelName;
    }

    /**
     * Semantic vectors (256 dimensions) for any number of texts, 100 per batchEmbedContents request.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>|null
     */
    public function embed(array $texts): ?array
    {
        $this->lastError = null;

        if (! $this->isAvailable()) {
            $this->lastError = 'No Gemini API key is configured (GEMINI_API_KEY).';

            return null;
        }

        $vectors = [];

        foreach (array_chunk(array_values($texts), 100) as $chunk) {
            try {
                $response = Http::connectTimeout(8)
                    ->timeout(30)
                    ->withOptions(['verify' => config('services.open_meteo.ca_bundle') ?: true])
                    ->withHeaders(['x-goog-api-key' => $this->apiKey])
                    ->post(self::ENDPOINT.$this->embeddingModelName.':batchEmbedContents', [
                        'requests' => array_map(fn (string $text) => [
                            'model' => 'models/'.$this->embeddingModelName,
                            'content' => ['parts' => [['text' => $text]]],
                            'taskType' => 'SEMANTIC_SIMILARITY',
                            'outputDimensionality' => 256,
                        ], $chunk),
                    ]);
            } catch (Throwable $exception) {
                $this->fail('Gemini embeddings could not be computed: '.$this->clean($exception->getMessage()));

                return null;
            }

            if (! $response->successful()) {
                $this->fail("Gemini embedding model {$this->embeddingModelName} answered HTTP {$response->status()}: ".$this->clean((string) $response->json('error.message', $response->reason())));

                return null;
            }

            $values = collect($response->json('embeddings', []))->pluck('values');

            if ($values->count() !== count($chunk) || $values->contains(fn ($vector) => ! is_array($vector) || $vector === [])) {
                $this->fail('Gemini returned an incomplete set of embeddings.');

                return null;
            }

            array_push($vectors, ...$values->map(fn (array $vector) => array_map('floatval', $vector))->all());
        }

        return $vectors;
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     */
    private function request(string $system, array $parts, int $maxTokens, int $timeout): ?string
    {
        $this->lastError = null;
        $this->usedModel = null;

        if (! $this->isAvailable()) {
            $this->lastError = 'No Gemini API key is configured (GEMINI_API_KEY).';

            return null;
        }

        foreach ($this->attempts() as $index => $model) {
            if ($index > 0 && $model === $this->attempts()[$index - 1]) {
                usleep(800_000);
            }

            try {
                $response = Http::connectTimeout(8)
                    ->timeout($timeout)
                    ->withOptions(['verify' => config('services.open_meteo.ca_bundle') ?: true])
                    ->withHeaders(['x-goog-api-key' => $this->apiKey])
                    ->post(self::ENDPOINT.$model.':generateContent', [
                        'systemInstruction' => ['parts' => [['text' => $system]]],
                        'contents' => [['role' => 'user', 'parts' => $parts]],
                        // Thinking models spend part of this budget on reasoning, so leave room for the answer.
                        'generationConfig' => ['maxOutputTokens' => max($maxTokens * 4, 2048), 'temperature' => 0.4],
                    ]);
            } catch (ConnectionException $exception) {
                $this->fail("Gemini model {$model} could not be reached: ".$this->clean($exception->getMessage()));

                continue;
            } catch (Throwable $exception) {
                // Local setup problems (for example an unreadable CA bundle) are not retryable.
                $this->fail('Gemini request could not be sent: '.$this->clean($exception->getMessage()));

                return null;
            }

            if (! $response->successful()) {
                $this->fail("Gemini model {$model} answered HTTP {$response->status()}: ".$this->clean((string) $response->json('error.message', $response->reason())));

                if (in_array($response->status(), self::TRANSIENT_STATUSES, true)) {
                    continue;
                }

                return null;
            }

            $text = trim(collect($response->json('candidates.0.content.parts', []))->pluck('text')->filter()->implode(''));

            if ($text === '') {
                $reason = $response->json('promptFeedback.blockReason') ?? $response->json('candidates.0.finishReason') ?? 'no content';
                $this->fail("Gemini model {$model} returned no text ({$reason}).");

                return null;
            }

            $this->lastError = null;
            $this->usedModel = $model;

            return $text;
        }

        return null;
    }

    /**
     * Models to try, in order; a lone model is tried twice to ride out a short overload.
     *
     * @return list<string>
     */
    private function attempts(): array
    {
        $models = array_values(array_unique(array_filter([$this->model, ...$this->fallbackModels])));

        return count($models) === 1 ? [$models[0], $models[0]] : $models;
    }

    private function fail(string $message): void
    {
        $this->lastError = $message;
        Log::warning('Gemini request failed: '.$message);
    }

    /**
     * Single line, without the API key, short enough for a notice.
     */
    private function clean(string $message): string
    {
        $message = (string) preg_replace('/\s*\(see https?:\/\/\S+\)( for https?:\/\/\S*)?/', '', str_replace((string) $this->apiKey, '[hidden]', $message));

        return Str::limit(trim((string) preg_replace('/\s+/', ' ', $message)), 220);
    }
}
