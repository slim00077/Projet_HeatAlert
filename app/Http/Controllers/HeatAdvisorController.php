<?php

namespace App\Http\Controllers;

use App\Enums\Severity;
use App\Models\Neighborhood;
use App\Services\HeatAdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HeatAdvisorController extends Controller
{
    public function brief(Neighborhood $neighborhood, HeatAdvisorService $advisor): JsonResponse
    {
        return response()->json($advisor->briefFor($neighborhood));
    }

    public function ask(Request $request, Neighborhood $neighborhood, HeatAdvisorService $advisor): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:5', 'max:300']]);

        return response()->json($advisor->answer($neighborhood, $data['question']));
    }

    public function draft(Request $request, HeatAdvisorService $advisor): JsonResponse
    {
        $data = $request->validate([
            'neighborhood_id' => ['required', 'exists:neighborhoods,id'],
            'severity_level' => ['required', Rule::enum(Severity::class)],
            'temperature' => ['nullable', 'numeric', 'between:-10,60'],
        ]);

        return response()->json($advisor->draftAlert(
            Neighborhood::findOrFail($data['neighborhood_id']),
            Severity::from($data['severity_level']),
            isset($data['temperature']) ? (float) $data['temperature'] : null,
        ));
    }
}
