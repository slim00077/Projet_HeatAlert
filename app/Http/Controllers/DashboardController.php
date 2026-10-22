<?php

namespace App\Http\Controllers;

use App\Enums\ResponseStage;
use App\Enums\Severity;
use App\Models\Advice;
use App\Models\CoolSpot;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use App\Models\OutageReport;
use App\Models\PowerOutage;
use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\ResponseCase;
use App\Models\ResponseUpdate;
use App\Models\User;
use App\Services\ResponseCaseService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ResponseCaseService $responseCases): View
    {
        $cases = ResponseCase::with(['residence', 'tasks', 'reports', 'confirmations'])->get();
        $overview = $responseCases->overview($cases);

        $commandCenterStats = [
            'open' => $overview['open'],
            'verification' => $cases->filter(fn (ResponseCase $case) => $case->stage === ResponseStage::Verification)->count(),
            'overdueTasks' => $overview['overdue_tasks']->count(),
            'avgResolution' => $overview['avg_resolution_days'],
            'oldest' => $overview['unresolved']->take(5),
        ];

        $stats = [
            'Total Users' => User::count(),
            'Total Residents' => User::where('role', User::ROLE_RESIDENT)->count(),
            'Total Residences' => Residence::count(),
            'Pending Residences' => Residence::pending()->count(),
            'Total Reports' => ResidentReport::count(),
            'Pending Reports' => ResidentReport::where('status', 'Pending')->count(),
            'In Progress Reports' => ResidentReport::where('status', 'In Progress')->count(),
            'Resolved Reports' => ResidentReport::where('status', 'Resolved')->count(),
        ];

        $recentReports = ResidentReport::with(['residence', 'user'])->latest('reported_at')->latest('id')->limit(5)->get();

        $activeAlerts = HeatAlert::active()->get();

        $alertStats = [
            'active' => $activeAlerts->count(),
            'high' => $activeAlerts->where('severity_level', Severity::High)->count(),
            'upcoming' => HeatAlert::upcoming()->count(),
            'bySeverity' => collect(Severity::cases())->mapWithKeys(
                fn (Severity $severity) => [$severity->value => $activeAlerts->where('severity_level', $severity)->count()]
            ),
        ];

        $mostAffected = Neighborhood::with('activeAlerts')
            ->get()
            ->filter(fn (Neighborhood $neighborhood) => $neighborhood->currentRisk() !== null)
            ->sortByDesc(fn (Neighborhood $neighborhood) => [$neighborhood->currentRisk()->weight(), $neighborhood->activeAlerts->count()])
            ->first();

        $monthlyAlerts = $this->alertsPerMonth(6);

        $commandCenterStats['stages'] = $overview['stages'];
        $commandCenterStats['tasksDone'] = $overview['tasks_done'];
        $commandCenterStats['tasksTotal'] = $overview['tasks_total'];
        $commandCenterStats['confirmed'] = $overview['confirmed'];
        $commandCenterStats['disputed'] = $overview['disputed'];

        $insights = [
            'reportsPerMonth' => $this->reportsPerMonth(6),
            'byCategory' => ResidentReport::selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category')->all(),
            'usersByRole' => User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role')->all(),
            'residencesByStatus' => Residence::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'busiestResidences' => Residence::approved()
                ->withCount(['residentReports as open_reports_count' => fn ($query) => $query->unresolved()])
                ->orderByDesc('open_reports_count')->limit(5)->get()->filter(fn (Residence $residence) => $residence->open_reports_count > 0),
            'riskyNeighborhoods' => Neighborhood::with('activeAlerts')->get()
                ->filter(fn (Neighborhood $neighborhood) => $neighborhood->currentRisk() !== null)
                ->sortByDesc(fn (Neighborhood $neighborhood) => [$neighborhood->currentRisk()->weight(), $neighborhood->activeAlerts->count()])
                ->take(5)->values(),
            'outagesByStatus' => PowerOutage::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'outageReports' => OutageReport::count(),
            'coolSpots' => CoolSpot::where('is_active', true)->count(),
            'advice' => Advice::count(),
            'overdueReports' => ResidentReport::overdue()->count(),
            'activity' => ResponseUpdate::with(['author', 'responseCase'])->latest()->limit(6)->get(),
        ];

        return view('back.dashboard', compact('stats', 'recentReports', 'alertStats', 'mostAffected', 'monthlyAlerts', 'commandCenterStats', 'insights'));
    }

    /**
     * @return array<string, int> Month label => reports filed that month, oldest first.
     */
    private function reportsPerMonth(int $months): array
    {
        $first = now()->startOfMonth()->subMonths($months - 1);

        $counts = ResidentReport::where('reported_at', '>=', $first->toDateString())
            ->pluck('reported_at')
            ->countBy(fn ($date) => $date->format('Y-m'));

        return collect(range(0, $months - 1))
            ->mapWithKeys(function (int $offset) use ($first, $counts) {
                $month = $first->copy()->addMonths($offset);

                return [$month->format('M') => $counts->get($month->format('Y-m'), 0)];
            })
            ->all();
    }

    /**
     * @return array<string, int> Month label => alerts started that month, oldest first.
     */
    private function alertsPerMonth(int $months): array
    {
        $first = now()->startOfMonth()->subMonths($months - 1);

        $counts = HeatAlert::where('start_date', '>=', $first)
            ->pluck('start_date')
            ->countBy(fn ($date) => $date->format('Y-m'));

        return collect(range(0, $months - 1))
            ->mapWithKeys(function (int $offset) use ($first, $counts) {
                $month = $first->copy()->addMonths($offset);

                return [$month->format('M Y') => $counts->get($month->format('Y-m'), 0)];
            })
            ->all();
    }
}
