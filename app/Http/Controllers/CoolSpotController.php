<?php

namespace App\Http\Controllers;

use App\Models\CoolSpot;
use App\Models\CoolSpotType;
use Illuminate\Http\Request;

class CoolSpotController extends Controller
{
    public function index(Request $request)
    {
        $spots = CoolSpot::with('type')
            ->where('is_active', true)
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->q . '%'))
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
}