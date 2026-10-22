{{-- Command Center tabs (Response board / Incident map). The map loads the first time its tab is shown. --}}
@once
    @push('scripts')
        <script>
            (function () {
                const tabs = document.querySelectorAll('[data-cc-tab]');
                const panes = document.querySelectorAll('[data-cc-pane]');
                if (!tabs.length) { return; }

                function show(name, updateHash) {
                    panes.forEach((pane) => { pane.hidden = pane.dataset.ccPane !== name; });
                    document.querySelectorAll('.cc-tabs [data-cc-tab]').forEach((tab) => {
                        const active = tab.dataset.ccTab === name;
                        tab.classList.toggle('is-active', active);
                        tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    if (updateHash) {
                        try { history.replaceState(null, '', name === 'map' ? '#map' : window.location.pathname + window.location.search); } catch (e) {}
                    }
                    if (name === 'map') {
                        const map = document.querySelector('[data-constellation]');
                        if (map) { map.dispatchEvent(new CustomEvent('constellation:show')); }
                    }
                }

                tabs.forEach((tab) => tab.addEventListener('click', (event) => {
                    event.preventDefault();
                    show(tab.dataset.ccTab, true);
                    document.querySelector('.cc-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }));

                const initial = document.querySelector('[data-cc-pane]:not([hidden])');
                show(window.location.hash === '#map' ? 'map' : (initial ? initial.dataset.ccPane : 'board'), false);
            })();
        </script>
    @endpush
@endonce
