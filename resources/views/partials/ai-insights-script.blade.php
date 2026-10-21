{{-- Shared client helpers of the AI features (Incident Command Center, Lens, Constellation). Every server value is inserted with textContent, never as HTML. --}}
@once
    @push('scripts')
        <script>
            window.HeatAlertInsights = (function () {
                const csrf = @json(csrf_token());

                const el = (tag, className, text) => {
                    const node = document.createElement(tag);
                    if (className) { node.className = className; }
                    if (text !== undefined && text !== null) { node.textContent = text; }
                    return node;
                };

                const link = (text, url, className) => {
                    const anchor = el('a', className, text);
                    anchor.href = url;
                    return anchor;
                };

                const badge = (status) => el('span', 'status-badge status-' + String(status).toLowerCase().replace(/[^a-z0-9]+/g, '-'), status);

                const sourceBadge = (source, model) => {
                    if (source === 'ai') { return el('span', 'status-badge status-admin', 'AI-generated' + (model ? ' · ' + model : '')); }
                    if (source === 'keywords') { return el('span', 'status-badge status-in-progress', 'Basic matching, not AI'); }
                    return null;
                };

                async function request(url, options = {}) {
                    const response = await fetch(url, {
                        method: options.method || 'GET',
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: options.body ? JSON.stringify(options.body) : undefined,
                    });
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                        throw new Error(firstError || (response.status === 429 ? 'Too many requests, please wait a minute.' : (response.status === 403 || response.status === 404 ? 'You are not allowed to analyse this record.' : 'The request failed. Please try again.')));
                    }

                    return data;
                }

                function run(button, container, task) {
                    const original = Array.from(button.childNodes);
                    button.disabled = true;
                    button.textContent = 'Analysing...';
                    container.replaceChildren(el('p', 'ai-loading', 'Comparing records, this can take a few seconds...'));
                    container.setAttribute('aria-busy', 'true');

                    task().catch((error) => {
                        container.replaceChildren(el('div', 'flash flash-error', error.message));
                    }).finally(() => {
                        button.disabled = false;
                        button.replaceChildren(...original);
                        container.removeAttribute('aria-busy');
                    });
                }

                return { csrf, el, link, badge, sourceBadge, request, run };
            })();
        </script>
    @endpush
@endonce
