<?php

namespace App\Services;

use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the Incident Constellation: a graph of real reports in the viewer's scope.
 *
 * Two kinds of links, always labelled:
 * - "semantic": Gemini embedding cosine similarity >= the configured threshold (each report keeps its strongest links only);
 * - "pattern": a rule, same residence + same category reported within PATTERN_WINDOW_DAYS days of each other;
 * - "keyword": basic word overlap (KeywordMatcher), only used as a labelled fallback when embeddings are unavailable.
 */
class ConstellationService
{
    public const PATTERN_WINDOW_DAYS = 21;

    private const MAX_NODES = 300;

    private const MAX_SEMANTIC_LINKS_PER_REPORT = 4;

    private const MAX_KEYWORD_LINKS_PER_REPORT = 3;

    private const KEYWORD_THRESHOLD = 0.3;

    public const PERIODS = [30, 90, 180, 365, 0];

    /**
     * Category key => [label, colour]. Colours reuse the theme palette.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const PALETTE = [
        'cooling' => ['Cooling / air conditioning', '#3b82f6'],
        'power' => ['Electricity / power', '#f59e0b'],
        'water' => ['Water supply', '#14b8a6'],
        'elevator' => ['Elevator / access', '#8b5cf6'],
        'ventilation' => ['Ventilation / shade', '#10b981'],
        'building' => ['Building / structure', '#e84b4b'],
        'safety' => ['Health & safety', '#ff6b6b'],
        'other' => ['Other', '#94a3b8'],
        'none' => ['Uncategorized', '#64748b'],
    ];

    public function __construct(private readonly ReportEmbeddingService $embeddings, private readonly KeywordMatcher $keywords) {}

    /**
     * @param  array{residence_id?: ?int, days?: int, cross?: bool}  $filters
     * @return array<string, mixed>
     */
    public function graph(User $viewer, array $filters): array
    {
        $days = (int) ($filters['days'] ?? 180);
        $cross = (bool) ($filters['cross'] ?? false);

        $reports = ResidentReport::query()
            ->visibleTo($viewer)
            ->with(['residence:id,name', 'responseCases.residence'])
            ->when($filters['residence_id'] ?? null, fn ($query, $id) => $query->where('residence_id', $id))
            ->when($days > 0, fn ($query) => $query->whereDate('reported_at', '>=', today()->subDays($days)->toDateString()))
            ->latest('reported_at')
            ->limit(self::MAX_NODES)
            ->get();

        $vectors = $reports->isEmpty() ? [] : $this->embeddings->vectorsFor($reports);
        $semantic = $vectors === null ? [] : $this->semanticLinks($reports, $vectors, $cross);
        $pattern = $this->patternLinks($reports);
        $keyword = $vectors === null ? $this->keywordLinks($reports, $cross) : [];

        $edges = $this->mergeLinks($semantic, $pattern, $keyword);

        return [
            'nodes' => $reports->map(fn (ResidentReport $report) => $this->node($report, $viewer))->values()->all(),
            'edges' => $edges,
            'legend' => collect(self::PALETTE)->map(fn (array $entry, string $key) => ['key' => $key, 'label' => $entry[0], 'color' => $entry[1]])->values()->all(),
            'semantic' => [
                'available' => $vectors !== null,
                'model' => $vectors !== null ? $this->embeddings->model() : null,
                'threshold' => $this->threshold(),
                'notice' => $vectors === null && $reports->isNotEmpty()
                    ? $this->embeddings->failureNotice().'. Only rule-based pattern links (same category within '.self::PATTERN_WINDOW_DAYS.' days) and basic keyword matches are shown; semantic links need Gemini embeddings.'
                    : null,
            ],
            'stats' => [
                'reports' => $reports->count(),
                'semantic_links' => collect($edges)->where('semantic', true)->count(),
                'pattern_links' => collect($edges)->where('pattern', true)->count(),
                'keyword_links' => collect($edges)->where('keyword', true)->count(),
                'truncated' => $reports->count() === self::MAX_NODES,
            ],
            'filters' => ['days' => $days, 'cross' => $cross, 'residence_id' => $filters['residence_id'] ?? null],
        ];
    }

    public function threshold(): float
    {
        return (float) config('services.gemini.similarity_threshold', 0.895);
    }

    /**
     * @return array<string, mixed>
     */
    private function node(ResidentReport $report, User $viewer): array
    {
        $category = $report->category ?? 'none';

        return [
            'id' => $report->id,
            'title' => $report->title,
            'category' => array_key_exists($category, self::PALETTE) ? $category : 'other',
            'category_label' => $report->categoryLabel(),
            'status' => $report->status,
            'open' => ! $report->isResolved(),
            'overdue' => $report->isOverdue(),
            'reported_at' => $report->reported_at->format('Y-m-d'),
            'resolved_at' => $report->resolved_at?->format('Y-m-d'),
            'days_open' => $report->daysOpen(),
            'residence_id' => $report->residence_id,
            'residence' => $report->residence->name,
            'excerpt' => Str::limit($report->description, 160),
            'resolution' => $report->resolution_notes ? Str::limit($report->resolution_notes, 200) : null,
            'case' => $this->caseOf($report, $viewer),
            'has_photo' => $report->hasPhoto(),
            'ai_assisted' => $report->ai_assisted,
            'url' => $report->urlFor($viewer),
        ];
    }

    /**
     * Each report keeps its strongest semantic neighbours above the threshold.
     *
     * @param  Collection<int, ResidentReport>  $reports
     * @param  array<int, list<float>>  $vectors
     * @return array<string, float> "a-b" => cosine
     */
    private function semanticLinks(Collection $reports, array $vectors, bool $cross): array
    {
        $threshold = $this->threshold();
        $candidates = [];

        foreach ($reports as $first) {
            foreach ($reports as $second) {
                if ($first->id >= $second->id || (! $cross && $first->residence_id !== $second->residence_id)) {
                    continue;
                }

                $score = ReportEmbeddingService::cosine($vectors[$first->id], $vectors[$second->id]);

                if ($score >= $threshold) {
                    $candidates[$first->id][$second->id] = $score;
                    $candidates[$second->id][$first->id] = $score;
                }
            }
        }

        $links = [];

        foreach ($candidates as $id => $neighbours) {
            arsort($neighbours);

            foreach (array_slice($neighbours, 0, self::MAX_SEMANTIC_LINKS_PER_REPORT, true) as $other => $score) {
                $links[min($id, $other).'-'.max($id, $other)] = round($score, 3);
            }
        }

        return $links;
    }

    /**
     * @param  Collection<int, ResidentReport>  $reports
     * @return array<string, int> "a-b" => days between the two reports
     */
    private function patternLinks(Collection $reports): array
    {
        $links = [];

        foreach ($reports->filter(fn (ResidentReport $report) => $report->category !== null && $report->category !== 'other')->groupBy(fn ($report) => $report->residence_id.'|'.$report->category) as $group) {
            $sorted = $group->sortBy('reported_at')->values();

            // Chain consecutive reports of the group that are close in time, rather than linking every pair.
            for ($index = 1; $index < $sorted->count(); $index++) {
                $gap = (int) $sorted[$index - 1]->reported_at->diffInDays($sorted[$index]->reported_at);

                if ($gap <= self::PATTERN_WINDOW_DAYS) {
                    $a = $sorted[$index - 1]->id;
                    $b = $sorted[$index]->id;
                    $links[min($a, $b).'-'.max($a, $b)] = $gap;
                }
            }
        }

        return $links;
    }

    /**
     * Basic word-overlap links, each report keeping its strongest few. Never mixed with AI semantic links.
     *
     * @param  Collection<int, ResidentReport>  $reports
     * @return array<string, array{score: float, shared: string}> "a-b" => score and what the two reports share
     */
    private function keywordLinks(Collection $reports, bool $cross): array
    {
        $profiles = $reports->mapWithKeys(fn (ResidentReport $report) => [$report->id => [
            'terms' => $this->keywords->terms($report->semanticText()),
            'concepts' => $this->keywords->concepts($report->semanticText()),
        ]]);

        $candidates = [];

        foreach ($reports as $first) {
            foreach ($reports as $second) {
                if ($first->id >= $second->id || (! $cross && $first->residence_id !== $second->residence_id)) {
                    continue;
                }

                $a = $profiles[$first->id];
                $b = $profiles[$second->id];
                $terms = array_values(array_intersect($a['terms'], $b['terms']));
                $concepts = array_values(array_intersect($a['concepts'], $b['concepts']));
                $union = count(array_unique(array_merge($a['terms'], $b['terms'])));
                $score = min(1.0, ($union > 0 ? count($terms) / $union : 0.0) + ($concepts !== [] ? 0.25 : 0.0));

                if ($score >= self::KEYWORD_THRESHOLD && ($concepts !== [] || count($terms) >= 2)) {
                    $link = ['score' => round($score, 2), 'shared' => $concepts !== [] ? 'both about '.implode(' and ', $concepts) : 'shared words: '.implode(', ', array_slice($terms, 0, 4))];
                    $candidates[$first->id][$second->id] = $link;
                    $candidates[$second->id][$first->id] = $link;
                }
            }
        }

        $links = [];

        foreach ($candidates as $id => $neighbours) {
            uasort($neighbours, fn (array $x, array $y) => $y['score'] <=> $x['score']);

            foreach (array_slice($neighbours, 0, self::MAX_KEYWORD_LINKS_PER_REPORT, true) as $other => $link) {
                $links[min($id, $other).'-'.max($id, $other)] = $link;
            }
        }

        return $links;
    }

    /**
     * The open response case already handling the report, shown only to staff who may open it.
     *
     * @return array{id: int, title: string, stage: string, url: string}|null
     */
    private function caseOf(ResidentReport $report, User $viewer): ?array
    {
        $case = $report->responseCases->first(fn (ResponseCase $case) => $case->isOpen());

        return $case === null || ! $viewer->can('view', $case)
            ? null
            : ['id' => $case->id, 'title' => $case->title, 'stage' => $case->stage->label(), 'url' => route('response-cases.show', $case)];
    }

    /**
     * @param  array<string, float>  $semantic
     * @param  array<string, int>  $pattern
     * @param  array<string, array{score: float, shared: string}>  $keyword
     * @return list<array<string, mixed>>
     */
    private function mergeLinks(array $semantic, array $pattern, array $keyword): array
    {
        return collect(array_unique([...array_keys($semantic), ...array_keys($pattern), ...array_keys($keyword)]))
            ->map(function (string $key) use ($semantic, $pattern, $keyword) {
                [$source, $target] = array_map('intval', explode('-', $key));

                return [
                    'source' => $source,
                    'target' => $target,
                    'semantic' => isset($semantic[$key]),
                    'similarity' => $semantic[$key] ?? null,
                    'pattern' => isset($pattern[$key]),
                    'gap_days' => $pattern[$key] ?? null,
                    'keyword' => isset($keyword[$key]),
                    'keyword_score' => $keyword[$key]['score'] ?? null,
                    'keyword_shared' => $keyword[$key]['shared'] ?? null,
                ];
            })
            ->values()
            ->all();
    }
}
