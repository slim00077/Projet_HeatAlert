<?php

namespace App\Http\Controllers;

use App\Models\Residence;
use App\Models\ResidentReport;
use App\Notifications\ResponseCaseUpdated;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
            ->when($user->isAdmin(), fn ($query) => $query->with('responseCases'))
            ->when($user->isResident(), fn ($query) => $query->with([
                'responseCases.tasks',
                'responseCases.updates' => fn ($updates) => $updates->published()->latest('published_at')->latest('id'),
                'responseCases.confirmations' => fn ($confirmations) => $confirmations->where('user_id', $user->id),
            ]))
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->when(in_array($status, ResidentReport::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->latest('reported_at')
            ->latest('id')
            ->get();

        $statuses = ResidentReport::STATUSES;

        // New response-case news for a resident is shown once on My Reports, then marked as read.
        $news = $user->isResident()
            ? $user->unreadNotifications()->where('type', ResponseCaseUpdated::class)->latest()->limit(10)->get()
            : collect();
        $news->each->markAsRead();

        // Admin overview numbers cover every report, whatever filter is applied to the list.
        $byStatus = $user->isAdmin() ? ResidentReport::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') : collect();
        $counts = $user->isAdmin() ? ['all' => (int) $byStatus->sum(), 'overdue' => ResidentReport::overdue()->count()] + $byStatus->all() : [];

        return view($this->folder($request).'.reports.index', compact('reports', 'statuses', 'status', 'search', 'news', 'counts'));
    }

    public function create(Request $request): View
    {
        return view($this->folder($request).'.reports.create', [
            'residences' => $this->availableResidences($request),
            'statuses' => ResidentReport::STATUSES,
            'categories' => ResidentReport::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $residence = Residence::findOrFail($data['residence_id']);
        $isAdmin = $request->user()->isAdmin();

        $data['user_id'] = $isAdmin ? $residence->user_id : $request->user()->id;
        $data['status'] = $isAdmin ? $data['status'] : 'Pending';

        ResidentReport::create($data + $this->storePhoto($request));

        return redirect()->route('resident-reports.index')->with('success', 'Report created successfully.');
    }

    public function show(Request $request, ResidentReport $residentReport): View
    {
        Gate::authorize('view', $residentReport);

        $residentReport->load([
            'residence',
            'user',
            'responseCases.tasks',
            'responseCases.updates' => fn ($query) => $query->published()->latest('published_at')->latest('id'),
            'responseCases.confirmations',
        ]);

        return view($this->folder($request).'.reports.show', [
            'report' => $residentReport,
            'statuses' => ResidentReport::STATUSES,
        ]);
    }

    public function edit(Request $request, ResidentReport $residentReport): View
    {
        Gate::authorize('update', $residentReport);

        return view($this->folder($request).'.reports.edit', [
            'report' => $residentReport,
            'residences' => $this->availableResidences($request),
            'statuses' => ResidentReport::STATUSES,
            'categories' => ResidentReport::CATEGORIES,
        ]);
    }

    public function update(Request $request, ResidentReport $residentReport): RedirectResponse
    {
        Gate::authorize('update', $residentReport);

        $data = $this->validated($request);

        if ($request->user()->isAdmin()) {
            $data['user_id'] = Residence::findOrFail($data['residence_id'])->user_id;
        }

        // Once drafted with the AI Lens, a report keeps that marker.
        $data['ai_assisted'] = $data['ai_assisted'] || $residentReport->ai_assisted;

        $residentReport->update($data + $this->storePhoto($request, $residentReport));

        return redirect()->route('resident-reports.index')->with('success', 'Report updated successfully.');
    }

    public function destroy(ResidentReport $residentReport): RedirectResponse
    {
        Gate::authorize('delete', $residentReport);

        if ($residentReport->photo_path) {
            Storage::disk('local')->delete($residentReport->photo_path);
        }

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
            'category' => ['nullable', Rule::in(array_keys(ResidentReport::CATEGORIES))],
            'description' => 'required|string',
            'reported_at' => 'required|date',
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64,max_width=8000,max_height=8000'],
            'ai_assisted' => ['nullable', 'boolean'],
            // A report drafted with the AI Incident Lens must be explicitly reviewed by its author.
            'review_confirmed' => ['exclude_unless:ai_assisted,1', 'accepted'],
        ];

        if ($user->isAdmin()) {
            $rules['status'] = ['required', Rule::in(ResidentReport::STATUSES)];
            $rules['resolution_notes'] = ['nullable', 'string', 'max:5000'];
        }

        $data = $request->validate($rules, [
            'review_confirmed.accepted' => 'Please confirm that you reviewed the AI-assisted report before submitting it.',
            'photo.mimes' => 'The photo must be a JPG, PNG or WebP image.',
            'photo.max' => 'The photo must be smaller than 5 MB.',
        ]);

        unset($data['photo'], $data['review_confirmed']);
        $data['ai_assisted'] = $request->boolean('ai_assisted');

        return $data;
    }

    /**
     * Stores an uploaded photo on the private disk, replacing the previous one.
     *
     * @return array<string, string>
     */
    private function storePhoto(Request $request, ?ResidentReport $report = null): array
    {
        if (! $request->hasFile('photo')) {
            return [];
        }

        if ($report?->photo_path) {
            Storage::disk('local')->delete($report->photo_path);
        }

        return ['photo_path' => $request->file('photo')->store('report-photos', 'local')];
    }
}
