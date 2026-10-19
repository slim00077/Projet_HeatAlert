<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CoolSpotTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'accès est déjà protégé par les middlewares auth + admin
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // à la modification, on ignore le type en cours pour éviter un faux doublon
                Rule::unique('cool_spot_types', 'name')->ignore($this->route('cool_spot_type')),
            ],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du type est obligatoire.',
            'name.unique' => 'Ce type existe déjà.',
            'name.max' => 'Le nom ne doit pas dépasser 100 caractères.',
            'icon.max' => "L'icône ne doit pas dépasser 50 caractères.",
            'description.max' => 'La description ne doit pas dépasser 500 caractères.',
        ];
    }
}
