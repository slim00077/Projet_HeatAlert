<?php

namespace App\Http\Controllers;

use App\Http\Resources\HeatAlertResource;
use App\Http\Resources\NeighborhoodResource;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapDataController extends Controller
{
    /**
     * Everything the map needs. Residences are only listed for admins (all of them)
     * and for logged-in residents (their own), never to guests.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $neighborhoods = Neighborhood::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('activeAlerts')
            ->withCount('residences')
            ->when($user?->isAdmin(), fn ($query) => $query->with('residences.user'))
            ->when($user && ! $user->isAdmin(), fn ($query) => $query->with(['residences' => fn ($q) => $q->where('user_id', $user->id)]))
            ->orderBy('name')
            ->get();

        $pins = HeatAlert::query()
            ->active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('neighborhood')
            ->get();

        return response()->json([
            'neighborhoods' => NeighborhoodResource::collection($neighborhoods)->resolve($request),
            'pins' => HeatAlertResource::collection($pins)->resolve($request),
        ]);
    }
}
