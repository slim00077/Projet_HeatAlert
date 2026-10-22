<?php

namespace App\Http\Controllers;

use App\Models\ResidentReport;
use App\Services\ResponseCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A resident tells the residence team whether the issue of their own report appears resolved.
 */
class ResponseConfirmationController extends Controller
{
    public function store(Request $request, ResidentReport $residentReport, ResponseCaseService $service): RedirectResponse
    {
        $case = $residentReport->currentResponseCase();

        abort_if($case === null, 404);

        Gate::authorize('confirm', [$case, $residentReport]);

        $data = $request->validate([
            'is_resolved' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->confirm($request->user(), $case, $residentReport, (bool) $data['is_resolved'], $data['comment'] ?? null);

        return back()->with('success', $data['is_resolved'] ? 'Thank you, your confirmation was sent to the residence team.' : 'Thank you, the residence team was told the issue still appears.');
    }
}
