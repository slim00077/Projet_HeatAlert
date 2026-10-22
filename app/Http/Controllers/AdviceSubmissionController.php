<?php

namespace App\Http\Controllers;

use App\Models\Advice;
use App\Notifications\AdviceModerationUpdated;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdviceSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $selectedAdvice = $request->integer('advice');

        if ($selectedAdvice !== 0) {
            abort_unless(
                $request->user()->adviceSubmissions()->whereKey($selectedAdvice)->exists(),
                404,
            );
        }

        $advices = Advice::query()
            ->whereBelongsTo($request->user(), 'submitter')
            ->with([
                'category:id,name',
                'moderationEvents' => fn ($query) => $query
                    ->whereIn('action', ['approved', 'rejected', 'changes_requested'])
                    ->latest('id')
                    ->limit(1),
            ])
            ->latest('created_at')
            ->paginate(10);

        $request->user()
            ->unreadNotifications()
            ->where('type', AdviceModerationUpdated::class)
            ->update(['read_at' => now()]);

        return view('front.advice.submissions', compact('advices', 'selectedAdvice'));
    }
}
