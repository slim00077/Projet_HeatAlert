<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NeighborhoodResource;
use App\Models\Neighborhood;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NeighborhoodApiController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $neighborhoods = Neighborhood::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('activeAlerts')
            ->withCount('residences')
            ->orderBy('name')
            ->get();

        return NeighborhoodResource::collection($neighborhoods);
    }
}
