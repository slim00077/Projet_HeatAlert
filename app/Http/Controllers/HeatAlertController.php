<?php

namespace App\Http\Controllers;

use App\Enums\Severity;
use App\Http\Requests\HeatAlertRequest;
use App\Models\HeatAlert;
use App\Models\Neighborhood;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HeatAlertController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'neighborhood_id' => ['nullable', 'integer'],
            'severity' => ['nullable', 'in:'.implode(',', array_column(Severity::cases(), 'value'))],
            'status' => ['nullable', 'in:'.implode(',', [HeatAlert::STATUS_UPCOMING, HeatAlert::STATUS_ACTIVE, HeatAlert::STATUS_EXPIRED])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $alerts = HeatAlert::query()
            ->with('neighborhood')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['neighborhood_id'] ?? null, fn ($query, $id) => $query->where('neighborhood_id', $id))
            ->when($filters['severity'] ?? null, fn ($query, string $severity) => $query->where('severity_level', $severity))
            ->when($filters['status'] ?? null, fn ($query, string $status) => match ($status) {
                HeatAlert::STATUS_UPCOMING => $query->upcoming(),
                HeatAlert::STATUS_EXPIRED => $query->expired(),
                default => $query->active(),
            })
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('start_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('start_date', '<=', $to))
            ->latest('start_date')
            ->paginate(10)
            ->withQueryString();

        return view('admin.alerts.index', [
            'alerts' => $alerts,
            'neighborhoods' => Neighborhood::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.alerts.create', [
            'heatAlert' => new HeatAlert,
            'neighborhoods' => Neighborhood::orderBy('name')->get(),
        ]);
    }

    public function store(HeatAlertRequest $request): RedirectResponse
    {
        $heatAlert = HeatAlert::create($request->validated());

        $notified = $heatAlert->isActive() ? $heatAlert->load('neighborhood')->notifyResidents() : 0;

        $message = 'Heat alert created.';
        if ($notified > 0) {
            $message .= " {$notified} resident(s) are being notified.";
        }

        return redirect()->route('admin.alerts.show', $heatAlert)->with('success', $message);
    }

    public function show(HeatAlert $heatAlert): View
    {
        $heatAlert->load('neighborhood');

        return view('admin.alerts.show', [
            'heatAlert' => $heatAlert,
            'residentsCount' => $heatAlert->neighborhood->residents()->count(),
        ]);
    }

    public function edit(HeatAlert $heatAlert): View
    {
        return view('admin.alerts.edit', [
            'heatAlert' => $heatAlert,
            'neighborhoods' => Neighborhood::orderBy('name')->get(),
        ]);
    }

    public function update(HeatAlertRequest $request, HeatAlert $heatAlert): RedirectResponse
    {
        $heatAlert->update($request->validated());

        return redirect()->route('admin.alerts.show', $heatAlert)->with('success', 'Heat alert updated.');
    }

    public function destroy(HeatAlert $heatAlert): RedirectResponse
    {
        $heatAlert->delete();

        return redirect()->route('admin.alerts.index')->with('success', 'Heat alert deleted.');
    }
}
