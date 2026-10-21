{{-- Incident Command Center client: stage picker, task progress sliders, AI Constellation suggestions and AI Lens evidence. Server text is always inserted with textContent. --}}
@once
    @push('scripts')
        <script>
            (function () {
                const AI = window.HeatAlertInsights;
                const { el, badge } = AI;

                /* ---------- Workflow stage picker ---------- */
                const stageForm = document.querySelector('[data-stage-form]');
                document.querySelectorAll('[data-stage-pick]').forEach((button) => button.addEventListener('click', () => {
                    if (!stageForm) { return; }
                    stageForm.querySelector('[data-stage-select]').value = button.dataset.stagePick;
                    stageForm.classList.remove('is-highlighted');
                    void stageForm.offsetWidth;
                    stageForm.classList.add('is-highlighted');
                    stageForm.querySelector('input[name="note"]').focus();
                }));

                /* ---------- Task progress sliders ---------- */
                document.querySelectorAll('[data-progress-form]').forEach((form) => {
                    const input = form.querySelector('[data-progress-input]');
                    const output = form.querySelector('[data-progress-output]');
                    const save = form.querySelector('[data-progress-save]');
                    const initial = input.value;
                    save.hidden = true;
                    input.addEventListener('input', () => {
                        output.textContent = input.value + '%';
                        input.style.setProperty('--value', input.value + '%');
                        save.hidden = input.value === initial;
                    });
                });

                /* ---------- AI Incident Constellation suggestions ---------- */
                function renderSuggestions(container, data, options) {
                    const nodes = [];
                    if (data.semantic && data.semantic.notice) { nodes.push(el('p', 'ai-note ai-warning', data.semantic.notice)); }

                    if (!data.suggestions.length) {
                        nodes.push(el('p', 'ai-note', 'The Constellation found no other open report linked to these reports (semantic similarity or same category within a few weeks).'));
                        container.replaceChildren(...nodes);
                        return;
                    }

                    const list = el('ul', 'cc-suggestions');
                    data.suggestions.forEach((suggestion) => {
                        const item = el('li');
                        const label = el('label', 'cc-suggestion');
                        const box = el('input');
                        box.type = 'checkbox';
                        box.name = 'report_ids[]';
                        box.value = suggestion.id;
                        box.dataset.suggested = '1';
                        const main = el('span', 'cc-suggestion-main');
                        const top = el('span', 'incident-top');
                        top.append(el('b', null, '#' + suggestion.id + ' ' + suggestion.title), badge(suggestion.status));
                        main.append(top, el('small', 'cc-evidence', suggestion.evidence + (suggestion.related_to ? ' · linked to "' + suggestion.related_to + '"' : '')));
                        main.append(el('small', 'muted-text', suggestion.category + ' · reported ' + suggestion.reported_at));
                        if (suggestion.excerpt) { main.append(el('span', 'incident-text', suggestion.excerpt)); }
                        label.append(box, main);
                        item.append(label);
                        list.append(item);
                    });
                    nodes.push(list);

                    if (options.linkUrl) {
                        const form = el('form', 'cc-suggest-form');
                        form.method = 'POST';
                        form.action = options.linkUrl;
                        [['_token', AI.csrf], ['source', 'constellation']].forEach(([name, value]) => {
                            const hidden = el('input');
                            hidden.type = 'hidden';
                            hidden.name = name;
                            hidden.value = value;
                            form.append(hidden);
                        });
                        const submit = el('button', 'btn btn-primary btn-sm', 'Confirm and link selected reports');
                        submit.type = 'submit';
                        form.append(list, submit);
                        nodes[nodes.length - 1] = form;
                    } else {
                        nodes.push(el('p', 'ai-note', 'Ticked suggestions are added to the case when you open it.'));
                    }

                    container.replaceChildren(...nodes);
                }

                const room = document.querySelector('[data-response-room]');
                const suggest = document.querySelector('[data-suggest]');
                if (room && suggest) {
                    const results = room.querySelector('[data-suggest-results]');
                    suggest.addEventListener('click', () => AI.run(suggest, results, () =>
                        AI.request(room.dataset.suggestUrl).then((data) => renderSuggestions(results, data, { linkUrl: data.link_url }))));
                }

                const creator = document.querySelector('[data-case-create]');
                if (creator) {
                    const button = creator.querySelector('[data-suggest]');
                    const results = creator.querySelector('[data-suggest-results]');
                    const source = creator.querySelector('input[name="source"]');
                    button.addEventListener('click', () => {
                        const ids = [...creator.querySelectorAll('input[name="report_ids[]"]:checked:not([data-suggested])')].map((box) => box.value);
                        if (!ids.length) {
                            results.replaceChildren(el('p', 'flash flash-error', 'Tick at least one report first: the Constellation looks for reports related to it.'));
                            return;
                        }
                        const url = new URL(creator.dataset.suggestUrl, window.location.origin);
                        ids.forEach((id) => url.searchParams.append('report_ids[]', id));
                        AI.run(button, results, () => AI.request(url.toString()).then((data) => {
                            // Reports already listed on the form are ticked there instead of being suggested twice.
                            data.suggestions = data.suggestions.filter((suggestion) => {
                                const existing = creator.querySelector('input[name="report_ids[]"][value="' + suggestion.id + '"]:not([data-suggested])');
                                if (existing) { existing.closest('[data-report-row]')?.classList.add('is-suggested'); }
                                return !existing;
                            });
                            renderSuggestions(results, data, {});
                        }));
                    });
                    creator.addEventListener('change', (event) => {
                        if (event.target.dataset && event.target.dataset.suggested && event.target.checked) { source.value = 'constellation'; }
                    });
                }

                /* ---------- AI Incident Lens on evidence ---------- */
                function renderLens(container, data) {
                    const nodes = [];
                    const head = el('div', 'toolbar');
                    const label = AI.sourceBadge(data.source, data.model);
                    if (label) { head.append(label); }
                    if (data.photo_sent) { head.append(el('span', 'chip', 'photo analysed')); }
                    nodes.push(head);
                    if (data.notice) { nodes.push(el('p', data.source === 'ai' ? 'ai-note' : 'ai-note ai-warning', data.notice)); }

                    const analysis = data.analysis;
                    if (analysis) {
                        if (analysis.safety_note) { nodes.push(el('div', 'flash flash-error', 'Safety: ' + analysis.safety_note)); }
                        const groups = [
                            ['What the evidence shows', analysis.observations.map((item) => item.detail + ' (' + item.source + ')')],
                            ['Possible causes (not confirmed)', analysis.possible_causes],
                            ['What cannot be determined', analysis.uncertainties],
                            ['Questions to check on site', analysis.follow_up_questions],
                        ];
                        groups.forEach(([title, items]) => {
                            if (!items || !items.length) { return; }
                            const list = el('ul', 'tips-list');
                            items.forEach((item) => list.append(el('li', null, item)));
                            nodes.push(el('h5', null, title), list);
                        });
                        if (analysis.suggested_category_label) {
                            nodes.push(el('p', 'ai-note', 'Suggested category: ' + analysis.suggested_category_label + ' (' + analysis.confidence + ' confidence). The report itself is not changed.'));
                        }
                    }
                    container.replaceChildren(...nodes);
                }

                document.querySelectorAll('[data-evidence-url]').forEach((button) => {
                    const results = button.closest('[data-evidence-card]').querySelector('[data-evidence-results]');
                    button.addEventListener('click', () => AI.run(button, results, () =>
                        AI.request(button.dataset.evidenceUrl, { method: 'POST' }).then((data) => renderLens(results, data))));
                });
            })();
        </script>
    @endpush
@endonce
