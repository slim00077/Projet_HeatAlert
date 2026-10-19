<?php

namespace App\Http\Resources;

use App\Models\Neighborhood;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Neighborhood
 */
class NeighborhoodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $risk = $this->whenLoaded('activeAlerts', fn () => $this->currentRisk());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'population' => $this->population,
            'vulnerable_population' => $this->vulnerable_population,
            'vulnerable_share' => $this->vulnerableShare(),
            'residences_count' => $this->whenCounted('residences'),
            'weather' => [
                'current_temperature' => $this->current_temperature,
                'forecast_max_temperature' => $this->forecast_max_temperature,
                'updated_at' => $this->weather_updated_at?->toIso8601String(),
            ],
            'risk' => $this->when($this->relationLoaded('activeAlerts'), fn () => $risk?->value ?? 'none'),
            'risk_label' => $this->when($this->relationLoaded('activeAlerts'), fn () => $risk?->label() ?? 'No active alert'),
            'color' => $this->when($this->relationLoaded('activeAlerts'), fn () => $risk?->color() ?? '#10b981'),
            'residences' => $this->whenLoaded('residences', fn () => $this->residences->map(fn ($residence) => [
                'id' => $residence->id,
                'name' => $residence->name,
                'address' => $residence->address,
                'owner' => $request->user()?->isAdmin() ? $residence->user?->name : null,
                'url' => route('residences.show', $residence),
            ])->values()),
            'active_alerts' => HeatAlertResource::collection($this->whenLoaded('activeAlerts')),
        ];
    }
}
