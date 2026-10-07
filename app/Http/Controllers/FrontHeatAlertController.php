<?php

namespace App\Http\Controllers;

use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

    public function create(): View
    {
        return view('front.alerts.create', ['neighborhoods' => Neighborhood::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'neighborhood_id' => 'required|exists:neighborhoods,id',
            'title' => 'required|string|max:255',
            'residence_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'severity_level' => 'required|string|in:low,medium,high',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $data = collect($validated)->except('image')->all();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('heat-alerts', 'public');
        }

        HeatAlert::create($data);

        return redirect()->route('front.alerts.index')->with('success', 'Alerte chaleur créée avec succès.');
    }
}
