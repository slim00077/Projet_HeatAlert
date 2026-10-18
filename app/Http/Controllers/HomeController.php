<?php

namespace App\Http\Controllers;

use App\Models\Advice;
use App\Models\CoolSpot;
use App\Models\HeatAlert;
use App\Models\PowerOutage;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $activeAlerts = HeatAlert::with('neighborhood')
            ->where('start_date', '<=', now())
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->latest('start_date');

        return view('front.home', [
            'activeAlertsCount' => (clone $activeAlerts)->count(),
            'highRiskCount' => (clone $activeAlerts)->where('severity_level', 'high')->count(),
            'latestAlerts' => (clone $activeAlerts)->limit(3)->get(),
            'coolSpotsCount' => CoolSpot::where('is_active', true)->count(),
            'coolSpots' => CoolSpot::with('type')->where('is_active', true)->latest()->limit(3)->get(),
            'outagesCount' => PowerOutage::where('status', '!=', 'resolved')->count(),
            'adviceCount' => Advice::count(),
            'advices' => Advice::with('category')->latest()->limit(3)->get(),
        ]);
    }
}
