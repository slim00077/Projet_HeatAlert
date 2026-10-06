<?php

namespace App\Http\Controllers;

use App\Models\Residence;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ResidenceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        $residences = Residence::with('user')
            ->withCount('residentReports')
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id))
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

    public function create(Request $request): View
    {
        return view($this->folder($request).'.residences.create', ['owners' => $this->owners($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->isAdmin() ? $data['user_id'] : $request->user()->id;

        Residence::create($data + $this->storeMedia($request));

        return redirect()->route('residences.index')->with('success', 'Residence created successfully.');
    }

    public function show(Request $request, Residence $residence): View
    {
        Gate::authorize('view', $residence);

        $residence->load(['user', 'residentReports']);

        return view($this->folder($request).'.residences.show', compact('residence'));
    }

    public function edit(Request $request, Residence $residence): View
    {
        Gate::authorize('update', $residence);

        return view($this->folder($request).'.residences.edit', [
            'residence' => $residence,
            'owners' => $this->owners($request),
        ]);
    }

    public function update(Request $request, Residence $residence): RedirectResponse
    {
        Gate::authorize('update', $residence);

        $data = $this->validated($request);

        if (! $request->user()->isAdmin()) {
            unset($data['user_id']);
        }

        $residence->update($data + $this->storeMedia($request, $residence));

        return redirect()->route('residences.index')->with('success', 'Residence updated successfully.');
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
    private function validated(Request $request): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'neighborhood' => 'required|string|max:255',
            'description' => 'nullable|string',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov|max:51200',
        ];

        if ($request->user()->isAdmin()) {
            $rules['user_id'] = 'required|exists:users,id';
        }

        return collect($request->validate($rules))->except('media')->all();
    }
}
