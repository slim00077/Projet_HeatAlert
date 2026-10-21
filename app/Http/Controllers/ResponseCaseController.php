<?php

namespace App\Http\Controllers;

use App\Enums\ResponseStage;
use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\ResponseTask;
use App\Models\User;
use App\Services\IncidentLensService;
use App\Services\ResponseCaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Incident Command Center response cases: opening a case from related reports, the response room,
 * the workflow stage, linked reports and the AI assistance (Constellation and Lens).
 */
class ResponseCaseController extends Controller
{
    public function create(Request $request, Residence $residence, ResponseCaseService $service): View
    {
        Gate::authorize('create', [ResponseCase::class, $residence]);

        $available = $service->awaitingTriage($residence);
        $selected = collect((array) $request->query('report_ids', []))->map(fn ($id) => (int) $id)->intersect($available->modelKeys())->values();

        return view($this->folder($request).'.command-center.create', [
            'residence' => $residence,
            'available' => $available,
            'selected' => $selected->all(),
            'source' => $request->query('source') === 'constellation' ? 'constellation' : 'manual',
            'responseCase' => new ResponseCase([
                'title' => $selected->isNotEmpty() ? $available->firstWhere('id', $selected->first())->title : null,
                'priority' => 'medium',
            ]),
        ]);
    }

    public function store(Request $request, Residence $residence, ResponseCaseService $service): RedirectResponse
    {
        Gate::authorize('create', [ResponseCase::class, $residence]);

        $data = $request->validate($this->rules() + [
            'report_ids' => ['required', 'array', 'min:1', 'max:30'],
            'report_ids.*' => ['integer', 'distinct'],
            'source' => ['nullable', Rule::in(['manual', 'constellation'])],
        ], [
            'report_ids.required' => 'Select at least one report to handle in this case.',
        ]);

        $reports = $this->linkableReports($request->user(), $residence, $data['report_ids'], $service);

        $case = $service->open($request->user(), $residence, collect($data)->only(['title', 'summary', 'priority', 'target_date'])->all(), $reports, $data['source'] ?? 'manual');

        return redirect()->route('response-cases.show', $case)->with('success', 'Response case opened. The residents of the linked reports were notified.');
    }

    public function show(Request $request, ResponseCase $responseCase): View
    {
        Gate::authorize('view', $responseCase);

        $responseCase->load([
            'residence.user',
            'creator',
            'reports.user',
            'tasks' => fn ($query) => $query->with('assignee')->orderByRaw('status = ?', [ResponseTask::STATUS_DONE])->orderByRaw('due_date IS NULL')->orderBy('due_date'),
            'updates' => fn ($query) => $query->with('author')->latest()->latest('id'),
            'confirmations.user',
        ]);

        return view($this->folder($request).'.command-center.show', [
            'case' => $responseCase,
            'assignees' => $this->assignees($responseCase->residence),
        ]);
    }

    public function update(Request $request, ResponseCase $responseCase): RedirectResponse
    {
        Gate::authorize('update', $responseCase);

        $responseCase->update($request->validate($this->rules()));

        return back()->with('success', 'Case details saved.');
    }

    public function stage(Request $request, ResponseCase $responseCase, ResponseCaseService $service): RedirectResponse
    {
        Gate::authorize('update', $responseCase);

        $data = $request->validate([
            'stage' => ['required', Rule::enum(ResponseStage::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $stage = ResponseStage::from($data['stage']);

        if ($stage === $responseCase->stage) {
            return back()->with('error', 'The case is already '.$stage->label().'.');
        }

        if ($stage === ResponseStage::Resolved && $responseCase->tasks()->where('status', '!=', ResponseTask::STATUS_DONE)->exists()) {
            throw ValidationException::withMessages(['stage' => 'Complete or remove the open tasks before resolving this case.']);
        }

        $service->moveTo($request->user(), $responseCase, $stage, $data['note'] ?? null);

        return back()->with('success', 'Case moved to '.$stage->label().'. Residents were notified.');
    }

    public function linkReports(Request $request, ResponseCase $responseCase, ResponseCaseService $service): RedirectResponse
    {
        Gate::authorize('update', $responseCase);

        $data = $request->validate([
            'report_ids' => ['required', 'array', 'min:1', 'max:30'],
            'report_ids.*' => ['integer', 'distinct'],
            'source' => ['nullable', Rule::in(['manual', 'constellation'])],
        ], [
            'report_ids.required' => 'Tick at least one report to link.',
        ]);

        $ids = array_values(array_diff($data['report_ids'], $responseCase->reports()->pluck('resident_reports.id')->all()));

        if ($ids === []) {
            return back()->with('error', 'These reports are already linked to this case.');
        }

        $reports = $this->linkableReports($request->user(), $responseCase->residence, $ids, $service, $responseCase);

        $service->link($request->user(), $responseCase, $reports, $data['source'] ?? 'manual');

        return back()->with('success', $reports->count().' report(s) linked to this case.');
    }

    public function unlinkReport(Request $request, ResponseCase $responseCase, ResidentReport $residentReport, ResponseCaseService $service): RedirectResponse
    {
        Gate::authorize('update', $responseCase);

        abort_unless($responseCase->reports()->whereKey($residentReport->id)->exists(), 404);

        if ($responseCase->reports()->count() === 1) {
            return back()->with('error', 'A response case needs at least one report.');
        }

        $service->unlink($request->user(), $responseCase, $residentReport);

        return back()->with('success', 'Report removed from this case. The report itself was not changed.');
    }

    /**
     * AI Incident Constellation suggestions for a case being opened (seeded by the reports ticked on the form).
     */
    public function suggestForResidence(Request $request, Residence $residence, ResponseCaseService $service): JsonResponse
    {
        Gate::authorize('create', [ResponseCase::class, $residence]);

        $data = $request->validate([
            'report_ids' => ['required', 'array', 'min:1', 'max:30'],
            'report_ids.*' => ['integer'],
        ]);

        $seeds = $residence->residentReports()->whereKey($data['report_ids'])->pluck('id')->all();

        return response()->json($service->suggestions($request->user(), $residence, $seeds));
    }

    /**
     * AI Incident Constellation suggestions for an existing case (seeded by its linked reports).
     */
    public function suggestForCase(Request $request, ResponseCase $responseCase, ResponseCaseService $service): JsonResponse
    {
        Gate::authorize('update', $responseCase);

        $seeds = $responseCase->reports()->pluck('resident_reports.id')->map(fn ($id) => (int) $id)->all();

        return response()->json($service->suggestions($request->user(), $responseCase->residence, $seeds, $responseCase) + [
            'link_url' => route('response-cases.reports.store', $responseCase),
        ]);
    }

    /**
     * AI Incident Lens on the evidence of one linked report (its stored photo and description).
     */
    public function evidence(Request $request, ResponseCase $responseCase, ResidentReport $residentReport, IncidentLensService $lens): JsonResponse
    {
        Gate::authorize('view', $responseCase);

        abort_unless($responseCase->reports()->whereKey($residentReport->id)->exists(), 404);

        $photo = $residentReport->hasPhoto() && Storage::disk('local')->exists($residentReport->photo_path)
            ? new UploadedFile(Storage::disk('local')->path($residentReport->photo_path), basename($residentReport->photo_path), Storage::disk('local')->mimeType($residentReport->photo_path) ?: null, null, true)
            : null;

        set_time_limit(150);

        return response()->json($lens->analyze($residentReport->title, $residentReport->description, $photo));
    }

    private function folder(Request $request): string
    {
        return $request->user()->isAdmin() ? 'back' : 'front';
    }

    /**
     * Tasks are assigned to the residence's manager (or an external crew named in free text); residents are not assignees.
     *
     * @return Collection<int, User>
     */
    private function assignees(Residence $residence): Collection
    {
        return User::whereKey($residence->user_id)->orWhere('role', User::ROLE_ADMIN)->orderBy('name')->get();
    }

    /**
     * Reports of this residence, visible to the user, and not already handled by another open case.
     *
     * @param  list<int>  $ids
     * @return Collection<int, ResidentReport>
     */
    private function linkableReports(User $user, Residence $residence, array $ids, ResponseCaseService $service, ?ResponseCase $except = null): Collection
    {
        $reports = ResidentReport::visibleTo($user)->where('residence_id', $residence->id)->whereKey($ids)->get();

        if ($reports->count() !== count($ids)) {
            throw ValidationException::withMessages(['report_ids' => 'Some selected reports do not exist or do not belong to '.$residence->name.'.']);
        }

        if ($handled = $service->alreadyHandled($ids, $except)) {
            throw ValidationException::withMessages(['report_ids' => 'Report(s) #'.implode(', #', $handled).' are already handled by another open response case.']);
        }

        return $reports;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'priority' => ['required', Rule::in(array_keys(ResponseCase::PRIORITIES))],
            'target_date' => ['nullable', 'date'],
        ];
    }
}
