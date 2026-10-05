<?php

namespace App\Http\Controllers;

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
            'Total Reports' => ResidentReport::count(),
            'Pending Reports' => ResidentReport::where('status', 'Pending')->count(),
            'In Progress Reports' => ResidentReport::where('status', 'In Progress')->count(),
            'Resolved Reports' => ResidentReport::where('status', 'Resolved')->count(),
        ];

        $recentReports = ResidentReport::with(['residence', 'user'])->latest('reported_at')->latest('id')->limit(5)->get();

        return view('back.dashboard', compact('stats', 'recentReports'));
    }
}
