@extends('layouts.front')

@section('title', 'HeatAlert - Cool Spots')

@section('content')

<section class="page-section">
    <div class="container">

        <div class="page-title">
            <div>
                <h1>Cool Spots</h1>
                <p>Find places nearby where you can cool down during a heatwave.</p>
            </div>
            <div class="toolbar">
                <form id="cool-spots-filter-form" action="{{ route('cool-spots.index') }}" method="GET" class="search-form" style="flex-wrap: wrap; row-gap: 0.5rem">
                    <input type="hidden" name="lat" value="{{ request('lat') }}">
                    <input type="hidden" name="lng" value="{{ request('lng') }}">
                    <select name="type" aria-label="Filter by type">
                        <option value="">All types</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected(request('type') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <select name="accessible" aria-label="Accessibility">
                        <option value="">All places</option>
                        <option value="1" @selected(request()->boolean('accessible'))>Accessible only</option>
                    </select>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search cool spots...">
                    <button type="submit" class="btn btn-primary" aria-label="Search"><x-icon name="search" /></button>
                    <button type="button" class="btn btn-outline" id="cool-spots-near-me"><x-icon name="pin" /> Near me</button>
                </form>
                <p class="cool-spot-nearby-status" id="cool-spots-near-me-status" role="status" aria-live="polite"></p>
                @if (request()->hasAny(['q', 'type', 'accessible', 'lat', 'lng']))
                    <a href="{{ route('cool-spots.index') }}" class="btn btn-outline">Reset</a>
                @endif
            </div>
        </div>

        <x-flash-messages />

        @auth
            <section class="panel cool-spot-recommendations">
                <div class="cool-spot-recommendation-heading">
                    <div>
                        <h2>Smart recommendation</h2>
                        <p>Describe your situation and get tailored cooling point suggestions.</p>
                    </div>
                    <x-icon name="bulb" />
                </div>
                <form id="cool-spot-recommendation-form" action="{{ route('cool-spots.recommend') }}" method="POST" class="cool-spot-recommendation-form">
                    @csrf
                    <input type="hidden" name="lat" value="{{ request('lat') }}">
                    <input type="hidden" name="lng" value="{{ request('lng') }}">
                    <div class="form-group">
                        <label for="cool-spot-situation">Describe your situation</label>
                        <textarea id="cool-spot-situation" name="situation" rows="2" minlength="5" maxlength="500" required placeholder="I have a baby, it's 40°C, no car"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" id="cool-spot-get-recommendations"><x-icon name="search" /> Get recommendations</button>
                </form>
                <p class="cool-spot-recommendation-status" id="cool-spot-recommendation-status" role="status" aria-live="polite"></p>
                <div class="residence-grid cool-spot-recommendation-results" id="cool-spot-recommendation-results" hidden></div>
            </section>
        @endauth

        <div class="residence-grid">
            @forelse ($spots as $spot)
                <article class="residence-card">
                    <div class="residence-body">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem">
                            <div class="stat-icon soft-blue"><x-icon :name="$spot->type->icon ?: 'snow'" /></div>
                            <span class="chip">{{ $spot->type->name }}</span>
                        </div>

                        <h3>{{ $spot->name }}</h3>
                        <div class="meta-row"><x-icon name="pin" /> {{ $spot->address }}</div>
                        @if ($spot->distance_km !== null)
                            <div class="meta-row"><x-icon name="pin" /> {{ number_format((float) $spot->distance_km, 1) }} km away</div>
                        @endif
                        @if ($spot->opening_hours)
                            <div class="meta-row"><x-icon name="clock" /> {{ $spot->opening_hours }}</div>
                        @endif
                        @php($openNow = $spot->isOpenNow())
                        @if ($openNow !== null)
                            <span class="status-badge {{ $openNow ? 'status-resolved' : 'status-pending' }}">{{ $openNow ? 'Open now' : 'Closed' }}</span>
                        @endif

                        <p class="residence-desc">{{ \Illuminate\Support\Str::limit($spot->description ?? 'No description provided.', 90) }}</p>

                        <div class="residence-footer">
                            <a href="{{ route('cool-spots.show', $spot) }}" class="card-link">View Details <x-icon name="arrow" /></a>
                            @if ($spot->is_accessible)
                                <span class="status-badge status-resolved">Accessible</span>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state" style="grid-column: 1 / -1">No cool spots match your search.</div>
            @endforelse
        </div>

        <div style="margin-top: 2rem">
            {{ $spots->links() }}
        </div>

    </div>
</section>

@endsection

@push('scripts')
    <script>
        (() => {
            const filterForm = document.getElementById('cool-spots-filter-form');
            const nearMeButton = document.getElementById('cool-spots-near-me');
            const nearMeStatus = document.getElementById('cool-spots-near-me-status');

            nearMeButton?.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    nearMeStatus.textContent = 'Geolocation is not available in this browser.';
                    return;
                }

                nearMeButton.disabled = true;
                nearMeStatus.textContent = 'Finding your location...';
                navigator.geolocation.getCurrentPosition(({ coords }) => {
                    filterForm.elements.lat.value = coords.latitude;
                    filterForm.elements.lng.value = coords.longitude;
                    filterForm.requestSubmit();
                }, () => {
                    nearMeButton.disabled = false;
                    nearMeStatus.textContent = 'Location access was denied or unavailable.';
                }, { enableHighAccuracy: true, timeout: 10000 });
            });

            const recommendationForm = document.getElementById('cool-spot-recommendation-form');
            if (!recommendationForm) return;

            const recommendationButton = document.getElementById('cool-spot-get-recommendations');
            const recommendationStatus = document.getElementById('cool-spot-recommendation-status');
            const recommendationResults = document.getElementById('cool-spot-recommendation-results');

            const createTextElement = (tag, className, text) => {
                const element = document.createElement(tag);
                if (className) element.className = className;
                element.textContent = text;
                return element;
            };

            const renderRecommendation = (recommendation) => {
                const card = document.createElement('article');
                card.className = 'residence-card';
                const body = document.createElement('div');
                body.className = 'residence-body';
                body.append(createTextElement('span', 'chip', recommendation.type || 'Cooling point'));
                body.append(createTextElement('h3', '', recommendation.name));
                body.append(createTextElement('p', 'meta-row', recommendation.address));
                body.append(createTextElement('p', 'cool-spot-recommendation-reason', recommendation.reason));

                if (recommendation.opening_hours) {
                    body.append(createTextElement('p', 'meta-row', `Hours: ${recommendation.opening_hours}`));
                }

                const badges = document.createElement('div');
                badges.className = 'cool-spot-recommendation-badges';
                if (recommendation.is_open_now !== null) {
                    badges.append(createTextElement(
                        'span',
                        `status-badge ${recommendation.is_open_now ? 'status-resolved' : 'status-pending'}`,
                        recommendation.is_open_now ? 'Open now' : 'Closed'
                    ));
                }
                if (recommendation.is_accessible) {
                    badges.append(createTextElement('span', 'status-badge status-resolved', 'Accessible'));
                }
                body.append(badges);

                const link = createTextElement('a', 'card-link', 'View Details');
                link.href = recommendation.url;
                body.append(link);
                card.append(body);

                return card;
            };

            recommendationForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                recommendationButton.disabled = true;
                recommendationStatus.textContent = 'Finding suitable cooling points...';
                recommendationResults.replaceChildren();
                recommendationResults.hidden = true;

                try {
                    const response = await fetch(recommendationForm.action, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': recommendationForm.querySelector('[name="_token"]').value,
                        },
                        body: JSON.stringify({
                            situation: recommendationForm.elements.situation.value,
                            lat: recommendationForm.elements.lat.value || null,
                            lng: recommendationForm.elements.lng.value || null,
                        }),
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        const validationMessage = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
                        throw new Error(validationMessage || 'Unable to get recommendations right now.');
                    }

                    recommendationStatus.textContent = data.source === 'rules'
                        ? 'Rule-based recommendations'
                        : 'AI recommendations';
                    data.recommendations.forEach((recommendation) => recommendationResults.append(renderRecommendation(recommendation)));
                    if (data.recommendations.length === 0) {
                        recommendationStatus.textContent += ': no active cooling points are available.';
                    } else {
                        recommendationResults.hidden = false;
                    }
                } catch (error) {
                    recommendationStatus.textContent = error.message || 'Unable to get recommendations right now.';
                } finally {
                    recommendationButton.disabled = false;
                }
            });
        })();
    </script>
@endpush