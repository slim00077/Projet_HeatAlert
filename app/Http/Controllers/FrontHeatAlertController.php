<?php

namespace App\Http\Controllers;

use App\Http\Requests\HeatAlertRequest;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Http\RedirectResponse;
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

    public function create(): View
    {
        return view('front.alerts.create', ['neighborhoods' => Neighborhood::orderBy('name')->get()]);
    }

    /**
     * Residents and managers submit community reports (a pin on the map, no banner, no notification);
     * an admin creating one here gets a regular alert that notifies the neighborhood.
     */
    public function store(HeatAlertRequest $request): RedirectResponse
    {
        $user = $request->user();

        $data = collect($request->validated())->except('image')->all();
        $data['source'] = $user->isAdmin() ? HeatAlert::SOURCE_MANUAL : HeatAlert::SOURCE_COMMUNITY;
        $data['created_by'] = $user->id;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('heat-alerts', 'public');
        }

        $heatAlert = HeatAlert::create($data);

        if ($user->isAdmin() && $heatAlert->isActive()) {
            $heatAlert->load('neighborhood')->notifyResidents();
        }

        return redirect()->route('front.alerts.index')->with('success', 'Heat alert created successfully.');
    }
}
