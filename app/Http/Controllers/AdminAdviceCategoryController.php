<?php

namespace App\Http\Controllers;

use App\Models\AdviceCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAdviceCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = trim($filters['search'] ?? '');

        $categories = AdviceCategory::withCount('advices')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('back.advice-categories.index', compact('categories', 'search'));
    }

    public function create(): View
    {
        return view('back.advice-categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $category = AdviceCategory::create($this->validated($request));

        return redirect()->route('admin.advice-categories.show', $category)->with('success', 'Advice category created successfully.');
    }

    public function show(AdviceCategory $adviceCategory): View
    {
        $adviceCategory->loadCount('advices');
        $advices = $adviceCategory->advices()->with('category')->latest()->paginate(10);

        return view('back.advice-categories.show', compact('adviceCategory', 'advices'));
    }

    public function edit(AdviceCategory $adviceCategory): View
    {
        return view('back.advice-categories.edit', compact('adviceCategory'));
    }

    public function update(Request $request, AdviceCategory $adviceCategory): RedirectResponse
    {
        $adviceCategory->update($this->validated($request, $adviceCategory));

        return redirect()->route('admin.advice-categories.show', $adviceCategory)->with('success', 'Advice category updated successfully.');
    }

    public function destroy(AdviceCategory $adviceCategory): RedirectResponse
    {
        if ($adviceCategory->advices()->exists()) {
            return redirect()->route('admin.advice-categories.index')
                ->withErrors(['category' => 'This category cannot be deleted while it contains advice.']);
        }

        $adviceCategory->delete();

        return redirect()->route('admin.advice-categories.index')->with('success', 'Advice category deleted successfully.');
    }

    /**
     * @return array{name: string, description: ?string}
     */
    private function validated(Request $request, ?AdviceCategory $adviceCategory = null): array
    {
        $uniqueName = Rule::unique('advice_categories', 'name');

        if ($adviceCategory !== null) {
            $uniqueName->ignore($adviceCategory);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'description' => ['nullable', 'string'],
        ]);
    }
}
