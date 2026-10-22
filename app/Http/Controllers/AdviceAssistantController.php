<?php

namespace App\Http\Controllers;

use App\Models\Advice;
use App\Models\AdviceConversation;
use App\Models\AdviceMessage;
use App\Services\AdviceAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdviceAssistantController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('advice.index', $request->query());
    }

    public function store(Request $request, AdviceAssistantService $assistant): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);
        $question = trim($data['message']);

        if ($question === '') {
            throw ValidationException::withMessages(['message' => 'Please enter a question.']);
        }

        $reply = $assistant->reply($question, collect());

        if ($reply === null) {
            return $this->unavailable($request, $assistant->isAvailable());
        }

        $conversation = DB::transaction(function () use ($request, $question, $reply): AdviceConversation {
            $conversation = $request->user()->adviceConversations()->create([
                'title' => Str::limit(Str::squish($question), 100),
            ]);
            $this->saveExchange($conversation, $question, $reply);

            return $conversation;
        });

        return $this->response($request, $conversation, $conversation->messages()->oldest('id')->get(), created: true);
    }

    public function send(Request $request, int $conversation, AdviceAssistantService $assistant): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);
        $question = trim($data['message']);

        if ($question === '') {
            throw ValidationException::withMessages(['message' => 'Please enter a question.']);
        }

        $ownedConversation = $request->user()->adviceConversations()->findOrFail($conversation);
        $history = $ownedConversation->messages()
            ->latest('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();
        $reply = $assistant->reply($question, $history);

        if ($reply === null) {
            return $this->unavailable($request, $assistant->isAvailable());
        }

        $messages = DB::transaction(function () use ($ownedConversation, $question, $reply): Collection {
            $savedMessages = $this->saveExchange($ownedConversation, $question, $reply);
            $ownedConversation->touch();

            return $savedMessages;
        });

        return $this->response($request, $ownedConversation, $messages);
    }

    /**
     * @param  array{answer: string, advices: Collection<int, Advice>}  $reply
     * @return Collection<int, AdviceMessage>
     */
    private function saveExchange(AdviceConversation $conversation, string $question, array $reply): Collection
    {
        return collect([
            $conversation->messages()->create([
                'role' => 'user',
                'content' => $question,
            ]),
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $reply['answer'],
                'advice_ids' => $reply['advices']->modelKeys(),
            ]),
        ]);
    }

    /**
     * @param  Collection<int, Advice>  $adviceById
     * @return list<array{title: string, category: string, url: string}>
     */
    private function referencesFor(AdviceMessage $message, Collection $adviceById): array
    {
        return collect($message->advice_ids ?? [])
            ->map(fn (int $id) => $adviceById->get($id))
            ->filter()
            ->map(fn (Advice $advice): array => [
                'title' => $advice->title,
                'category' => $advice->category->name,
                'url' => route('advice.show', $advice),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AdviceMessage>  $messages
     */
    private function response(
        Request $request,
        AdviceConversation $conversation,
        Collection $messages,
        bool $created = false,
    ): JsonResponse|RedirectResponse {
        if (! $request->expectsJson()) {
            return redirect()->route('advice.index', ['conversation' => $conversation->id])->withFragment('ai-assistant');
        }

        $adviceIds = $messages->flatMap(fn (AdviceMessage $message) => $message->advice_ids ?? [])->unique();
        $adviceById = $adviceIds->isEmpty()
            ? collect()
            : Advice::approved()->with('category')->whereIn('id', $adviceIds)->get()->keyBy('id');

        return response()->json([
            'conversation_id' => $conversation->id,
            'message_url' => route('assistant.messages.store', $conversation),
            'messages' => $messages->map(fn (AdviceMessage $message): array => [
                'role' => $message->role,
                'content' => $message->content,
                'references' => $this->referencesFor($message, $adviceById),
            ])->values(),
        ], $created ? 201 : 200);
    }

    private function unavailable(Request $request, bool $isConfigured): JsonResponse|RedirectResponse
    {
        $message = $isConfigured
            ? 'Gemini did not return a response. Please try again in a moment. If the problem continues, check storage/logs/laravel.log for the provider error.'
            : 'The AI assistant is not configured. Set AI_PROVIDER and GEMINI_API_KEY in the server environment.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }

        return back()->withInput()->withErrors(['message' => $message]);
    }
}
