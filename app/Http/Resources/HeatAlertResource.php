<?php

namespace App\Http\Resources;

use App\Models\HeatAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HeatAlert
 */
class HeatAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity_level->value,
            'severity_label' => $this->severity_level->label(),
            'color' => $this->severity_level->color(),
            'source' => $this->source,
            'temperature' => $this->temperature,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status(),
            'start_date' => $this->start_date->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'recommendations' => $this->severity_level->recommendations(),
            'neighborhood' => new NeighborhoodResource($this->whenLoaded('neighborhood')),
        ];
    }
}
