<?php

namespace App\Http\Controllers;

use App\Models\HeatAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HeatAlertReviewController extends Controller
{
    /**
     * Publish a resident report: it now shows on the map and in the public alert list.
     */
    public function approve(Request $request, HeatAlert $heatAlert): RedirectResponse
    {
        return $this->review($request, $heatAlert, HeatAlert::REVIEW_APPROVED, 'Report approved: it is now visible on the map.');
    }

    /**
     * Refuse a resident report: it never appears publicly.
     */
    public function reject(Request $request, HeatAlert $heatAlert): RedirectResponse
    {
        return $this->review($request, $heatAlert, HeatAlert::REVIEW_REJECTED, 'Report rejected.');
    }

    private function review(Request $request, HeatAlert $heatAlert, string $decision, string $message): RedirectResponse
    {
        if (! $heatAlert->isPendingReview()) {
            return back()->with('error', 'This report has already been reviewed.');
        }

        $heatAlert->markReviewed($decision, $request->user());

        return back()->with('success', $message);
    }
}
