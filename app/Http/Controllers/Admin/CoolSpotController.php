<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CoolSpotRequest;
use App\Http\Controllers\Controller;
use App\Models\CoolSpot;
use App\Models\CoolSpotType;
use App\Services\CoolSpotAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoolSpotController extends Controller
{
    public function index(Request $request)
    {
        $spots = CoolSpot::with('type')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->q . '%'))
            ->when($request->filled('type'), fn ($q) => $q->where('cool_spot_type_id', $request->type))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $types = CoolSpotType::orderBy('name')->get();

        return view('back.cool-spots.index', compact('spots', 'types'));
    }

    public function create()
    {
        $types = CoolSpotType::orderBy('name')->get();

        return view('back.cool-spots.create', compact('types'));
    }

<<<<<<< Updated upstream
   public function store(CoolSpotRequest $request)
{
    CoolSpot::create($this->data($request));
=======
    public function generateDescription(Request $request, CoolSpotAiService $advisor): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'is_accessible' => ['sometimes', 'boolean'],
        ]);

        return response()->json($advisor->generateDescription(
            $data['name'],
            $data['type'],
            $data['address'],
            $request->boolean('is_accessible'),
        ));
    }

    public function store(CoolSpotRequest $request)
    {
        CoolSpot::create($this->data($request));
>>>>>>> Stashed changes

    return redirect()->route('admin.cool-spots.index')
        ->with('success', 'Point de fraîcheur créé avec succès.');
}

    public function edit(CoolSpot $coolSpot)
    {
        $types = CoolSpotType::orderBy('name')->get();

        return view('back.cool-spots.edit', compact('coolSpot', 'types'));
    }

    public function update(CoolSpotRequest $request, CoolSpot $coolSpot)
{
    $coolSpot->update($this->data($request));

    return redirect()->route('admin.cool-spots.index')
        ->with('success', 'Point de fraîcheur modifié avec succès.');
}

    public function destroy(CoolSpot $coolSpot)
    {
        $coolSpot->delete();

        return redirect()->route('admin.cool-spots.index')
            ->with('success', 'Point de fraîcheur supprimé.');
    }

  private function data(CoolSpotRequest $request): array
{
    return array_merge($request->validated(), [
        // une case non cochée n'envoie rien : on force la valeur à false
        'is_accessible' => $request->boolean('is_accessible'),
        'is_active'     => $request->boolean('is_active'),
    ]);
}
}