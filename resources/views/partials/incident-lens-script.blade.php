{{-- AI Incident Lens client: photo preview, multimodal analysis request and review-before-submit workflow. All server text is inserted with textContent. --}}
@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-lens]').forEach((lens) => {
                const { el, badge, sourceBadge, csrf } = window.HeatAlertInsights;
                const form = lens.closest('form');
                const file = lens.querySelector('[data-lens-file]');
                const preview = lens.querySelector('[data-lens-preview]');
                const dropText = lens.querySelector('[data-lens-drop-text]');
                const drop = lens.querySelector('[data-lens-drop]');
                const clear = lens.querySelector('[data-lens-clear]');
                const analyse = lens.querySelector('[data-lens-analyze]');
                const result = lens.querySelector('[data-lens-result]');
                const assisted = form.querySelector('[data-lens-assisted]');
                const confirmBox = form.querySelector('[data-lens-confirm]');
                const field = (id) => form.querySelector('#' + id);
                let previewUrl = null;

                const showPhoto = () => {
                    const photo = file.files[0];
                    if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }

                    if (!photo) {
                        preview.hidden = true;
                        dropText.hidden = false;
                        clear.hidden = true;
                        drop.classList.remove('has-photo');
                        return;
                    }

                    if (!['image/jpeg', 'image/png', 'image/webp'].includes(photo.type) || photo.size > 5 * 1024 * 1024) {
                        file.value = '';
                        result.replaceChildren(el('div', 'flash flash-error', 'Please choose a JPG, PNG or WebP photo smaller than 5 MB.'));
                        return showPhoto();
                    }

                    previewUrl = URL.createObjectURL(photo);
                    preview.src = previewUrl;
                    preview.hidden = false;
                    dropText.hidden = true;
                    clear.hidden = false;
                    drop.classList.add('has-photo');
                };

                file.addEventListener('change', showPhoto);
                clear.addEventListener('click', () => { file.value = ''; showPhoto(); });

                ['dragenter', 'dragover'].forEach((type) => drop.addEventListener(type, (event) => { event.preventDefault(); drop.classList.add('is-dragging'); }));
                ['dragleave', 'drop'].forEach((type) => drop.addEventListener(type, () => drop.classList.remove('is-dragging')));
                drop.addEventListener('drop', (event) => {
                    event.preventDefault();
                    if (event.dataTransfer.files.length) {
                        file.files = event.dataTransfer.files;
                        showPhoto();
                    }
                });

                // Applying any AI suggestion marks the report as AI-assisted and requires the explicit review checkbox.
                const markAssisted = (...targets) => {
                    assisted.value = '1';
                    confirmBox.hidden = false;
                    confirmBox.querySelector('input').required = true;
                    targets.forEach((target) => {
                        target.classList.remove('lens-applied');
                        void target.offsetWidth;
                        target.classList.add('lens-applied');
                    });
                };

                const section = (title, className) => {
                    const box = el('section', 'lens-block' + (className ? ' ' + className : ''));
                    box.append(el('h4', null, title));
                    return box;
                };

                const button = (text, className, onClick) => {
                    const node = el('button', className, text);
                    node.type = 'button';
                    node.addEventListener('click', onClick);
                    return node;
                };

                function render(data) {
                    if (data.source !== 'ai') {
                        const box = el('div', 'lens-unavailable');
                        box.append(el('strong', null, 'No AI analysis'), el('p', null, data.notice || 'The AI Lens is not available right now.'));
                        box.append(el('p', 'ai-note', 'Nothing was guessed or filled in for you. You can still write and submit your report yourself.'));
                        result.replaceChildren(box);
                        return;
                    }

                    const analysis = data.analysis;
                    const grid = el('div', 'lens-grid');

                    // Left: the photo (or the description) beside the evidence the AI says it observed.
                    const figure = el('figure', 'lens-figure');
                    if (data.photo_sent && previewUrl) {
                        const img = el('img');
                        img.src = previewUrl;
                        img.alt = 'Photo attached to your report';
                        figure.append(img);
                    } else {
                        figure.append(el('div', 'lens-no-photo', 'No photo: the analysis is based on your description only.'));
                    }
                    const caption = el('figcaption');
                    const assessments = { relevant: 'Photo shows the problem', unclear: 'Photo is unclear', unrelated: 'Photo seems unrelated', none: 'No photo analysed' };
                    caption.append(el('span', 'status-badge ' + (analysis.photo_assessment === 'relevant' ? 'status-resolved' : 'status-in-progress'), assessments[analysis.photo_assessment] || 'Photo'));
                    figure.append(caption);

                    const evidence = section('What the AI could observe');
                    const list = el('ol', 'lens-evidence');
                    analysis.observations.forEach((item) => {
                        const li = el('li');
                        li.append(el('span', null, item.detail), el('small', 'chip', item.source === 'photo' ? 'from the photo' : 'from your text'));
                        list.append(li);
                    });
                    if (!analysis.observations.length) { list.append(el('li', 'muted-text', 'Nothing specific could be observed.')); }
                    evidence.append(list);
                    figure.append(evidence);
                    grid.append(figure);

                    // Right: suggestions the resident can apply, each one explicit.
                    const panel = el('div', 'lens-panel');
                    const head = el('div', 'toolbar');
                    head.append(sourceBadge('ai', data.model), el('span', 'chip', 'confidence: ' + analysis.confidence));
                    panel.append(head, el('p', 'ai-note', data.notice));

                    if (analysis.safety_note) {
                        panel.append(el('div', 'flash flash-error', 'Safety: ' + analysis.safety_note));
                    }

                    if (analysis.suggested_category) {
                        const block = section('Suggested category');
                        const row = el('div', 'lens-suggestion');
                        row.append(el('strong', 'lens-category', analysis.suggested_category_label));
                        row.append(button('Apply category', 'btn btn-outline btn-sm', () => {
                            field('category').value = analysis.suggested_category;
                            markAssisted(field('category'));
                        }));
                        block.append(row);
                        if (analysis.category_reason) { block.append(el('p', 'ai-note', analysis.category_reason)); }
                        panel.append(block);
                    }

                    const draft = section('Clearer report draft');
                    const quote = el('blockquote', 'lens-draft');
                    quote.append(el('strong', null, analysis.draft.title), el('p', null, analysis.draft.description));
                    draft.append(quote);
                    const draftActions = el('div', 'toolbar');
                    draftActions.append(button('Use this draft', 'btn btn-primary btn-sm', () => {
                        if (analysis.draft.title) { field('title').value = analysis.draft.title; }
                        field('description').value = analysis.draft.description;
                        markAssisted(field('title'), field('description'));
                    }), el('small', 'muted-text', 'You can edit it afterwards. Your own text stays until you choose.'));
                    draft.append(draftActions);
                    panel.append(draft);

                    if (analysis.follow_up_questions.length) {
                        const block = section('Questions that would help your manager');
                        const inputs = analysis.follow_up_questions.map((question, index) => {
                            const label = el('label', 'lens-question');
                            const input = el('input');
                            input.type = 'text';
                            input.maxLength = 300;
                            input.placeholder = 'Your answer (optional)';
                            input.id = 'lens-question-' + index;
                            label.htmlFor = input.id;
                            label.append(el('span', null, question), input);
                            block.append(label);
                            return { question, input };
                        });
                        block.append(button('Add my answers to the description', 'btn btn-outline btn-sm', () => {
                            const answered = inputs.filter((item) => item.input.value.trim() !== '');
                            if (!answered.length) { return; }
                            field('description').value = field('description').value.trim() + '\n\nAdditional details:\n'
                                + answered.map((item) => '- ' + item.question + ' ' + item.input.value.trim()).join('\n');
                            markAssisted(field('description'));
                        }));
                        panel.append(block);
                    }

                    if (analysis.possible_causes.length) {
                        const block = section('Possible causes (hypotheses, not confirmed)', 'lens-hypotheses');
                        const ul = el('ul');
                        analysis.possible_causes.forEach((cause) => ul.append(el('li', null, cause)));
                        block.append(ul, el('p', 'ai-note', 'Only a technician can confirm the cause. These are not added to your report.'));
                        panel.append(block);
                    }

                    if (analysis.uncertainties.length) {
                        const block = section('What the AI cannot tell');
                        const ul = el('ul', 'tips-list');
                        analysis.uncertainties.forEach((item) => ul.append(el('li', null, item)));
                        block.append(ul);
                        panel.append(block);
                    }

                    grid.append(panel);

                    result.replaceChildren(grid);
                    result.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                analyse.addEventListener('click', async () => {
                    const residence = field('residence_id').value;
                    const description = field('description').value.trim();

                    if (!residence || (description.length < 10 && !file.files.length)) {
                        result.replaceChildren(el('div', 'flash flash-error', 'Choose a residence, then describe the problem (at least 10 characters) or add a photo.'));
                        return;
                    }

                    const body = new FormData();
                    body.append('residence_id', residence);
                    body.append('title', field('title').value.trim());
                    body.append('description', description);
                    if (file.files.length) { body.append('photo', file.files[0]); }

                    const original = Array.from(analyse.childNodes);
                    analyse.disabled = true;
                    analyse.textContent = 'Analysing...';
                    drop.classList.add('is-scanning');
                    const loading = el('div', 'lens-loading');
                    loading.append(el('span', 'lens-spinner'), el('span', null, file.files.length ? 'Gemini is looking at your photo and reading your description. This usually takes 10 to 40 seconds...' : 'Gemini is reading your description. This usually takes 10 to 30 seconds...'));
                    result.replaceChildren(loading);

                    try {
                        const response = await fetch(lens.dataset.url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body });
                        const data = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                            throw new Error(firstError || (response.status === 429 ? 'Too many analyses, please wait a minute.' : 'The analysis request failed. Please try again.'));
                        }

                        render(data);
                    } catch (error) {
                        result.replaceChildren(el('div', 'flash flash-error', error.message));
                    } finally {
                        analyse.disabled = false;
                        analyse.replaceChildren(...original);
                        drop.classList.remove('is-scanning');
                    }
                });
            });
        </script>
    @endpush
@endonce
