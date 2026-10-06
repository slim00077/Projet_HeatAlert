<?php

namespace App\Http\Controllers;

use App\Models\Advice;
use App\Models\AdviceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAdviceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'exists:advice_categories,id'],
        ]);
        $search = trim($filters['search'] ?? '');

        $advices = Advice::with('category')
            ->when(isset($filters['category']), fn ($query) => $query->where('advice_category_id', $filters['category']))
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

        return view('back.advices.index', compact('advices', 'categories', 'filters', 'search'));
    }

    public function create(): View
    {
        return view('back.advices.create', ['categories' => AdviceCategory::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $advice = Advice::create($this->validated($request));

        return redirect()->route('admin.advices.show', $advice)->with('success', 'Advice created successfully.');
    }

    public function show(Advice $advice): View
    {
        $advice->load('category');

        return view('back.advices.show', compact('advice'));
    }

    public function edit(Advice $advice): View
    {
        return view('back.advices.edit', [
            'advice' => $advice,
            'categories' => AdviceCategory::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Advice $advice): RedirectResponse
    {
        $advice->update($this->validated($request));

        return redirect()->route('admin.advices.show', $advice)->with('success', 'Advice updated successfully.');
    }

    public function destroy(Advice $advice): RedirectResponse
    {
        $advice->delete();

        return redirect()->route('admin.advices.index')->with('success', 'Advice deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'advice_category_id' => ['required', 'integer', 'exists:advice_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
        ]);
    }
}
