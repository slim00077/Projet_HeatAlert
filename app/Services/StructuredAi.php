<?php

namespace App\Services;

use App\Contracts\AiEmbedder;
use App\Contracts\AiTextGenerator;
use App\Contracts\AiVisionGenerator;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin layer over the configured AiTextGenerator for the residence module: never throws,
 * decodes JSON replies so callers can validate them field by field, and keeps the reason of the last
 * failure (no secrets) and the model that answered, so the UI can say exactly what happened.
 */
class StructuredAi
{
    private ?string $lastError = null;

    private ?string $model = null;

    public function __construct(private readonly AiTextGenerator $ai) {}

    public function isAvailable(): bool
    {
        return $this->ai->isAvailable();
    }

    /**
     * Why the last call returned null (the provider error, or an invalid reply), or null after a success.
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * The model that produced the last answer, when the provider reports it.
     */
    public function model(): ?string
    {
        return $this->model;
    }

    /**
     * Text for a notice: how to enable the AI, or why the last request failed.
     */
    public function failureNotice(): string
    {
        if (! $this->ai->isAvailable()) {
            return 'AI is not configured (set GEMINI_API_KEY or ANTHROPIC_API_KEY in .env)';
        }

        return 'The AI request failed'.($this->lastError !== null ? ' ('.rtrim($this->lastError, '.').')' : '');
    }

    /**
     * Marks the last reply as unusable (valid JSON but the wrong shape) so the notice can say why.
     */
    public function reject(string $reason): void
    {
        $this->lastError = $reason;
        $this->model = null;
    }

    /**
     * Whether the configured provider can read images (Gemini can).
     */
    public function supportsImages(): bool
    {
        return $this->ai->isAvailable() && $this->ai instanceof AiVisionGenerator;
    }

    /**
     * Whether the configured provider can compute semantic embeddings.
     */
    public function supportsEmbeddings(): bool
    {
        return $this->ai->isAvailable() && $this->ai instanceof AiEmbedder;
    }

    public function embeddingModel(): ?string
    {
        return $this->ai instanceof AiEmbedder ? $this->ai->embeddingModel() : null;
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>|null
     */
    public function embed(array $texts): ?array
    {
        $this->lastError = null;

        if (! $this->supportsEmbeddings()) {
            $this->lastError = $this->ai->isAvailable() ? 'The configured AI provider cannot compute embeddings (Gemini is required).' : 'AI is not configured.';

            return null;
        }

        try {
            $vectors = $this->ai->embed($texts);
        } catch (Throwable $exception) {
            Log::warning('AI embedding request failed: '.$exception->getMessage());
            $this->lastError = 'Unexpected error: '.class_basename($exception);

            return null;
        }

        if ($vectors === null) {
            $this->lastError = method_exists($this->ai, 'lastError') ? ($this->ai->lastError() ?? 'The provider returned no embeddings.') : 'The provider returned no embeddings.';
        }

        return $vectors;
    }

    /**
     * Multimodal JSON request (image + prompt).
     *
     * @return array<string, mixed>|null
     */
    public function jsonWithImage(string $system, string $prompt, string $imageBytes, string $mimeType, int $maxTokens = 1200): ?array
    {
        if (! $this->supportsImages()) {
            $this->lastError = $this->ai->isAvailable() ? 'The configured AI provider cannot read images (Gemini is required).' : 'AI is not configured.';
            $this->model = null;

            return null;
        }

        return $this->decode($this->call(fn () => $this->ai->generateWithImage(
            $system.' Reply with a single JSON object only, no markdown fences and no commentary.', $prompt, $imageBytes, $mimeType, $maxTokens,
        )));
    }

    public function text(string $system, string $prompt, int $maxTokens = 600): ?string
    {
        return $this->call(fn () => $this->ai->generate($system, $prompt, $maxTokens));
    }

    /**
     * @param  Closure(): ?string  $request
     */
    private function call(Closure $request): ?string
    {
        $this->lastError = null;
        $this->model = null;

        if (! $this->ai->isAvailable()) {
            $this->lastError = 'AI is not configured.';

            return null;
        }

        try {
            $text = $request();
        } catch (Throwable $exception) {
            Log::warning('AI request failed: '.$exception->getMessage());
            $this->lastError = 'Unexpected error: '.class_basename($exception);

            return null;
        }

        if ($text === null) {
            $this->lastError = method_exists($this->ai, 'lastError') ? ($this->ai->lastError() ?? 'The provider returned no answer.') : 'The provider returned no answer.';

            return null;
        }

        $this->model = method_exists($this->ai, 'usedModel') ? $this->ai->usedModel() : null;

        return $text;
    }

    /**
     * @return array<string, mixed>|null Null when the AI is unavailable, failed or did not return a JSON object.
     */
    public function json(string $system, string $prompt, int $maxTokens = 800): ?array
    {
        return $this->decode($this->text($system.' Reply with a single JSON object only, no markdown fences and no commentary.', $prompt, $maxTokens));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(?string $text): ?array
    {
        if ($text === null) {
            return null;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        $decoded = ($start === false || $end === false || $end < $start) ? null : json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            Log::warning('AI reply was not valid JSON: '.mb_substr($text, 0, 200));
            $this->lastError = 'The AI replied, but not with the expected JSON structure.';
            $this->model = null;

            return null;
        }

        return $decoded;
    }
}
