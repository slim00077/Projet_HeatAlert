<?php

namespace App\Http\Controllers;

use App\Enums\Severity;
use App\Models\Advice;
use App\Models\AdviceCategory;
use App\Models\AdviceMessage;
use App\Services\AdviceAssistantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdviceCatalogController extends Controller
{
    public function index(Request $request, AdviceAssistantService $assistant): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'exists:advice_categories,id'],
            'risk_level' => ['nullable', Rule::enum(Severity::class)],
        ]);
        $search = trim($filters['search'] ?? '');

        $advices = Advice::approved()->with('category')
            ->when(isset($filters['category']), fn ($query) => $query->where('advice_category_id', $filters['category']))
            ->when(isset($filters['risk_level']), fn ($query) => $query->where('risk_level', $filters['risk_level']))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(9)
            ->withQueryString();

        $categories = AdviceCategory::withCount([
            'advices' => fn (Builder $query) => $query->approved(),
        ])->orderBy('name')->get();

        $assistantConversations = collect();
        $assistantConversation = null;
        $assistantMessages = collect();
        $assistantReferences = collect();
        $isAiAvailable = false;

        if ($request->user() !== null) {
            $assistantConversations = $request->user()
                ->adviceConversations()
                ->with(['messages' => fn ($query) => $query->latest('id')->limit(1)])
                ->latest('updated_at')
                ->limit(30)
                ->get();

            if ($request->filled('conversation')) {
                $assistantConversation = $request->user()
                    ->adviceConversations()
                    ->findOrFail($request->integer('conversation'));
            } elseif (! $request->boolean('new')) {
                $assistantConversation = $assistantConversations->first();
            }

            if ($assistantConversation !== null) {
                $assistantMessages = $assistantConversation->messages()
                    ->latest('id')
                    ->limit(80)
                    ->get()
                    ->reverse()
                    ->values();

                $adviceIds = $assistantMessages
                    ->flatMap(fn (AdviceMessage $message) => $message->advice_ids ?? [])
                    ->unique()
                    ->all();
                $adviceById = $adviceIds === []
                    ? collect()
                    : Advice::approved()->with('category')->whereIn('id', $adviceIds)->get()->keyBy('id');

                $assistantReferences = $assistantMessages->mapWithKeys(fn (AdviceMessage $message): array => [
                    $message->id => collect($message->advice_ids ?? [])
                        ->map(fn (int $id) => $adviceById->get($id))
                        ->filter()
                        ->map(fn (Advice $advice): array => [
                            'title' => $advice->title,
                            'category' => $advice->category->name,
                            'url' => route('advice.show', $advice),
                        ])
                        ->values()
                        ->all(),
                ]);
            }

            $isAiAvailable = $assistant->isAvailable();
        }

        return view('front.advice.index', [
            'advices' => $advices,
            'categories' => $categories,
            'search' => $search,
            'filters' => $filters,
            'riskLevels' => Severity::cases(),
            'assistantConversations' => $assistantConversations,
            'assistantConversation' => $assistantConversation,
            'assistantMessages' => $assistantMessages,
            'assistantReferences' => $assistantReferences,
            'isAiAvailable' => $isAiAvailable,
        ]);
    }

    public function show(Advice $advice): View
    {
        abort_unless($advice->is_approved, 404);

        $advice->load('category');
        $relatedAdvices = $advice->category
            ->advices()
            ->approved()
            ->whereKeyNot($advice->getKey())
            ->latest()
            ->limit(3)
            ->get();

        return view('front.advice.show', compact('advice', 'relatedAdvices'));
    }

    public function create(): View
    {
        return view('front.advice.create', [
            'categories' => AdviceCategory::orderBy('name')->get(),
            'riskLevels' => Severity::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'advice_category_id' => ['required', 'integer', 'exists:advice_categories,id'],
            'risk_level' => ['required', Rule::enum(Severity::class)],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $data = collect($validated)->except('image')->all();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('advice', 'public');
        }

        Advice::create($data);

        return redirect()->route('advice.index')->with('success', 'Thank you. Your advice was submitted for administrator review.');
    }
}
