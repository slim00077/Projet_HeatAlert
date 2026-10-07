<?php

namespace App\Http\Controllers;

use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Http\Request;

class FrontHeatAlertController extends Controller
{
    public function index(Request $request)
    {
        $query = HeatAlert::with('neighborhood')->where('end_date', '>=', now())->orWhereNull('end_date');
        
        if ($request->has('neighborhood_id') && $request->neighborhood_id != '') {
            $query->where('neighborhood_id', $request->neighborhood_id);
        }

        $alerts = $query->orderBy('start_date', 'asc')->get();
        $neighborhoods = Neighborhood::all();

        return view('front.alerts.index', compact('alerts', 'neighborhoods'));
    }
}
