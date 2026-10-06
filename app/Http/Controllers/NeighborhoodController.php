<?php

namespace App\Http\Controllers;

use App\Models\Neighborhood;
use Illuminate\Http\Request;

class NeighborhoodController extends Controller
{
    public function index()
    {
        $neighborhoods = Neighborhood::all();
        return view('admin.neighborhoods.index', compact('neighborhoods'));
    }

    public function create()
    {
        return view('admin.neighborhoods.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Neighborhood::create($validated);
        return redirect()->route('admin.neighborhoods.index')->with('success', 'Quartier créé avec succès.');
    }

    public function show(Neighborhood $neighborhood)
    {
        return view('admin.neighborhoods.show', compact('neighborhood'));
    }

    public function edit(Neighborhood $neighborhood)
    {
        return view('admin.neighborhoods.edit', compact('neighborhood'));
    }

    public function update(Request $request, Neighborhood $neighborhood)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $neighborhood->update($validated);
        return redirect()->route('admin.neighborhoods.index')->with('success', 'Quartier mis à jour.');
    }

    public function destroy(Neighborhood $neighborhood)
    {
        $neighborhood->delete();
        return redirect()->route('admin.neighborhoods.index')->with('success', 'Quartier supprimé.');
    }
}
