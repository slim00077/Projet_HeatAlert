<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePowerOutageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'neighborhood_ids' => ['required', 'array', 'min:1'],
            'neighborhood_ids.*' => ['required', 'integer', 'distinct', 'exists:neighborhoods,id'],
            'status' => ['required', 'in:planned,in_progress'],
            'severity' => ['required', 'in:low,moderate,high,critical'],
            'started_at' => ['required', 'date'],
            'estimated_end_at' => ['required', 'date', 'after:started_at'],
            'description' => ['required', 'string', 'min:10'],
        ];
    }
}
