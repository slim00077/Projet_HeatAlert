<?php

namespace App\Services;

use App\Enums\ResponseStage;
use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\ResponseTask;
use App\Models\ResponseUpdate;
use App\Models\User;
use App\Notifications\ResponseCaseUpdated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Incident Command Center workflow: opening cases, linking reports, moving stages, tasks, the activity
 * timeline and resident notifications. Linked resident reports are read, never modified.
 */
class ResponseCaseService
{
    public const MAX_SUGGESTIONS = 8;

    public function __construct(private readonly ConstellationService $constellation) {}

    /**
     * @param  array{title: string, summary?: ?string, priority: string, target_date?: ?string}  $data
     * @param  Collection<int, ResidentReport>  $reports
     */
    public function open(User $actor, Residence $residence, array $data, Collection $reports, string $source = 'manual'): ResponseCase
    {
        $case = DB::transaction(function () use ($actor, $residence, $data, $reports, $source) {
            $case = $residence->responseCases()->make($data);
            $case->created_by = $actor->id;
            $case->save();

            $case->reports()->attach($reports->modelKeys(), ['linked_by' => $actor->id, 'source' => $source]);

            $this->log($case, $actor, ResponseUpdate::KIND_CREATED, 'Response case opened for '.$reports->count().' related report(s).', [
                'source' => $source,
                'report_ids' => $reports->modelKeys(),
            ], true);

            return $case;
        });

        $this->notifyResidents($case, $actor, 'Your report is now handled in the response case "'.$case->title.'".');

        return $case;
    }

    /**
     * @param  Collection<int, ResidentReport>  $reports
     */
    public function link(User $actor, ResponseCase $case, Collection $reports, string $source = 'manual'): void
    {
        $case->reports()->syncWithoutDetaching(
            $reports->mapWithKeys(fn (ResidentReport $report) => [$report->id => ['linked_by' => $actor->id, 'source' => $source]])->all()
        );

        $this->log($case, $actor, ResponseUpdate::KIND_LINK, 'Linked '.$reports->count().' related report(s)'.($source === 'constellation' ? ' suggested by the AI Incident Constellation' : '').': '
            .$reports->map(fn (ResidentReport $report) => '#'.$report->id.' '.Str::limit($report->title, 60))->implode(', ').'.', ['report_ids' => $reports->modelKeys(), 'source' => $source]);

        $this->notifyResidents($case, $actor, 'Your report was added to the response case "'.$case->title.'".', $reports);
    }

    public function unlink(User $actor, ResponseCase $case, ResidentReport $report): void
    {
        $case->reports()->detach($report->id);

        $this->log($case, $actor, ResponseUpdate::KIND_LINK, 'Removed report #'.$report->id.' "'.Str::limit($report->title, 60).'" from this case. The report itself is unchanged.', ['report_ids' => [$report->id], 'removed' => true]);
    }

    public function moveTo(User $actor, ResponseCase $case, ResponseStage $stage, ?string $note): void
    {
        $from = $case->stage;

        $case->update(['stage' => $stage]);

        $body = 'Status moved from '.$from->label().' to '.$stage->label().'.'.($note ? ' '.trim($note) : '');

        $this->log($case, $actor, ResponseUpdate::KIND_STAGE, $body, ['from' => $from->value, 'to' => $stage->value, 'tone' => $stage->tone()], true);

        $this->notifyResidents($case, $actor, $stage->label().': '.$stage->description().($note ? ' '.trim($note) : ''));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addTask(User $actor, ResponseCase $case, array $data): ResponseTask
    {
        $task = $case->tasks()->make($data);
        $task->created_by = $actor->id;
        $task->save();

        $this->log($case, $actor, ResponseUpdate::KIND_TASK, 'Task added: "'.$task->title.'"'
            .($task->assigneeName() ? ' assigned to '.$task->assigneeName() : '')
            .($task->due_date ? ', due '.$task->due_date->format('Y-m-d') : '').'.', ['task_id' => $task->id]);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTask(User $actor, ResponseTask $task, array $data): void
    {
        $wasDone = $task->isDone();
        $previousProgress = $task->progress;

        $task->update($data);

        if ($task->isDone() !== $wasDone || $task->progress !== $previousProgress) {
            $this->log($task->responseCase, $actor, ResponseUpdate::KIND_TASK, match (true) {
                $task->isDone() && ! $wasDone => 'Task completed: "'.$task->title.'".',
                ! $task->isDone() && $wasDone => 'Task reopened: "'.$task->title.'" ('.$task->progress.'%).',
                default => 'Task "'.$task->title.'" progressed to '.$task->progress.'%.',
            }, ['task_id' => $task->id, 'progress' => $task->progress]);
        }
    }

    public function postUpdate(User $actor, ResponseCase $case, string $body, bool $public): ResponseUpdate
    {
        $update = $this->log($case, $actor, ResponseUpdate::KIND_NOTE, trim($body), null, $public);

        if ($public) {
            $this->notifyResidents($case, $actor, $update->body);
        }

        return $update;
    }

    /**
     * An internal note becomes visible to the residents once the manager approves it for publication.
     */
    public function publish(User $actor, ResponseUpdate $update): void
    {
        $update->update(['is_public' => true]);

        $this->notifyResidents($update->responseCase, $actor, $update->body);
    }

    public function confirm(User $resident, ResponseCase $case, ResidentReport $report, bool $resolved, ?string $comment): void
    {
        $case->confirmations()->updateOrCreate(
            ['resident_report_id' => $report->id],
            ['user_id' => $resident->id, 'is_resolved' => $resolved, 'comment' => $comment],
        );

        $this->log($case, $resident, ResponseUpdate::KIND_CONFIRMATION, ($resolved
            ? 'The resident of report #'.$report->id.' confirmed the issue appears resolved.'
            : 'The resident of report #'.$report->id.' says the issue still appears.').($comment ? ' "'.Str::limit(trim($comment), 400).'"' : ''), ['resident_report_id' => $report->id, 'resolved' => $resolved]);
    }

    /**
     * Unresolved reports of a residence that no open case handles yet.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ResidentReport>
     */
    public function awaitingTriage(Residence $residence): \Illuminate\Database\Eloquent\Collection
    {
        return $residence->residentReports()
            ->unresolved()
            ->whereDoesntHave('responseCases', fn (Builder $case) => $case->open())
            ->with('user')
            ->latest('reported_at')
            ->get();
    }

    /**
     * Ids among the given reports that an open case (other than $except) already handles.
     *
     * @param  list<int>  $reportIds
     * @return list<int>
     */
    public function alreadyHandled(array $reportIds, ?ResponseCase $except = null): array
    {
        return DB::table('response_case_report')
            ->join('response_cases', 'response_cases.id', '=', 'response_case_report.response_case_id')
            ->whereIn('resident_report_id', $reportIds)
            ->where('response_cases.stage', '!=', ResponseStage::Resolved->value)
            ->when($except, fn ($query) => $query->where('response_cases.id', '!=', $except->id))
            ->pluck('resident_report_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Related open reports proposed by the AI Incident Constellation (semantic and pattern links of the seed reports).
     * Nothing is linked here: the manager reviews and confirms each suggestion.
     *
     * @param  list<int>  $seedIds
     * @return array{suggestions: list<array<string, mixed>>, semantic: array<string, mixed>}
     */
    public function suggestions(User $viewer, Residence $residence, array $seedIds, ?ResponseCase $case = null): array
    {
        $graph = $this->constellation->graph($viewer, ['residence_id' => $residence->id, 'days' => 365, 'cross' => false]);

        $nodes = collect($graph['nodes'])->keyBy('id');
        $blocked = array_flip([...$seedIds, ...$this->alreadyHandled($nodes->keys()->all(), $case)]);
        $found = [];

        foreach ($graph['edges'] as $edge) {
            foreach ([[$edge['source'], $edge['target']], [$edge['target'], $edge['source']]] as [$seed, $other]) {
                if (! in_array($seed, $seedIds, true) || isset($blocked[$other]) || ! ($nodes[$other]['open'] ?? false)) {
                    continue;
                }

                $strength = ($edge['similarity'] ?? $edge['keyword_score'] ?? 0) + ($edge['pattern'] ? 0.05 : 0);

                if (! isset($found[$other]) || $strength > $found[$other]['strength']) {
                    $found[$other] = [
                        'strength' => $strength,
                        'evidence' => collect([
                            $edge['semantic'] ? 'AI semantic similarity '.round($edge['similarity'] * 100).'%' : null,
                            $edge['keyword'] ? 'keyword match ('.$edge['keyword_shared'].')' : null,
                            $edge['pattern'] ? 'same category, '.$edge['gap_days'].' days apart' : null,
                        ])->filter()->implode(' + '),
                        'related_to' => $nodes[$seed]['title'] ?? null,
                    ];
                }
            }
        }

        uasort($found, fn (array $a, array $b) => $b['strength'] <=> $a['strength']);

        return [
            'suggestions' => collect($found)->take(self::MAX_SUGGESTIONS)->map(fn (array $match, int $id) => [
                'id' => $id,
                'title' => $nodes[$id]['title'],
                'category' => $nodes[$id]['category_label'],
                'status' => $nodes[$id]['status'],
                'reported_at' => $nodes[$id]['reported_at'],
                'excerpt' => $nodes[$id]['excerpt'],
                'evidence' => $match['evidence'],
                'related_to' => $match['related_to'],
                'url' => $nodes[$id]['url'],
            ])->values()->all(),
            'semantic' => $graph['semantic'],
        ];
    }

    /**
     * Performance overview of the cases a staff member manages: workflow funnel, aging, overdue tasks and timings.
     *
     * @param  Collection<int, ResponseCase>  $cases  with tasks, reports and residence loaded
     * @return array<string, mixed>
     */
    public function overview(Collection $cases): array
    {
        $open = $cases->filter->isOpen();
        $resolved = $cases->reject->isOpen()->filter(fn (ResponseCase $case) => $case->resolved_at !== null && $case->resolved_at->gte(now()->subDays(90)));
        $tasks = $open->flatMap->tasks;
        $confirmations = $cases->flatMap->confirmations;

        $buckets = ['0-3 days' => [0, 3], '4-7 days' => [4, 7], '8-14 days' => [8, 14], '15+ days' => [15, PHP_INT_MAX]];

        return [
            'stages' => collect(ResponseStage::cases())->map(fn (ResponseStage $stage) => [
                'stage' => $stage,
                'count' => $cases->filter(fn (ResponseCase $case) => $case->stage === $stage)->count(),
            ])->all(),
            'open' => $open->count(),
            'resolved_recently' => $resolved->count(),
            'avg_resolution_days' => $resolved->isEmpty() ? null : round($resolved->avg(fn (ResponseCase $case) => $case->ageInDays()), 1),
            'avg_response_days' => $cases->isEmpty() ? null : round($cases->avg(fn (ResponseCase $case) => $case->reports->isEmpty()
                ? 0 : max(0, $case->reports->min('reported_at')->copy()->startOfDay()->diffInDays($case->created_at->copy()->startOfDay()))), 1),
            'aging' => collect($buckets)->map(fn (array $range) => $open->filter(fn (ResponseCase $case) => $case->ageInDays() >= $range[0] && $case->ageInDays() <= $range[1])->count())->all(),
            'overdue_tasks' => $tasks->filter->isOverdue()->sortBy('due_date')->values(),
            'tasks_total' => $tasks->count(),
            'tasks_done' => $tasks->filter->isDone()->count(),
            'confirmed' => $confirmations->where('is_resolved', true)->count(),
            'disputed' => $confirmations->where('is_resolved', false)->count(),
            'unresolved' => $open->sortByDesc(fn (ResponseCase $case) => $case->ageInDays())->values(),
            'residences' => $open->groupBy('residence_id')->map(fn (Collection $group) => [
                'residence' => $group->first()->residence,
                'open' => $group->count(),
                'overdue_tasks' => $group->flatMap->tasks->filter->isOverdue()->count(),
            ])->sortByDesc('open')->values(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function log(ResponseCase $case, User $actor, string $kind, string $body, ?array $meta = null, bool $public = false): ResponseUpdate
    {
        return $case->updates()->create([
            'user_id' => $actor->id,
            'kind' => $kind,
            'body' => $body,
            'meta' => $meta,
            'is_public' => $public,
        ]);
    }

    /**
     * Notifies the authors of the case's reports (or of the given reports only), never the person acting.
     *
     * @param  Collection<int, ResidentReport>|null  $reports
     */
    private function notifyResidents(ResponseCase $case, User $actor, string $message, ?Collection $reports = null): void
    {
        ($reports ?? $case->reports()->with('user')->get())
            ->filter(fn (ResidentReport $report) => $report->user !== null && $report->user_id !== $actor->id && $report->user->isResident())
            ->unique('user_id')
            ->each(fn (ResidentReport $report) => $report->user->notify(new ResponseCaseUpdated($case, $report, $message)));
    }
}
