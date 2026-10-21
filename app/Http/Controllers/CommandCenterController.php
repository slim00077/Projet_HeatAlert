<?php

namespace App\Http\Controllers;

use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\ResponseUpdate;
use App\Services\ConstellationService;
use App\Services\ResponseCaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Incident Command Center home: the workflow board, response performance and the AI Incident Constellation map
 * for managers and admins. Residents follow their own cases from My Reports.
 */
class CommandCenterController extends Controller
{
    private const MAX_TRIAGE = 60;

    public function __invoke(Request $request, ResponseCaseService $service): View|RedirectResponse
    {
        $user = $request->user();

        // Residents follow their cases from My Reports.
        if ($user->isResident()) {
            return redirect()->route('resident-reports.index');
        }

        abort_unless($user->isManager() || $user->isAdmin(), 403);

        $cases = ResponseCase::manageableBy($user)
            ->with(['residence', 'tasks.assignee', 'reports', 'confirmations'])
            ->withMax('updates', 'created_at')
            ->latest()
            ->get();

        $triage = ResidentReport::visibleTo($user)
            ->unresolved()
            ->whereHas('residence', fn (Builder $residence) => $residence->approved())
            ->whereDoesntHave('responseCases', fn (Builder $case) => $case->open())
            ->with(['residence', 'user'])
            ->latest('reported_at')
            ->limit(self::MAX_TRIAGE)
            ->get()
            ->groupBy('residence_id');

        $recentActivity = ResponseUpdate::whereIn('response_case_id', $cases->modelKeys())
            ->with(['author', 'responseCase'])
            ->latest()
            ->limit(8)
            ->get();

        return view(($user->isAdmin() ? 'back' : 'front').'.command-center.index', [
            'cases' => $cases,
            'triage' => $triage,
            'overview' => $service->overview($cases),
            'recentActivity' => $recentActivity,
            // The AI Incident Constellation is embedded as the "Incident map" tab.
            'residences' => Residence::accessibleBy($user)->orderBy('name')->get(['id', 'name']),
            'periods' => ConstellationService::PERIODS,
            'focus' => $request->integer('focus') ?: null,
            'tab' => $request->query('tab') === 'map' || $request->integer('focus') ? 'map' : 'board',
        ]);
    }
}
