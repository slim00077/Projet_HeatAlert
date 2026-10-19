<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\HeatAlertResource;
use App\Models\HeatAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HeatAlertApiController extends Controller
{
    public function active(Request $request): AnonymousResourceCollection
    {
        $alerts = HeatAlert::query()
            ->active()
            ->with('neighborhood')
            ->when($request->integer('neighborhood_id'), fn ($query, int $id) => $query->where('neighborhood_id', $id))
            ->orderByDesc('start_date')
            ->get();

        return HeatAlertResource::collection($alerts);
    }
}
