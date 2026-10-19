@if ($alerts->isNotEmpty())
    <div class="danger-banner" role="alert" data-danger-banner data-key="{{ $alerts->pluck('id')->sort()->join('-') }}">
        <div class="danger-banner-inner">
            <strong><x-icon name="warning" /> Danger heat alert</strong>
            <ul>
                @foreach ($alerts as $alert)
                    <li>
                        <a href="{{ route('front.alerts.index', ['neighborhood_id' => $alert->neighborhood_id]) }}">
                            {{ $alert->neighborhood->name }}@if ($alert->temperature !== null) · {{ $alert->temperature }} °C @endif
                            - {{ $alert->title }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <button type="button" class="danger-banner-close" aria-label="Dismiss" data-danger-dismiss>&times;</button>
        </div>
    </div>
    <script>
        (function () {
            const banner = document.querySelector('[data-danger-banner]');
            const key = 'danger-banner-' + banner.dataset.key;
            try { if (sessionStorage.getItem(key)) { banner.remove(); return; } } catch (e) {}
            banner.querySelector('[data-danger-dismiss]').addEventListener('click', function () {
                try { sessionStorage.setItem(key, '1'); } catch (e) {}
                banner.remove();
            });
        })();
    </script>
@endif
