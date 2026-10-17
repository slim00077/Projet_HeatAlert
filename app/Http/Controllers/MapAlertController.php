<?php

namespace App\Http\Controllers;

use App\Enums\Severity;
use App\Http\Resources\HeatAlertResource;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MapAlertController extends Controller
{
    /**
     * Create an alert from a point picked on the map. Admins create regular alerts
     * (residents get notified); residents create community reports (shown as pins only).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $maxHours = $user->isAdmin() ? 72 : 24;

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'severity_level' => ['required', Rule::enum(Severity::class)],
            'temperature' => ['nullable', 'numeric', 'between:-10,60'],
            'duration_hours' => ['required', 'integer', 'between:1,'.$maxHours],
        ]);

        $neighborhood = Neighborhood::nearestTo((float) $data['latitude'], (float) $data['longitude']);

        if ($neighborhood === null) {
            return response()->json([
                'message' => 'This point is too far from any monitored neighborhood.',
                'errors' => ['latitude' => ['This point is too far from any monitored neighborhood.']],
            ], 422);
        }

        $alert = HeatAlert::create([
            'neighborhood_id' => $neighborhood->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'severity_level' => $data['severity_level'],
            'temperature' => $data['temperature'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'source' => $user->isAdmin() ? HeatAlert::SOURCE_MANUAL : HeatAlert::SOURCE_COMMUNITY,
            'created_by' => $user->id,
            'start_date' => now(),
            'end_date' => now()->addHours((int) $data['duration_hours']),
        ]);

        $alert->setRelation('neighborhood', $neighborhood)->notifyResidents();

        return response()->json([
            'message' => "Alert added in {$neighborhood->name}.",
            'alert' => HeatAlertResource::make($alert)->resolve($request),
        ], 201);
    }
}
