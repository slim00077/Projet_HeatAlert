<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePowerOutageRequest;
use App\Http\Requests\UpdatePowerOutageRequest;
use App\Models\PowerOutage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PowerOutageController extends Controller
{
    public function index(): View
    {
        $outages = PowerOutage::withCount('reports')->latest('started_at')->paginate(10);

        return view('back.power-outages.index', compact('outages'));
    }

    public function create(): View
    {
        return view('back.power-outages.create', ['powerOutage' => new PowerOutage]);
    }

    public function store(StorePowerOutageRequest $request): RedirectResponse
    {
        $powerOutage = PowerOutage::create($request->validated());

        return redirect()->route('admin.power-outages.show', $powerOutage)
            ->with('success', 'The outage has been created.');
    }

    public function show(PowerOutage $powerOutage): View
    {
        $powerOutage->load(['reports' => fn ($query) => $query->latest()]);

        return view('back.power-outages.show', compact('powerOutage'));
    }

    public function edit(PowerOutage $powerOutage): View
    {
        return view('back.power-outages.edit', compact('powerOutage'));
    }

    public function update(UpdatePowerOutageRequest $request, PowerOutage $powerOutage): RedirectResponse
    {
        $powerOutage->update($request->validated());

        return redirect()->route('admin.power-outages.show', $powerOutage)
            ->with('success', 'The outage has been updated.');
    }

    public function destroy(PowerOutage $powerOutage): RedirectResponse
    {
        $powerOutage->delete();

        return redirect()->route('admin.power-outages.index')
            ->with('success', 'The outage has been deleted.');
    }

    public function publicIndex(): View
    {
        $outages = PowerOutage::withCount('reports')->latest('started_at')->paginate(9);

        return view('front.outages.index', compact('outages'));
    }

    public function publicShow(PowerOutage $powerOutage): View
    {
        $powerOutage->load(['reports' => fn ($query) => $query->latest()]);

        return view('front.outages.show', compact('powerOutage'));
    }
}
