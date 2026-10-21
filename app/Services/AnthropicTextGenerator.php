<?php

namespace App\Services;

use Anthropic\Client;
use App\Contracts\AiTextGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Claude (Anthropic Messages API) behind the AiTextGenerator contract.
 */
class AnthropicTextGenerator implements AiTextGenerator
{
    private ?string $lastError = null;

    private ?string $usedModel = null;

    public function __construct(private readonly ?string $apiKey, private readonly string $model) {}

    /**
     * Why the last generate() call returned null, or null when it succeeded.
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function usedModel(): ?string
    {
        return $this->usedModel;
    }

    public function isAvailable(): bool
    {
        return filled($this->apiKey);
    }

    public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string
    {
        $this->lastError = null;
        $this->usedModel = null;

        if (! $this->isAvailable()) {
            $this->lastError = 'No Anthropic API key is configured (ANTHROPIC_API_KEY).';

            return null;
        }

        try {
            $message = (new Client(apiKey: $this->apiKey))->messages->create(
                model: $this->model,
                maxTokens: $maxTokens,
                system: $system,
                outputConfig: ['effort' => 'low'],
                messages: [['role' => 'user', 'content' => $prompt]],
            );
        } catch (Throwable $exception) {
            $this->lastError = 'Claude request failed: '.Str::limit(str_replace((string) $this->apiKey, '[hidden]', $exception->getMessage()), 220);
            Log::warning($this->lastError);

            return null;
        }

        if ($message->stopReason === 'refusal') {
            $this->lastError = 'Claude declined to answer this request.';

            return null;
        }

        $text = '';

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        if (trim($text) === '') {
            $this->lastError = 'Claude returned no text.';

            return null;
        }

        $this->usedModel = $this->model;

        return trim($text);
    }
}
