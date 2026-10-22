<?php

namespace App\Http\Controllers;

use App\Enums\Severity;
use App\Models\Advice;
use App\Models\AdviceCategory;
use App\Models\AdviceModerationEvent;
use App\Models\User;
use App\Notifications\AdviceModerationUpdated;
use App\Services\AdviceQualityAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAdviceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'exists:advice_categories,id'],
            'approval' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'changes_requested'])],
        ]);
        $search = trim($filters['search'] ?? '');

        $advices = Advice::with(['category', 'submitter:id,name'])
            ->when(isset($filters['category']), fn ($query) => $query->where('advice_category_id', $filters['category']))
            ->when(isset($filters['approval']), fn ($query) => $query->where('moderation_status', $filters['approval']))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $categories = AdviceCategory::orderBy('name')->get();
        $pendingAdviceCount = Advice::whereIn('moderation_status', ['pending', 'changes_requested'])->count();
        $statusCounts = Advice::query()
            ->selectRaw('moderation_status, COUNT(*) as total')
            ->groupBy('moderation_status')
            ->pluck('total', 'moderation_status');

        return view('back.advices.index', compact('advices', 'categories', 'filters', 'search', 'pendingAdviceCount', 'statusCounts'));
    }

    public function create(): View
    {
        return view('back.advices.create', [
            'categories' => AdviceCategory::orderBy('name')->get(),
            'riskLevels' => Severity::cases(),
        ]);
    }

    public function store(Request $request, AdviceQualityAnalyzer $qualityAnalyzer): RedirectResponse
    {
        $advice = Advice::create($this->validated($request) + $this->storeImage($request) + [
            'submitted_by' => $request->user()->getKey(),
            'is_approved' => false,
            'moderation_status' => 'pending',
        ]);
        $qualityAnalyzer->analyze($advice->load('category'), $request->user());

        return redirect()->route('admin.advices.show', $advice)->with('success', 'Advice saved for review. Check the AI quality report and make a moderation decision.');
    }

    public function approve(Request $request, Advice $advice): RedirectResponse
    {
        $advice->update(['is_approved' => true, 'moderation_status' => 'approved']);
        $this->recordModeration($advice, $request->user(), 'approved');

        return redirect()->route('admin.advices.show', $advice)->with('success', 'Advice approved and published.');
    }

    public function reject(Request $request, Advice $advice): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $advice->update(['is_approved' => false, 'moderation_status' => 'rejected']);
        $this->recordModeration($advice, $request->user(), 'rejected', $data['note']);

        return redirect()->route('admin.advices.show', $advice)->with('success', 'Advice rejected. The decision was added to the moderation log.');
    }

    public function requestChanges(Request $request, Advice $advice): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $advice->update(['is_approved' => false, 'moderation_status' => 'changes_requested']);
        $this->recordModeration($advice, $request->user(), 'changes_requested', $data['note']);

        return redirect()->route('admin.advices.show', $advice)->with('success', 'Changes requested. The decision was added to the moderation log.');
    }

    public function reanalyze(Request $request, Advice $advice, AdviceQualityAnalyzer $qualityAnalyzer): RedirectResponse
    {
        $qualityAnalyzer->analyze($advice->load('category'), $request->user());

        return redirect()->route('admin.advices.show', $advice)->with('success', 'A new quality report was recorded. Human review is still required.');
    }

    public function show(Advice $advice): View
    {
        $advice->load([
            'category',
            'qualityReports' => fn ($query) => $query->latest('id')->limit(10),
            'moderationEvents' => fn ($query) => $query->with('actor')->latest('id')->limit(30),
        ]);
        $relatedAdviceIds = collect($advice->qualityReports)
            ->flatMap(fn ($report) => collect($report->findings)->pluck('related_advice_ids')->flatten())
            ->unique()
            ->all();
        $relatedAdvices = $relatedAdviceIds === []
            ? collect()
            : Advice::whereIn('id', $relatedAdviceIds)->get(['id', 'title'])->keyBy('id');

        return view('back.advices.show', compact('advice', 'relatedAdvices'));
    }

    public function edit(Advice $advice): View
    {
        return view('back.advices.edit', [
            'advice' => $advice,
            'categories' => AdviceCategory::orderBy('name')->get(),
            'riskLevels' => Severity::cases(),
        ]);
    }

    public function update(Request $request, Advice $advice, AdviceQualityAnalyzer $qualityAnalyzer): RedirectResponse
    {
        $validated = $this->validated($request);
        $reviewFields = ['advice_category_id', 'risk_level', 'title', 'summary', 'content', 'source_url'];
        $previousValues = collect($reviewFields)->mapWithKeys(
            fn (string $field): array => [$field => $advice->getRawOriginal($field)],
        )->all();
        $advice->update($validated + $this->storeImage($request, $advice));
        $requiresReview = collect($reviewFields)->contains(
            fn (string $field): bool => $advice->getRawOriginal($field) !== ($previousValues[$field] ?? null),
        );

        if ($requiresReview) {
            $advice->update(['is_approved' => false, 'moderation_status' => 'pending']);
            $qualityAnalyzer->analyze($advice->load('category'), $request->user());
        }

        return redirect()->route('admin.advices.show', $advice)->with('success', 'Advice updated successfully.');
    }

    public function destroy(Advice $advice): RedirectResponse
    {
        if ($advice->image_path && ! str_starts_with($advice->image_path, 'http://') && ! str_starts_with($advice->image_path, 'https://')) {
            Storage::disk('public')->delete($advice->image_path);
        }

        $advice->delete();

        return redirect()->route('admin.advices.index')->with('success', 'Advice deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'advice_category_id' => ['required', 'integer', 'exists:advice_categories,id'],
            'risk_level' => ['required', Rule::enum(Severity::class)],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        return collect($validated)->except('image')->all();
    }

    /**
     * Stores the uploaded picture and returns the column to save.
     *
     * @return array<string, string>
     */
    private function storeImage(Request $request, ?Advice $advice = null): array
    {
        if (! $request->hasFile('image')) {
            return [];
        }

        if ($advice?->image_path && ! str_starts_with($advice->image_path, 'http://') && ! str_starts_with($advice->image_path, 'https://')) {
            Storage::disk('public')->delete($advice->image_path);
        }

        return ['image_path' => $request->file('image')->store('advice', 'public')];
    }

    private function recordModeration(Advice $advice, User $actor, string $action, ?string $note = null): void
    {
        AdviceModerationEvent::create([
            'advice_id' => $advice->getKey(),
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'note' => $note,
        ]);

        if ($advice->submitter()->exists()) {
            $advice->submitter()->firstOrFail()->notify(new AdviceModerationUpdated(
                $advice,
                $action,
                $note,
            ));
        }
    }
}
