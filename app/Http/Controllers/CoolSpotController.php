<?php

namespace App\Http\Controllers;

use App\Http\Requests\CoolSpotRequest;
use App\Models\CoolSpot;
use App\Models\CoolSpotType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoolSpotController extends Controller
{
    public function index(Request $request)
    {
        $spots = CoolSpot::with('type')
            ->where('is_active', true)
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->when($request->filled('type'), fn ($q) => $q->where('cool_spot_type_id', $request->type))
            ->when($request->boolean('accessible'), fn ($q) => $q->where('is_accessible', true))
            ->orderBy('name')
            ->paginate(9)
            ->withQueryString();

        $types = CoolSpotType::orderBy('name')->get();

        return view('front.cool-spots.index', compact('spots', 'types'));
    }

    public function show(CoolSpot $coolSpot)
    {
        abort_unless($coolSpot->is_active, 404);

        $coolSpot->load('type');

        $related = CoolSpot::where('cool_spot_type_id', $coolSpot->cool_spot_type_id)
            ->where('is_active', true)
            ->where('id', '!=', $coolSpot->id)
            ->orderBy('name')
            ->take(3)
            ->get();

        return view('front.cool-spots.show', compact('coolSpot', 'related'));
    }

    public function create(): View
    {
        return view('front.cool-spots.create', ['types' => CoolSpotType::orderBy('name')->get()]);
    }

    public function store(CoolSpotRequest $request): RedirectResponse
    {
        CoolSpot::create(array_merge($request->validated(), [
            'is_accessible' => $request->boolean('is_accessible'),
            'is_active' => $request->boolean('is_active'),
        ]));

        return redirect()->route('cool-spots.index')->with('success', 'Point de fraîcheur créé avec succès.');
    }
}
