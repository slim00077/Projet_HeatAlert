<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePowerOutageRequest;
use App\Http\Requests\UpdatePowerOutageRequest;
use App\Models\Neighborhood;
use App\Models\PowerOutage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PowerOutageController extends Controller
{
    public function index(): View
    {
        $outages = PowerOutage::with('neighborhoods')->withCount('reports')->latest('started_at')->paginate(10);

        return view('back.power-outages.index', [
            'outages' => $outages,
            ...$this->outageMapData([
                PowerOutage::STATUS_PLANNED,
                PowerOutage::STATUS_IN_PROGRESS,
                PowerOutage::STATUS_RESOLVED,
                PowerOutage::STATUS_CANCELLED,
            ]),
        ]);
    }

    public function create(): View
    {
        return view('back.power-outages.create', [
            'powerOutage' => new PowerOutage,
            'neighborhoods' => Neighborhood::query()->orderBy('name')->get(['id', 'name', 'latitude', 'longitude']),
            'statusOptions' => [
                PowerOutage::STATUS_PLANNED => 'Planned',
                PowerOutage::STATUS_IN_PROGRESS => 'In progress',
            ],
        ]);
    }

    public function store(StorePowerOutageRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $neighborhoodIds = $validated['neighborhood_ids'];
        unset($validated['neighborhood_ids']);

        $powerOutage = DB::transaction(function () use ($validated, $neighborhoodIds): PowerOutage {
            $powerOutage = PowerOutage::create($validated);
            $powerOutage->neighborhoods()->sync($neighborhoodIds);

            return $powerOutage;
        });

        return redirect()->route('admin.power-outages.show', $powerOutage)
            ->with('success', 'The outage has been created.');
    }

    public function show(PowerOutage $powerOutage): View
    {
        $powerOutage->load(['neighborhoods', 'reports' => fn ($query) => $query->latest()]);

        return view('back.power-outages.show', compact('powerOutage'));
    }

    public function edit(PowerOutage $powerOutage): View
    {
        $powerOutage->load('neighborhoods');
        $allStatusOptions = [
            PowerOutage::STATUS_PLANNED => 'Planned',
            PowerOutage::STATUS_IN_PROGRESS => 'In progress',
            PowerOutage::STATUS_RESOLVED => 'Resolved',
            PowerOutage::STATUS_CANCELLED => 'Cancelled',
        ];

        return view('back.power-outages.edit', [
            'powerOutage' => $powerOutage,
            'neighborhoods' => Neighborhood::query()->orderBy('name')->get(['id', 'name', 'latitude', 'longitude']),
            'statusOptions' => array_intersect_key($allStatusOptions, array_flip($powerOutage->availableStatuses())),
        ]);
    }

    public function update(UpdatePowerOutageRequest $request, PowerOutage $powerOutage): RedirectResponse
    {
        $validated = $request->validated();
        $neighborhoodIds = $validated['neighborhood_ids'];
        unset($validated['neighborhood_ids']);

        DB::transaction(function () use ($powerOutage, $validated, $neighborhoodIds): void {
            $powerOutage->fill($validated)->forceFill(['area' => null])->save();
            $powerOutage->neighborhoods()->sync($neighborhoodIds);
        });

        return redirect()->route('admin.power-outages.show', $powerOutage)
            ->with('success', 'The outage has been updated.');
    }

    public function destroy(PowerOutage $powerOutage): RedirectResponse
    {
        $powerOutage->delete();

        return redirect()->route('admin.power-outages.index')
            ->with('success', 'The outage has been deleted.');
    }

    public function publicIndex(): View
    {
        $outages = PowerOutage::with('neighborhoods')
            ->withCount('reports')
            ->active()
            ->latest('started_at')
            ->paginate(9);

        return view('front.outages.index', [
            'outages' => $outages,
            'history' => false,
            ...$this->outageMapData([
                PowerOutage::STATUS_PLANNED,
                PowerOutage::STATUS_IN_PROGRESS,
            ]),
        ]);
    }

    public function history(): View
    {
        $outages = PowerOutage::with('neighborhoods')
            ->withCount('reports')
            ->history()
            ->latest('started_at')
            ->paginate(9);

        return view('front.outages.index', [
            'outages' => $outages,
            'history' => true,
            ...$this->outageMapData([
                PowerOutage::STATUS_RESOLVED,
                PowerOutage::STATUS_CANCELLED,
            ]),
        ]);
    }

    public function publicShow(PowerOutage $powerOutage): View
    {
        $powerOutage->load(['neighborhoods', 'reports' => fn ($query) => $query->latest()]);

        return view('front.outages.show', [
            'powerOutage' => $powerOutage,
            ...$this->outageMapData([], $powerOutage),
        ]);
    }

    /**
     * @param  array<int, string>  $statuses
     * @return array{
     *     mapPoints: array<int, array<string, mixed>>,
     *     affectedNeighborhoods: int,
     *     mappedNeighborhoods: int,
     *     estimatedPopulation: int,
     *     estimatedVulnerablePopulation: int,
     *     neighborhoodsWithPopulationData: int
     * }
     */
    private function outageMapData(array $statuses, ?PowerOutage $singleOutage = null): array
    {
        $outagesForNeighborhood = function (Builder|Relation $query) use ($statuses, $singleOutage): void {
            $query->when(
                $singleOutage,
                fn ($outages) => $outages->whereKey($singleOutage->getKey()),
                fn ($outages) => $outages->whereIn('status', $statuses),
            );
        };

        $neighborhoods = Neighborhood::query()
            ->whereHas('powerOutages', $outagesForNeighborhood)
            ->with([
                'powerOutages' => function ($query) use ($outagesForNeighborhood): void {
                    $outagesForNeighborhood($query);
                    $query->withCount('reports')->orderBy('started_at');
                },
            ])
            ->orderBy('name')
            ->get();

        $mapPoints = $neighborhoods
            ->filter(fn (Neighborhood $neighborhood): bool => $neighborhood->hasCoordinates())
            ->map(fn (Neighborhood $neighborhood): array => [
                'name' => $neighborhood->name,
                'latitude' => $neighborhood->latitude,
                'longitude' => $neighborhood->longitude,
                'population' => $neighborhood->population,
                'vulnerable_population' => $neighborhood->vulnerable_population,
                'outages' => $neighborhood->powerOutages->map(fn (PowerOutage $outage): array => [
                    'title' => $outage->title,
                    'status' => $outage->status,
                    'severity' => $outage->severity,
                    'reports_count' => $outage->reports_count,
                    'url' => route('front.power-outages.show', $outage),
                ])->all(),
            ])
            ->values()
            ->all();

        return [
            'mapPoints' => $mapPoints,
            'affectedNeighborhoods' => $neighborhoods->count(),
            'mappedNeighborhoods' => count($mapPoints),
            'estimatedPopulation' => (int) $neighborhoods->sum('population'),
            'estimatedVulnerablePopulation' => (int) $neighborhoods->sum('vulnerable_population'),
            'neighborhoodsWithPopulationData' => $neighborhoods->filter(
                fn (Neighborhood $neighborhood): bool => $neighborhood->population !== null,
            )->count(),
        ];
    }
}
