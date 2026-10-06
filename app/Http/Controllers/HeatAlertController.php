<?php

namespace App\Http\Controllers;

use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Http\Request;

class HeatAlertController extends Controller
{
    public function index()
    {
        $alerts = HeatAlert::with('neighborhood')->get();
        return view('admin.alerts.index', compact('alerts'));
    }

    public function create()
    {
        $neighborhoods = Neighborhood::all();
        return view('admin.alerts.create', compact('neighborhoods'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'neighborhood_id' => 'required|exists:neighborhoods,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'severity_level' => 'required|string|in:low,medium,high',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        HeatAlert::create($validated);
        return redirect()->route('admin.alerts.index')->with('success', 'Alerte chaleur créée avec succès.');
    }

    public function show(HeatAlert $heatAlert)
    {
        return view('admin.alerts.show', compact('heatAlert'));
    }

    public function edit(HeatAlert $heatAlert)
    {
        $neighborhoods = Neighborhood::all();
        return view('admin.alerts.edit', compact('heatAlert', 'neighborhoods'));
    }

    public function update(Request $request, HeatAlert $heatAlert)
    {
        $validated = $request->validate([
            'neighborhood_id' => 'required|exists:neighborhoods,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'severity_level' => 'required|string|in:low,medium,high',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $heatAlert->update($validated);
        return redirect()->route('admin.alerts.index')->with('success', 'Alerte chaleur mise à jour.');
    }

    public function destroy(HeatAlert $heatAlert)
    {
        $heatAlert->delete();
        return redirect()->route('admin.alerts.index')->with('success', 'Alerte chaleur supprimée.');
    }
}
