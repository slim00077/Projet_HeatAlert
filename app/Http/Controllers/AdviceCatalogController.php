<?php

namespace App\Http\Controllers;

use App\Models\Advice;
use App\Models\AdviceCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdviceCatalogController extends Controller
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
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(9)
            ->withQueryString();

        $categories = AdviceCategory::withCount('advices')->orderBy('name')->get();

        return view('front.advice.index', compact('advices', 'categories', 'search', 'filters'));
    }

    public function show(Advice $advice): View
    {
        $advice->load('category');
        $relatedAdvices = $advice->category
            ->advices()
            ->whereKeyNot($advice->getKey())
            ->latest()
            ->limit(3)
            ->get();

        return view('front.advice.show', compact('advice', 'relatedAdvices'));
    }
}
