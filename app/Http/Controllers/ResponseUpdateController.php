<?php

namespace App\Http\Controllers;

use App\Models\ResponseCase;
use App\Models\ResponseUpdate;
use App\Services\ResponseCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Updates on a response case timeline: internal notes for the team, or published updates residents can read.
 */
class ResponseUpdateController extends Controller
{
    public function store(Request $request, ResponseCase $responseCase, ResponseCaseService $service): RedirectResponse
    {
        Gate::authorize('update', $responseCase);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $public = (bool) ($data['is_public'] ?? false);

        $service->postUpdate($request->user(), $responseCase, $data['body'], $public);

        return back()->with('success', $public ? 'Update published to the residents.' : 'Internal note added.');
    }

    /**
     * Approves an internal note for the residents of the case.
     */
    public function publish(Request $request, ResponseUpdate $responseUpdate, ResponseCaseService $service): RedirectResponse
    {
        Gate::authorize('update', $responseUpdate->responseCase);

        abort_unless($responseUpdate->kind === ResponseUpdate::KIND_NOTE && ! $responseUpdate->is_public, 422, 'Only internal notes can be published.');

        $service->publish($request->user(), $responseUpdate);

        return back()->with('success', 'Update approved and published to the residents.');
    }
}
