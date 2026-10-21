<?php

namespace App\Http\Controllers;

use App\Models\ResidentReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Incident follow-up by the residence manager (and admins): status and documented resolution.
 * Managers do not file reports, so they use this page instead of the resident report pages.
 */
class ReportFollowUpController extends Controller
{
    public function show(Request $request, ResidentReport $residentReport): View|RedirectResponse
    {
        Gate::authorize('followUp', $residentReport);

        if ($request->user()->isAdmin()) {
            return redirect()->route('resident-reports.show', $residentReport);
        }

        $residentReport->load(['residence', 'user', 'responseCases']);

        return view('front.reports.manage', [
            'report' => $residentReport,
            'statuses' => ResidentReport::STATUSES,
        ]);
    }

    public function update(Request $request, ResidentReport $residentReport): RedirectResponse
    {
        Gate::authorize('followUp', $residentReport);

        $data = $request->validate([
            'status' => ['required', Rule::in(ResidentReport::STATUSES)],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $residentReport->update($data);

        return back()->with('success', 'Follow-up saved.');
    }
}
