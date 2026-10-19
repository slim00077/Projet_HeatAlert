<?php

namespace App\Contracts;

interface AiTextGenerator
{
    /**
     * Whether a real AI backend is configured (an API key is present).
     */
    public function isAvailable(): bool;

    /**
     * Generate a short text answer, or null when the AI is unavailable, declined or failed.
     */
    public function generate(string $system, string $prompt, int $maxTokens = 1200): ?string;
}
