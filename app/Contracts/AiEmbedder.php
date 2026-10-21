<?php

namespace App\Contracts;

/**
 * An AI backend that turns texts into semantic embedding vectors.
 */
interface AiEmbedder
{
    /**
     * One vector per text (same order), or null when the AI is unavailable or failed.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>|null
     */
    public function embed(array $texts): ?array;

    /**
     * Identifier of the embedding model, stored with each vector so stale vectors are recomputed.
     */
    public function embeddingModel(): string;
}
