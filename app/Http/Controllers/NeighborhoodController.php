<?php

namespace App\Http\Controllers;

use App\Http\Requests\NeighborhoodRequest;
use App\Models\Neighborhood;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NeighborhoodController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $neighborhoods = Neighborhood::query()
            ->with('activeAlerts')
            ->withCount(['heatAlerts', 'residences'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.neighborhoods.index', compact('neighborhoods', 'search'));
    }

    public function create(): View
    {
        return view('admin.neighborhoods.create', ['neighborhood' => new Neighborhood]);
    }

    public function store(NeighborhoodRequest $request): RedirectResponse
    {
        $neighborhood = Neighborhood::create($request->validated());

        return redirect()->route('admin.neighborhoods.show', $neighborhood)->with('success', 'Neighborhood created.');
    }

    public function show(Neighborhood $neighborhood): View
    {
        $neighborhood->load('activeAlerts')->loadCount('residences');

        return view('admin.neighborhoods.show', [
            'neighborhood' => $neighborhood,
            'alerts' => $neighborhood->heatAlerts()->latest('start_date')->limit(10)->get(),
        ]);
    }

    public function edit(Neighborhood $neighborhood): View
    {
        return view('admin.neighborhoods.edit', compact('neighborhood'));
    }

    public function update(NeighborhoodRequest $request, Neighborhood $neighborhood): RedirectResponse
    {
        $neighborhood->update($request->validated());

        return redirect()->route('admin.neighborhoods.show', $neighborhood)->with('success', 'Neighborhood updated.');
    }

    public function destroy(Neighborhood $neighborhood): RedirectResponse
    {
        $neighborhood->delete();

        return redirect()->route('admin.neighborhoods.index')->with('success', 'Neighborhood deleted.');
    }
}
