<?php

namespace App\Http\Controllers;

use App\Enums\Severity;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use App\Models\Residence;
use App\Models\ResidentReport;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
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

        return view('back.dashboard', compact('stats', 'recentReports', 'alertStats', 'mostAffected', 'monthlyAlerts'));
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
