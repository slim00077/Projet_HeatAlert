<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CoolSpotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cool_spot_type_id' => ['required', 'exists:cool_spot_types,id'],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'opening_hours' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_accessible' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'cool_spot_type_id.required' => 'Veuillez choisir un type.',
            'cool_spot_type_id.exists' => "Ce type n'existe pas.",
            'name.required' => 'Le nom du point de fraîcheur est obligatoire.',
            'name.max' => 'Le nom ne doit pas dépasser 150 caractères.',
            'address.required' => "L'adresse est obligatoire.",
            'address.max' => "L'adresse ne doit pas dépasser 255 caractères.",
            'latitude.numeric' => 'La latitude doit être un nombre.',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.numeric' => 'La longitude doit être un nombre.',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',
            'opening_hours.max' => 'Les horaires ne doivent pas dépasser 100 caractères.',
            'description.max' => 'La description ne doit pas dépasser 1000 caractères.',
        ];
    }
}
