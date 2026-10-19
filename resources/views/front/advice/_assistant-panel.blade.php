<section class="advice-assistant" id="ai-assistant" aria-labelledby="advice-assistant-title">
    <header class="assistant-page-heading">
        <div>
            <p class="home-eyebrow">HeatAlert knowledge assistant</p>
            <h2 id="advice-assistant-title">Ask about staying safe in the heat</h2>
            <p>Get guidance grounded in approved HeatAlert advice. Ask in English, français, العربية or derja tounsi.</p>
        </div>
        <span class="assistant-status {{ $isAiAvailable ? 'is-ready' : 'is-unavailable' }}">
            <span aria-hidden="true"></span>
            {{ $isAiAvailable ? 'AI ready' : 'AI not configured' }}
        </span>
    </header>

    @if (! $isAiAvailable)
        <div class="assistant-configuration-alert" role="alert">
            The AI provider is not configured on the server. Set <code>AI_PROVIDER=gemini</code> and <code>GEMINI_API_KEY</code> in your local environment, then restart Laravel.
        </div>
    @endif

    <div class="assistant-layout is-embedded">
        <aside class="assistant-history" aria-label="Conversation history">
            <div class="assistant-history-heading">
                <h3>Your conversations</h3>
                <a class="btn btn-outline btn-sm" href="{{ route('advice.index', ['new' => 1]) }}#ai-assistant">New chat</a>
            </div>
            <nav class="assistant-conversation-list">
                @forelse ($conversations as $item)
                    <a href="{{ route('advice.index', ['conversation' => $item->id]) }}#ai-assistant"
                        @class(['assistant-conversation-link', 'is-current' => $conversation?->is($item)])
                        @if ($conversation?->is($item)) aria-current="page" @endif>
                        <strong>{{ $item->title }}</strong>
                        <span>{{ $item->messages->first()?->content ?? 'No messages yet' }}</span>
                        <time datetime="{{ $item->updated_at->toIso8601String() }}">{{ $item->updated_at->diffForHumans() }}</time>
                    </a>
                @empty
                    <p class="assistant-history-empty">Your conversations will appear here.</p>
                @endforelse
            </nav>
            <p class="assistant-history-note">Your chat history is private to your account.</p>
        </aside>

        <section class="assistant-chat" aria-label="HeatAlert chat">
            <header class="assistant-chat-header">
                <span class="assistant-avatar" aria-hidden="true">☀</span>
                <div>
                    <h3>HeatAlert Assistant</h3>
                    <p>Practical heat-safety guidance from our advice library</p>
                </div>
            </header>

            <div class="assistant-messages" data-assistant-messages aria-live="polite" aria-relevant="additions text">
                @if ($messages->isEmpty())
                    <div class="assistant-welcome" data-assistant-welcome>
                        <span class="assistant-welcome-icon" aria-hidden="true">✦</span>
                        <h3>How can I help you today?</h3>
                        <p>Ask about heat, hydration, children, older adults or what to do during a heatwave.</p>
                        <div class="assistant-suggestions">
                            <button type="button" data-assistant-prompt="Kifech n7mi jeddi mel 7arr?">Protect an older relative</button>
                            <button type="button" data-assistant-prompt="Combien d’eau faut-il boire pendant une canicule ?">Hydration during a heatwave</button>
                            <button type="button" data-assistant-prompt="شنوة نعمل كان نحس بدوخة من السخانة؟">What to do if I feel dizzy</button>
                        </div>
                    </div>
                @else
                    @foreach ($messages as $message)
                        <article @class(['assistant-message', 'is-user' => $message->role === 'user']) data-role="{{ $message->role }}">
                            <span class="assistant-message-avatar" aria-hidden="true">{{ $message->role === 'user' ? auth()->user()->initials() : '☀' }}</span>
                            <div class="assistant-message-content">
                                <span class="assistant-message-label">{{ $message->role === 'user' ? 'You' : 'HeatAlert Assistant' }}</span>
                                <p dir="auto">{{ $message->content }}</p>
                                @if (($references[$message->id] ?? []) !== [])
                                    <div class="assistant-references">
                                        <span>Related HeatAlert advice</span>
                                        @foreach ($references[$message->id] as $reference)
                                            <a href="{{ $reference['url'] }}">
                                                <strong>{{ $reference['title'] }}</strong>
                                                <small>{{ $reference['category'] }}</small>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                @endif
            </div>

            <div class="assistant-error" data-assistant-error role="alert" hidden></div>
            <form class="assistant-composer" method="POST"
                action="{{ $conversation ? route('assistant.messages.store', $conversation) : route('assistant.conversations.store') }}"
                data-assistant-form>
                @csrf
                <label class="sr-only" for="assistant-message">Your question</label>
                <textarea id="assistant-message" name="message" rows="2" maxlength="2000" required
                    placeholder="Ask anything about heat safety..." data-assistant-input>{{ old('message') }}</textarea>
                @error('message')
                    <span class="assistant-validation-error">{{ $message }}</span>
                @enderror
                <div class="assistant-composer-footer">
                    <span>AI can make mistakes. This assistant is not emergency care.</span>
                    <button class="btn btn-primary" type="submit" data-assistant-submit {{ $isAiAvailable ? '' : 'disabled' }}>
                        <span data-submit-label>Send</span>
                        <span aria-hidden="true">↑</span>
                    </button>
                </div>
            </form>
        </section>
    </div>
</section>
