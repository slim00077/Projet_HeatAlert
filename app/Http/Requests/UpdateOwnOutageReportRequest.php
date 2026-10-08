<?php

namespace App\Http\Requests;

use App\Models\OutageReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnOutageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $outageReport = $this->route('outageReport');

        return $outageReport instanceof OutageReport
            && $this->user()?->can('update', $outageReport) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
