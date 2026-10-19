<?php

namespace App\Http\Controllers;

use App\Models\Residence;
use App\Models\ResidentReport;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResidentReportController extends Controller implements HasMiddleware
{
    /**
     * Residence managers do not file or follow resident reports.
     */
    public static function middleware(): array
    {
        return [fn (Request $request, Closure $next) => $request->user()?->isManager() ? abort(403) : $next($request)];
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $reports = ResidentReport::with(['residence', 'user'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->when(in_array($status, ResidentReport::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->latest('reported_at')
            ->latest('id')
            ->get();

        $statuses = ResidentReport::STATUSES;

        return view($this->folder($request).'.reports.index', compact('reports', 'statuses', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        return view($this->folder($request).'.reports.create', [
            'residences' => $this->availableResidences($request),
            'statuses' => ResidentReport::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $residence = Residence::findOrFail($data['residence_id']);
        $isAdmin = $request->user()->isAdmin();

        $data['user_id'] = $isAdmin ? $residence->user_id : $request->user()->id;
        $data['status'] = $isAdmin ? $data['status'] : 'Pending';

        ResidentReport::create($data);

        return redirect()->route('resident-reports.index')->with('success', 'Report created successfully.');
    }

    public function show(Request $request, ResidentReport $residentReport): View
    {
        Gate::authorize('view', $residentReport);

        $residentReport->load(['residence', 'user']);

        return view($this->folder($request).'.reports.show', ['report' => $residentReport]);
    }

    public function edit(Request $request, ResidentReport $residentReport): View
    {
        Gate::authorize('update', $residentReport);

        return view($this->folder($request).'.reports.edit', [
            'report' => $residentReport,
            'residences' => $this->availableResidences($request),
            'statuses' => ResidentReport::STATUSES,
        ]);
    }

    public function update(Request $request, ResidentReport $residentReport): RedirectResponse
    {
        Gate::authorize('update', $residentReport);

        $data = $this->validated($request);

        if ($request->user()->isAdmin()) {
            $data['user_id'] = Residence::findOrFail($data['residence_id'])->user_id;
        }

        $residentReport->update($data);

        return redirect()->route('resident-reports.index')->with('success', 'Report updated successfully.');
    }

    public function destroy(ResidentReport $residentReport): RedirectResponse
    {
        Gate::authorize('delete', $residentReport);

        $residentReport->delete();

        return redirect()->route('resident-reports.index')->with('success', 'Report deleted successfully.');
    }

    private function folder(Request $request): string
    {
        return $request->user()->isAdmin() ? 'back' : 'front';
    }

    /**
     * Residences the current user may attach a report to.
     *
     * @return Collection<int, Residence>
     */
    private function availableResidences(Request $request): Collection
    {
        $user = $request->user();

        return Residence::accessibleBy($user)->orderBy('name')->get();
    }

    /**
     * Residents can only pick their own residences and cannot change the status.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $user = $request->user();

        $residenceExists = Rule::in(Residence::accessibleBy($user)->pluck('id')->all());

        $rules = [
            'residence_id' => ['required', $residenceExists],
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'reported_at' => 'required|date',
        ];

        if ($user->isAdmin()) {
            $rules['status'] = ['required', Rule::in(ResidentReport::STATUSES)];
        }

        return $request->validate($rules);
    }
}
