<?php

namespace App\Http\Controllers;

use App\Models\ResponseCase;
use App\Models\ResponseTask;
use App\Models\User;
use App\Services\ResponseCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Response tasks of an incident case: planning, assignment, deadlines and progress.
 */
class ResponseTaskController extends Controller
{
    public function store(Request $request, ResponseCase $responseCase, ResponseCaseService $service): RedirectResponse
    {
        Gate::authorize('update', $responseCase);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer', Rule::in($this->assigneeIds($responseCase))],
            'assignee_label' => ['nullable', 'string', 'max:120'],
            'due_date' => ['nullable', 'date'],
        ], [
            'assigned_to.in' => 'Tasks can only be assigned to the residence manager or an administrator.',
        ]);

        $service->addTask($request->user(), $responseCase, $data);

        return back()->with('success', 'Task added to the response plan.');
    }

    public function update(Request $request, ResponseTask $responseTask, ResponseCaseService $service): RedirectResponse
    {
        Gate::authorize('update', $responseTask->responseCase);

        $data = $request->validate([
            'status' => ['sometimes', 'required', Rule::in(ResponseTask::STATUSES)],
            'progress' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', Rule::in($this->assigneeIds($responseTask->responseCase))],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $service->updateTask($request->user(), $responseTask, $data);

        return back()->with('success', "\"{$responseTask->title}\" is now {$responseTask->status} ({$responseTask->progress}%).");
    }

    public function destroy(ResponseTask $responseTask): RedirectResponse
    {
        Gate::authorize('update', $responseTask->responseCase);

        $responseTask->delete();

        return back()->with('success', 'Task removed.');
    }

    /**
     * @return list<int>
     */
    private function assigneeIds(ResponseCase $case): array
    {
        return User::whereKey($case->residence->user_id)->orWhere('role', User::ROLE_ADMIN)->pluck('id')->all();
    }
}
