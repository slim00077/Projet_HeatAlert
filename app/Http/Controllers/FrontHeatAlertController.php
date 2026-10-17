<?php

namespace App\Http\Controllers;

use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FrontHeatAlertController extends Controller
{
    public function index(Request $request): View
    {
        $neighborhoodId = $request->integer('neighborhood_id') ?: null;

        $alerts = HeatAlert::query()
            ->with('neighborhood')
            ->currentOrUpcoming()
            ->when($neighborhoodId, fn ($query, int $id) => $query->where('neighborhood_id', $id))
            ->orderBy('start_date')
            ->get();

        return view('front.alerts.index', [
            'alerts' => $alerts,
            'neighborhoods' => Neighborhood::orderBy('name')->get(),
            'neighborhoodId' => $neighborhoodId,
        ]);
    }
}
