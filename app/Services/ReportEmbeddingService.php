<?php

namespace App\Services;

use App\Models\ResidentReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Semantic vectors of resident reports, computed by the configured AI provider (Gemini embeddings)
 * and cached on the report. A vector is recomputed only when the report text or the model changes.
 */
class ReportEmbeddingService
{
    public function __construct(private readonly StructuredAi $ai) {}

    public function isAvailable(): bool
    {
        return $this->ai->supportsEmbeddings();
    }

    public function failureNotice(): string
    {
        return $this->ai->failureNotice();
    }

    public function model(): ?string
    {
        return $this->ai->embeddingModel();
    }

    /**
     * Vectors keyed by report id, computing the missing ones in batches; null when embeddings are unavailable or failed.
     *
     * @param  Collection<int, ResidentReport>  $reports
     * @return array<int, list<float>>|null
     */
    public function vectorsFor(Collection $reports): ?array
    {
        if (! $this->ai->supportsEmbeddings()) {
            $this->ai->embed([]);

            return null;
        }

        $model = (string) $this->ai->embeddingModel();
        $stale = $reports->filter(fn (ResidentReport $report) => ! is_array($report->embedding) || $report->embedding_signature !== $this->signature($report, $model))->values();

        if ($stale->isNotEmpty()) {
            $vectors = $this->ai->embed($stale->map(fn (ResidentReport $report) => $report->semanticText())->all());

            if ($vectors === null) {
                return null;
            }

            foreach ($stale as $index => $report) {
                $signature = $this->signature($report, $model);

                // Written without touching updated_at or model events: the report itself did not change.
                DB::table('resident_reports')->where('id', $report->id)->update([
                    'embedding' => json_encode($vectors[$index]),
                    'embedding_signature' => $signature,
                ]);

                $report->embedding = $vectors[$index];
                $report->embedding_signature = $signature;
                $report->syncOriginalAttributes(['embedding', 'embedding_signature']);
            }
        }

        return $reports->mapWithKeys(fn (ResidentReport $report) => [$report->id => $report->embedding])->all();
    }

    /**
     * Cosine similarity of two vectors, from -1 to 1.
     *
     * @param  list<float>  $first
     * @param  list<float>  $second
     */
    public static function cosine(array $first, array $second): float
    {
        $dot = $normFirst = $normSecond = 0.0;

        foreach ($first as $index => $value) {
            $other = $second[$index] ?? 0.0;
            $dot += $value * $other;
            $normFirst += $value * $value;
            $normSecond += $other * $other;
        }

        return $normFirst > 0 && $normSecond > 0 ? $dot / sqrt($normFirst * $normSecond) : 0.0;
    }

    private function signature(ResidentReport $report, string $model): string
    {
        return hash('sha256', $model.'|'.$report->semanticText());
    }
}
