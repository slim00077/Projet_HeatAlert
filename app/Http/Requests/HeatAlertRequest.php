<?php

namespace App\Http\Requests;

use App\Enums\Severity;
use App\Models\HeatAlert;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeatAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'neighborhood_id' => ['required', 'exists:neighborhoods,id'],
            'title' => ['required', 'string', 'max:255'],
            'residence_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'severity_level' => ['required', Rule::enum(Severity::class)],
            'temperature' => ['nullable', 'numeric', 'between:-10,60'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Reject a second alert of the same severity overlapping in the same neighborhood.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $overlaps = HeatAlert::query()
                ->where('neighborhood_id', $this->input('neighborhood_id'))
                ->where('severity_level', $this->input('severity_level'))
                ->when($this->route('heatAlert'), fn ($query, HeatAlert $current) => $query->whereKeyNot($current->getKey()))
                ->overlapping($this->date('start_date'), $this->date('end_date'))
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('start_date', 'An alert with the same severity already covers this neighborhood during that period.');
            }
        }];
    }
}
