<?php

namespace App\Http\Controllers;

use App\Models\CoolSpot;
use App\Models\CoolSpotType;
<<<<<<< Updated upstream
=======
use App\Services\CoolSpotAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
>>>>>>> Stashed changes
use Illuminate\Http\Request;

class CoolSpotController extends Controller
{
    public function index(Request $request)
    {
        $coordinates = $request->validate([
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ]);
        $latitude = isset($coordinates['lat']) ? (float) $coordinates['lat'] : null;
        $longitude = isset($coordinates['lng']) ? (float) $coordinates['lng'] : null;

        $query = CoolSpot::with('type')
            ->where('is_active', true)
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->q . '%'))
            ->when($request->filled('type'), fn ($q) => $q->where('cool_spot_type_id', $request->type))
            ->when($request->boolean('accessible'), fn ($q) => $q->where('is_accessible', true));

        if ($latitude !== null && $longitude !== null) {
            $query->withDistanceFrom($latitude, $longitude)
                ->orderByRaw('CASE WHEN cool_spots.latitude IS NULL OR cool_spots.longitude IS NULL THEN 1 ELSE 0 END')
                ->orderBy('distance_km')
                ->orderBy('cool_spots.id');
        } else {
            $query->orderBy('name');
        }

        $spots = $query->paginate(9)->withQueryString();

        $types = CoolSpotType::orderBy('name')->get();
        
        return view('front.cool-spots.index', compact('spots', 'types'));
    }

<<<<<<< Updated upstream
   public function show(CoolSpot $coolSpot)
{
    abort_unless($coolSpot->is_active, 404);
=======
    public function recommend(Request $request, CoolSpotAiService $advisor): JsonResponse
    {
        $data = $request->validate([
            'situation' => ['required', 'string', 'min:5', 'max:500'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ]);
        $latitude = isset($data['lat']) ? (float) $data['lat'] : null;
        $longitude = isset($data['lng']) ? (float) $data['lng'] : null;

        $query = CoolSpot::query()
            ->with('type:id,name')
            ->where('is_active', true);

        if ($latitude !== null && $longitude !== null) {
            $query->withDistanceFrom($latitude, $longitude);
        }

        $spots = $query->get();
        $result = $advisor->recommend($data['situation'], $spots, $latitude, $longitude);
        $spotsById = $spots->keyBy('id');
        $recommendations = collect($result['recommendations'])
            ->map(function (array $recommendation) use ($spotsById): ?array {
                $spot = $spotsById->get($recommendation['id']);
                if (! $spot) {
                    return null;
                }

                return [
                    'id' => $spot->id,
                    'name' => $spot->name,
                    'type' => $spot->type?->name,
                    'address' => $spot->address,
                    'opening_hours' => $spot->opening_hours,
                    'is_accessible' => $spot->is_accessible,
                    'is_open_now' => $spot->isOpenNow(),
                    'reason' => $recommendation['reason'],
                    'url' => route('cool-spots.show', $spot),
                ];
            })
            ->filter()
            ->values();

        return response()->json(['recommendations' => $recommendations, 'source' => $result['source']]);
    }

    public function show(CoolSpot $coolSpot)
    {
        abort_unless($coolSpot->is_active, 404);
>>>>>>> Stashed changes

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