<?php

namespace App\Http\Controllers;

use App\Models\Residence;
use App\Services\IncidentLensService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI Incident Lens: analyses a report being written (optional photo + description) before submission.
 * Nothing is stored here: the resident reviews the suggestions and submits the report form normally.
 */
class IncidentLensController extends Controller
{
    public function __invoke(Request $request, IncidentLensService $lens): JsonResponse
    {
        abort_if($request->user()->isManager(), 403, 'Residence managers do not file reports.');

        $data = $request->validate([
            'residence_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['required_without:photo', 'nullable', 'string', 'min:10', 'max:5000'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64,max_width=8000,max_height=8000'],
        ], [
            'description.required_without' => 'Describe the problem or add a photo before asking the AI Lens.',
            'photo.mimes' => 'The photo must be a JPG, PNG or WebP image.',
            'photo.max' => 'The photo must be smaller than 5 MB.',
        ]);

        // Only residences the user belongs to may be reported on, so nothing is sent to the AI for an unauthorized one.
        Residence::accessibleBy($request->user())->findOrFail($data['residence_id']);

        // An overloaded main model can time out before the fallback model answers (Apache's default limit is shorter).
        set_time_limit(150);

        return response()->json($lens->analyze($data['title'] ?? null, (string) ($data['description'] ?? ''), $request->file('photo')));
    }
}
