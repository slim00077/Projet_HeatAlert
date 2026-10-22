<?php

namespace App\Services;

use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * AI investigation of a cluster of reports selected in the Incident Constellation.
 *
 * The caller has already checked that every report is within the viewer's scope. The AI only sees those
 * records (plus their measured semantic similarity), every report id it cites is validated against the
 * selection, and possible causes are returned as hypotheses with a confidence level, never as facts.
 */
class IncidentInvestigationService
{
    private const CONFIDENCE = ['low', 'medium', 'high'];

    private const RELATIONS = ['possible duplicate', 'same recurring problem', 'possibly related', 'probably unrelated'];

    private const SYSTEM = 'You are an incident analyst for the HeatAlert residence platform, helping a residence manager investigate reports. '
        .'Use ONLY the records provided. Distinguish facts stated in the records from your hypotheses. Never present a cause as confirmed, '
        .'never invent repairs, dates or measurements, and say clearly when the records are insufficient. Never include personal names or contact details.';

    public function __construct(private readonly StructuredAi $ai, private readonly ReportEmbeddingService $embeddings) {}

    /**
     * @param  Collection<int, ResidentReport>  $reports  Already authorized for the viewer.
     * @return array<string, mixed>
     */
    public function investigate(User $viewer, Collection $reports): array
    {
        $reports->loadMissing('residence');
        $ids = $reports->modelKeys();

        $records = $reports->sortBy('reported_at')->map(fn (ResidentReport $report) => array_filter([
            'id' => $report->id,
            'residence' => $report->residence->name,
            'category' => $report->categoryLabel(),
            'status' => $report->status,
            'reported_on' => $report->reported_at->format('Y-m-d'),
            'resolved_on' => $report->resolved_at?->format('Y-m-d'),
            'days_open' => $report->daysOpen(),
            'title' => Str::limit($report->title, 150),
            'description' => Str::limit($report->description, 500),
            'documented_resolution' => $report->resolution_notes ? Str::limit($report->resolution_notes, 400) : null,
        ], fn ($value) => $value !== null))->values()->all();

        $similarities = $this->measuredSimilarities($reports);

        $result = [
            'report_ids' => $ids,
            'source' => 'unavailable',
            'model' => null,
            'notice' => null,
            'summary' => null,
            'relationships' => [],
            'hypotheses' => [],
            'uncertainties' => [],
            'next_steps' => [],
            'cases' => $this->cases($viewer, $ids),
        ];

        $key = 'incident-investigation:'.md5(json_encode([$records, $similarities]));
        $cached = Cache::remember($key, now()->addMinutes(30), function () use ($records, $similarities) {
            $reply = $this->ai->json(
                self::SYSTEM,
                "Reports selected by the manager (JSON):\n".json_encode($records, JSON_UNESCAPED_UNICODE)."\n\n"
                .'Semantic similarity measured by an embedding model (cosine, 1 = same meaning; above 0.9 is very close): '
                .($similarities === [] ? 'not available' : json_encode($similarities))."\n\n"
                .'Return {"summary":"<at most 70 words>",'
                .'"relationships":[{"report_ids":[<2 or more ids>],"relation":"possible duplicate|same recurring problem|possibly related|probably unrelated","evidence":["<fact quoted or paraphrased from the records>"],"confidence":"low|medium|high"}],'
                .'"hypotheses":[{"statement":"<a possible common cause, phrased as a possibility>","supporting_report_ids":[<ids>],"evidence":["<fact from the records>"],"confidence":"low|medium|high"}],'
                .'"uncertainties":["<what the records do not show>"],'
                .'"next_steps":["<concrete investigative step for the manager>"]}. '
                .'At most 4 relationships, 3 hypotheses, 4 uncertainties and 5 next steps.',
                1500,
            );

            return $reply === null ? null : ['reply' => $reply, 'model' => $this->ai->model()];
        });

        if ($cached === null) {
            return ['notice' => $this->ai->failureNotice().'. No AI investigation was produced; the timeline and records below are real data.'] + $result;
        }

        $reply = $cached['reply'];

        if (! is_string($reply['summary'] ?? null) || ! is_array($reply['hypotheses'] ?? null)) {
            return ['notice' => 'The AI replied, but not with the expected investigation structure, so nothing is shown.'] + $result;
        }

        return [
            'source' => 'ai',
            'model' => $cached['model'],
            'notice' => 'AI investigation based only on the '.count($ids).' selected reports. Hypotheses are possibilities to check, not confirmed causes.',
            'summary' => Str::limit(strip_tags($reply['summary']), 600),
            'relationships' => $this->items($reply['relationships'] ?? [], 4, function (array $item) use ($ids) {
                $related = $this->ids($item['report_ids'] ?? [], $ids);

                return count($related) < 2 ? null : [
                    'report_ids' => $related,
                    'relation' => in_array($item['relation'] ?? null, self::RELATIONS, true) ? $item['relation'] : 'possibly related',
                    'evidence' => $this->strings($item['evidence'] ?? [], 3),
                    'confidence' => $this->confidence($item['confidence'] ?? null),
                ];
            }),
            'hypotheses' => $this->items($reply['hypotheses'], 3, fn (array $item) => is_string($item['statement'] ?? null) && trim($item['statement']) !== '' ? [
                'statement' => Str::limit(strip_tags($item['statement']), 250),
                'supporting_report_ids' => $this->ids($item['supporting_report_ids'] ?? [], $ids),
                'evidence' => $this->strings($item['evidence'] ?? [], 3),
                'confidence' => $this->confidence($item['confidence'] ?? null),
            ] : null),
            'uncertainties' => $this->strings($reply['uncertainties'] ?? [], 4),
            'next_steps' => $this->strings($reply['next_steps'] ?? [], 5),
        ] + $result;
    }

    /**
     * Cosine similarity of each pair, from cached vectors only (no new AI call here).
     *
     * @param  Collection<int, ResidentReport>  $reports
     * @return list<array{0: int, 1: int, 2: float}>
     */
    private function measuredSimilarities(Collection $reports): array
    {
        $withVectors = $reports->filter(fn (ResidentReport $report) => is_array($report->embedding))->values();
        $pairs = [];

        foreach ($withVectors as $i => $first) {
            foreach ($withVectors->slice($i + 1) as $second) {
                $pairs[] = [$first->id, $second->id, round(ReportEmbeddingService::cosine($first->embedding, $second->embedding), 3)];
            }
        }

        return $pairs;
    }

    /**
     * Response cases already handling any of these reports, so the investigation leads to the existing response
     * (only cases the viewer may open are listed).
     *
     * @param  list<int>  $reportIds
     * @return list<array{id: int, title: string, stage: string, tone: string, url: string, report_ids: list<int>}>
     */
    private function cases(User $viewer, array $reportIds): array
    {
        return ResponseCase::manageableBy($viewer)
            ->with(['reports' => fn ($query) => $query->whereKey($reportIds)->select('resident_reports.id')])
            ->whereHas('reports', fn ($query) => $query->whereKey($reportIds))
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (ResponseCase $case) => [
                'id' => $case->id,
                'title' => $case->title,
                'stage' => $case->stage->label(),
                'tone' => $case->stage->tone(),
                'url' => route('response-cases.show', $case),
                'report_ids' => $case->reports->modelKeys(),
            ])->all();
    }

    /**
     * @param  mixed  $items
     * @return list<array<string, mixed>>
     */
    private function items($items, int $limit, callable $map): array
    {
        return collect(is_array($items) ? $items : [])->filter(fn ($item) => is_array($item))->map($map)->filter()->take($limit)->values()->all();
    }

    /**
     * Only ids that belong to the authorized selection.
     *
     * @param  mixed  $values
     * @param  list<int>  $allowed
     * @return list<int>
     */
    private function ids($values, array $allowed): array
    {
        return collect(is_array($values) ? $values : [])
            ->filter(fn ($id) => is_numeric($id) && in_array((int) $id, $allowed, true))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * @param  mixed  $values
     * @return list<string>
     */
    private function strings($values, int $limit): array
    {
        return collect(is_array($values) ? $values : [])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => Str::limit(trim(strip_tags($value)), 240))
            ->take($limit)->values()->all();
    }

    private function confidence(mixed $value): string
    {
        return in_array($value, self::CONFIDENCE, true) ? $value : 'low';
    }
}
