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
            'title' => ['required', 'string', 'max:255'],
            'area' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:planned,in_progress,resolved'],
            'severity' => ['required', 'in:low,moderate,high,critical'],
            'started_at' => ['nullable', 'date'],
            'estimated_end_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'description' => ['nullable', 'string'],
        ];
    }
}
