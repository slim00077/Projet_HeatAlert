<?php

namespace App\Http\Controllers;

use App\Models\Residence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResidenceApprovalController extends Controller
{
    public function index(): View
    {
        return view('back.approvals.index', [
            'pending' => Residence::pending()->with('user')->oldest()->get(),
            'reviewed' => Residence::whereNotNull('reviewed_at')->with(['user', 'reviewer'])->latest('reviewed_at')->limit(10)->get(),
        ]);
    }

    public function approve(Request $request, Residence $residence): RedirectResponse
    {
        $residence->update([
            'status' => Residence::STATUS_APPROVED,
            'rejection_reason' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', "{$residence->name} was approved.");
    }

    public function reject(Request $request, Residence $residence): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        $residence->update($data + [
            'status' => Residence::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', "{$residence->name} was rejected.");
    }
}
