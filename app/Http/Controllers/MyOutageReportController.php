<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOwnOutageReportRequest;
use App\Models\OutageReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MyOutageReportController extends Controller
{
    public function index(Request $request): View
    {
        $reports = $request->user()
            ->outageReports()
            ->with('powerOutage')
            ->latest()
            ->paginate(10);

        return view('front.outage-reports.index', compact('reports'));
    }

    public function update(UpdateOwnOutageReportRequest $request, OutageReport $outageReport): RedirectResponse
    {
        $outageReport->update($request->validated());

        return redirect()->route('my-outage-reports.index')
            ->with('success', 'Your outage report has been updated.');
    }

    public function destroy(Request $request, OutageReport $outageReport): RedirectResponse
    {
        Gate::authorize('delete', $outageReport);
        $outageReport->delete();

        return redirect()->route('my-outage-reports.index')
            ->with('success', 'Your outage report has been deleted.');
    }
}
