<?php

namespace App\Http\Controllers;

use App\Models\Neighborhood;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResidenceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        $residences = Residence::with('user')
            ->withCount([
                'residentReports',
                'residentReports as open_reports_count' => fn ($query) => $query->unresolved(),
                'responseCases as open_cases_count' => fn ($query) => $query->open(),
            ])
            ->accessibleBy($user)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('neighborhood', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        return view($this->folder($request).'.residences.index', compact('residences', 'search'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', Residence::class);

        if ($missing = $this->missingNeighborhood($request->user())) {
            return $missing;
        }

        return view($this->folder($request).'.residences.create', [
            'owners' => $this->owners($request),
            'neighborhoods' => $this->neighborhoodNames($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Residence::class);

        $user = $request->user();

        if ($missing = $this->missingNeighborhood($user)) {
            return $missing;
        }

        $data = $this->validated($request);

        if ($user->isAdmin()) {
            $data['status'] = Residence::STATUS_APPROVED;
        } else {
            $data['user_id'] = $user->id;
            $data['status'] = Residence::STATUS_PENDING;
        }

        Residence::create($data + $this->storeMedia($request));

        return redirect()->route('residences.index')->with(
            'success',
            $user->isAdmin() ? 'Residence created successfully.' : 'Residence submitted. An admin must approve it before you can add residents.'
        );
    }

    public function show(Request $request, Residence $residence): View
    {
        Gate::authorize('view', $residence);

        $user = $request->user();

        $residence->load(['user', 'residentReports.residence', 'residentReports.user', 'residentReports.responseCases', 'members', 'reviewer', 'responseCases.tasks', 'responseCases.reports']);

        // Residents only see their own reports; the residence-wide picture belongs to the manager.
        $reports = $residence->residentReports
            ->when($user->isResident(), fn ($reports) => $reports->where('user_id', $user->id))
            ->sortBy([fn ($a, $b) => $a->isResolved() <=> $b->isResolved(), fn ($a, $b) => $b->reported_at <=> $a->reported_at])
            ->values();

        return view($this->folder($request).'.residences.show', [
            'residence' => $residence,
            'canManageMembers' => Gate::allows('manageMembers', $residence),
            'reports' => $reports,
        ]);
    }

    public function edit(Request $request, Residence $residence): View
    {
        Gate::authorize('update', $residence);

        return view($this->folder($request).'.residences.edit', [
            'residence' => $residence,
            'owners' => $this->owners($request),
            'neighborhoods' => $this->neighborhoodNames($request->user(), $residence),
        ]);
    }

    public function update(Request $request, Residence $residence): RedirectResponse
    {
        Gate::authorize('update', $residence);

        $user = $request->user();
        $data = $this->validated($request, $residence);

        if (! $user->isAdmin()) {
            unset($data['user_id']);
        }

        if ($user->isManager() && $residence->isRejected()) {
            $data += ['status' => Residence::STATUS_PENDING, 'rejection_reason' => null, 'reviewed_by' => null, 'reviewed_at' => null];
        }

        $residence->update($data + $this->storeMedia($request, $residence));

        return redirect()->route('residences.index')->with(
            'success',
            $residence->isPending() ? 'Residence resubmitted for approval.' : 'Residence updated successfully.'
        );
    }

    public function destroy(Residence $residence): RedirectResponse
    {
        Gate::authorize('delete', $residence);

        if ($residence->media_path) {
            Storage::disk('public')->delete($residence->media_path);
        }

        $residence->delete();

        return redirect()->route('residences.index')->with('success', 'Residence deleted successfully.');
    }

    /**
     * Stores the uploaded picture or video and returns the columns to save.
     *
     * @return array<string, string>
     */
    private function storeMedia(Request $request, ?Residence $residence = null): array
    {
        if (! $request->hasFile('media')) {
            return [];
        }

        $file = $request->file('media');

        if ($residence?->media_path) {
            Storage::disk('public')->delete($residence->media_path);
        }

        return [
            'media_path' => $file->store('residences', 'public'),
            'media_type' => str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image',
        ];
    }

    private function folder(Request $request): string
    {
        return $request->user()->isAdmin() ? 'back' : 'front';
    }

    /**
     * A manager can only create residences once an admin assigned them a neighborhood.
     */
    private function missingNeighborhood(User $user): ?RedirectResponse
    {
        if ($user->isManager() && $user->neighborhood_id === null) {
            return redirect()->route('residences.index')->with('error', 'An admin must assign you to a neighborhood before you can create residences.');
        }

        return null;
    }

    /**
     * Neighborhoods offered in the form: every one for admins, only the assigned one for managers
     * (plus the current value of a legacy residence so it is not lost).
     *
     * @return list<string>
     */
    private function neighborhoodNames(User $user, ?Residence $residence = null): array
    {
        $names = $user->isAdmin()
            ? Neighborhood::orderBy('name')->pluck('name')->all()
            : array_filter([$user->neighborhood?->name]);

        if ($residence !== null && $residence->neighborhood !== null && ! in_array($residence->neighborhood, $names, true)) {
            $names[] = $residence->neighborhood;
        }

        return array_values($names);
    }

    /**
     * Only admins can pick the owner of a residence.
     *
     * @return Collection<int, User>
     */
    private function owners(Request $request): Collection
    {
        return $request->user()->isAdmin() ? User::orderBy('name')->get() : new Collection;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Residence $residence = null): array
    {
        $user = $request->user();

        $rules = [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'neighborhood' => ['required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov|max:51200',
        ];

        if ($user->isManager()) {
            $rules['neighborhood'][] = Rule::in($this->neighborhoodNames($user, $residence));
        }

        if ($user->isAdmin()) {
            $rules['user_id'] = 'required|exists:users,id';
        }

        return collect($request->validate($rules))->except('media')->all();
    }
}
