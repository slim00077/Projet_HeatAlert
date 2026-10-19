<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CoolSpotTypeRequest;
use App\Models\CoolSpotType;

class CoolSpotTypeController extends Controller
{
    public function index()
    {
        $types = CoolSpotType::withCount('coolSpots')->orderBy('name')->paginate(10);

        return view('back.cool-spot-types.index', compact('types'));
    }

    public function create()
    {
        return view('back.cool-spot-types.create');
    }

    public function store(CoolSpotTypeRequest $request)
    {
        CoolSpotType::create($request->validated());

        return redirect()->route('admin.cool-spot-types.index')
            ->with('success', 'Type créé avec succès.');
    }

    public function edit(CoolSpotType $coolSpotType)
    {
        return view('back.cool-spot-types.edit', compact('coolSpotType'));
    }

    public function update(CoolSpotTypeRequest $request, CoolSpotType $coolSpotType)
    {
        $coolSpotType->update($request->validated());

        return redirect()->route('admin.cool-spot-types.index')
            ->with('success', 'Type modifié avec succès.');
    }

    public function destroy(CoolSpotType $coolSpotType)
    {
        if ($coolSpotType->coolSpots()->exists()) {
            return back()->with('error', 'Impossible de supprimer : ce type contient des points de fraîcheur.');
        }

        $coolSpotType->delete();

        return redirect()->route('admin.cool-spot-types.index')
            ->with('success', 'Type supprimé.');
    }
}
