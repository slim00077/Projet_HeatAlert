<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Contracts\AiTextGenerator;
use Illuminate\Support\Facades\Log;

/**
 * Claude (Anthropic Messages API) behind the AiTextGenerator contract.
 */
class AnthropicTextGenerator implements AiTextGenerator
{
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
            $message = (new Client(apiKey: $this->apiKey))->messages->create(
                model: $this->model,
                maxTokens: $maxTokens,
                system: $system,
                outputConfig: ['effort' => 'low'],
                messages: [['role' => 'user', 'content' => $prompt]],
            );
        } catch (APIException $exception) {
            Log::warning('Claude request failed: '.$exception->getMessage());

            return null;
        }

        if ($message->stopReason === 'refusal') {
            return null;
        }

        $text = '';

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text) !== '' ? trim($text) : null;
    }
}
