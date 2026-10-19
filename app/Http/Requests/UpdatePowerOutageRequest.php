<?php

namespace App\Http\Requests;

use App\Models\PowerOutage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePowerOutageRequest extends FormRequest
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
        /** @var PowerOutage $powerOutage */
        $powerOutage = $this->route('powerOutage');

        return [
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'neighborhood_ids' => ['required', 'array', 'min:1'],
            'neighborhood_ids.*' => ['required', 'integer', 'distinct', 'exists:neighborhoods,id'],
            'status' => ['required', Rule::in($powerOutage->availableStatuses())],
            'severity' => ['required', 'in:low,moderate,high,critical'],
            'started_at' => ['required', 'date'],
            'estimated_end_at' => ['required', 'date', 'after:started_at'],
            'description' => ['required', 'string', 'min:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Choose a valid status transition for this outage.',
        ];
    }
}
