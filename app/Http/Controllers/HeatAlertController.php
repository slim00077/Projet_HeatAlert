<?php

namespace App\Http\Controllers;

use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $validated = $this->validated($request);

        HeatAlert::create($validated + $this->storeImage($request));

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
        $validated = $this->validated($request);

        $heatAlert->update($validated + $this->storeImage($request, $heatAlert));

        return redirect()->route('admin.alerts.index')->with('success', 'Alerte chaleur mise à jour.');
    }

    public function destroy(HeatAlert $heatAlert)
    {
        if ($heatAlert->image_path) {
            Storage::disk('public')->delete($heatAlert->image_path);
        }

        $heatAlert->delete();

        return redirect()->route('admin.alerts.index')->with('success', 'Alerte chaleur supprimée.');
    }

    /**
     * Stores the uploaded picture and returns the column to save.
     *
     * @return array<string, string>
     */
    private function storeImage(Request $request, ?HeatAlert $heatAlert = null): array
    {
        if (! $request->hasFile('image')) {
            return [];
        }

        if ($heatAlert?->image_path) {
            Storage::disk('public')->delete($heatAlert->image_path);
        }

        return ['image_path' => $request->file('image')->store('heat-alerts', 'public')];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
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

        return collect($validated)->except('image')->all();
    }
}
