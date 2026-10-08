<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOutageReportRequest;
use App\Http\Requests\UpdateOutageReportStatusRequest;
use App\Models\OutageReport;
use App\Models\PowerOutage;
use Illuminate\Http\RedirectResponse;

class OutageReportController extends Controller
{
    public function updateStatus(UpdateOutageReportStatusRequest $request, OutageReport $outageReport): RedirectResponse
    {
        $outageReport->update($request->validated());

        return redirect()->route('admin.power-outages.show', $outageReport->power_outage_id)
            ->with('success', 'The report status has been updated.');
    }

    public function adminDestroy(OutageReport $outageReport): RedirectResponse
    {
        $powerOutage = $outageReport->powerOutage;
        $outageReport->delete();

        return redirect()->route('admin.power-outages.show', $powerOutage)
            ->with('success', 'The report has been deleted.');
    }

    public function publicStore(StoreOutageReportRequest $request, PowerOutage $powerOutage): RedirectResponse
    {
        $validated = $request->validated();
        $validated['power_outage_id'] = $powerOutage->id;
        $validated['user_id'] = $request->user()->id;
        $validated['reporter_name'] = $request->user()->name;
        $validated['email'] = $request->user()->email;
        $validated['status'] = 'pending';

        $powerOutage->reports()->create($validated);

        return redirect()->route('front.power-outages.show', $powerOutage)
            ->with('success', 'Your outage report was submitted.');
    }
}
