<?php

namespace App\Contracts;

/**
 * An AI backend that can read an image together with a text prompt.
 */
interface AiVisionGenerator
{
    /**
     * Generate a text answer about the image, or null when the AI is unavailable, declined or failed.
     */
    public function generateWithImage(string $system, string $prompt, string $imageBytes, string $mimeType, int $maxTokens = 1200): ?string;
}
