<?php

namespace App\Http\Controllers;

use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\User;
use App\Services\ConstellationService;
use App\Services\IncidentInvestigationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AI Incident Constellation (shown in the Command Center): interactive graph of real reports for residence managers (their residences)
 * and administrators (every residence, including cross-residence patterns). Residents have no access.
 */
class ConstellationController extends Controller
{
    /**
     * The incident map lives inside the Command Center; old links land on its "Incident map" tab.
     */
    public function index(Request $request): RedirectResponse
    {
        $this->staff($request);

        return redirect()->route('command-center.index', array_filter(['tab' => 'map', 'focus' => $request->integer('focus') ?: null]));
    }

    public function graph(Request $request, ConstellationService $constellation): JsonResponse
    {
        $user = $this->staff($request);

        $data = $request->validate([
            'residence_id' => ['nullable', 'integer', Rule::in(Residence::accessibleBy($user)->pluck('id')->all())],
            'days' => ['nullable', 'integer', Rule::in(ConstellationService::PERIODS)],
            'cross' => ['nullable', 'boolean'],
        ]);

        return response()->json($constellation->graph($user, [
            'residence_id' => isset($data['residence_id']) ? (int) $data['residence_id'] : null,
            'days' => (int) ($data['days'] ?? 180),
            'cross' => (bool) ($data['cross'] ?? false),
        ]));
    }

    public function investigate(Request $request, IncidentInvestigationService $investigator): JsonResponse
    {
        $user = $this->staff($request);

        $data = $request->validate([
            'report_ids' => ['required', 'array', 'min:2', 'max:15'],
            'report_ids.*' => ['integer', 'distinct'],
        ]);

        // Every id must be a real report inside the viewer's scope; otherwise nothing is sent to the AI.
        $reports = ResidentReport::visibleTo($user)->whereKey($data['report_ids'])->get();

        if ($reports->count() !== count($data['report_ids'])) {
            throw ValidationException::withMessages(['report_ids' => 'Some selected reports do not exist or are outside your residences.']);
        }

        set_time_limit(150);

        return response()->json($investigator->investigate($user, $reports));
    }

    private function staff(Request $request): User
    {
        $user = $request->user();

        abort_unless($user->isManager() || $user->isAdmin(), 403, 'The Incident Constellation is available to residence managers and administrators.');

        return $user;
    }
}
