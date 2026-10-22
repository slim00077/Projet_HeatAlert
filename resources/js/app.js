import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('[data-nav-header]');
    const toggle = document.querySelector('[data-nav-toggle]');

    if (!header || !toggle) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isOpen = header.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-assistant-form]');
    const input = document.querySelector('[data-assistant-input]');
    const messages = document.querySelector('[data-assistant-messages]');
    const error = document.querySelector('[data-assistant-error]');
    const submit = document.querySelector('[data-assistant-submit]');
    const submitLabel = document.querySelector('[data-submit-label]');

    if (!form || !input || !messages || !error || !submit) {
        return;
    }

    const scrollToLatest = () => {
        messages.scrollTop = messages.scrollHeight;
    };

    const appendMessage = (message) => {
        const article = document.createElement('article');
        article.className = `assistant-message${message.role === 'user' ? ' is-user' : ''}`;
        article.dataset.role = message.role;

        const avatar = document.createElement('span');
        avatar.className = 'assistant-message-avatar';
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = message.role === 'user' ? 'You' : '☀';

        const content = document.createElement('div');
        content.className = 'assistant-message-content';

        const label = document.createElement('span');
        label.className = 'assistant-message-label';
        label.textContent = message.role === 'user' ? 'You' : 'HeatAlert Assistant';

        const paragraph = document.createElement('p');
        paragraph.dir = 'auto';
        paragraph.textContent = message.content;

        content.append(label, paragraph);

        if (message.references?.length) {
            const references = document.createElement('div');
            references.className = 'assistant-references';

            const heading = document.createElement('span');
            heading.textContent = 'Related HeatAlert advice';
            references.append(heading);

            message.references.forEach((reference) => {
                const link = document.createElement('a');
                link.href = reference.url;

                const title = document.createElement('strong');
                title.textContent = reference.title;

                const category = document.createElement('small');
                category.textContent = reference.category;
                link.append(title, category);
                references.append(link);
            });

            content.append(references);
        }

        article.append(avatar, content);
        messages.append(article);
        scrollToLatest();
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = input.value.trim();

        if (!question || submit.disabled) {
            return;
        }

        error.hidden = true;
        submit.disabled = true;
        if (submitLabel) {
            submitLabel.textContent = 'Thinking...';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });
            const payload = await response.json();

            if (!response.ok) {
                const validationMessage = Object.values(payload.errors || {}).flat()[0];
                throw new Error(validationMessage || payload.message || 'The assistant could not answer right now. Please try again.');
            }

            document.querySelector('[data-assistant-welcome]')?.remove();
            payload.messages.forEach(appendMessage);
            form.action = payload.message_url;
            input.value = '';
            input.focus();
        } catch (requestError) {
            error.textContent = requestError.message || 'A connection error occurred. Please try again.';
            error.hidden = false;
        } finally {
            submit.disabled = false;
            if (submitLabel) {
                submitLabel.textContent = 'Send';
            }
        }
    });

    document.querySelectorAll('[data-assistant-prompt]').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.assistantPrompt;
            form.requestSubmit();
        });
    });

    scrollToLatest();
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-outage-form]').forEach((form) => {
        const fields = [...form.querySelectorAll('[data-min-length], [data-date-order], [data-custom-required]')];

        const validateField = (field, showValidState = false) => {
            const value = field.value.trim();
            let message = '';

            if (field.required && value.length === 0) {
                message = 'This field is required.';
            } else if (field.dataset.minLength && value.length > 0 && value.length < Number(field.dataset.minLength)) {
                message = `Enter at least ${field.dataset.minLength} characters.`;
            } else if (field.dataset.dateOrder === 'start') {
                const estimatedEnd = form.elements.namedItem('estimated_end_at');
                if (value.length > 0 && estimatedEnd.value && field.valueAsNumber >= estimatedEnd.valueAsNumber) {
                    message = 'The start date must be before the estimated end date.';
                }
            } else if (field.dataset.dateOrder === 'end') {
                const startedAt = form.elements.namedItem('started_at');
                if (value.length > 0 && startedAt.value && field.valueAsNumber <= startedAt.valueAsNumber) {
                    message = 'The estimated end date must be after the start date.';
                }
            }

            if (field.dataset.minLength) {
                const minimumLength = Number(field.dataset.minLength);
                const counter = field.parentElement.querySelector('[data-character-count]');
                if (counter) {
                    counter.textContent = `${field.value.length} characters`;
                    counter.classList.toggle('is-valid', showValidState && value.length >= minimumLength);
                }
            }

            const messageElement = document.getElementById(`${field.id}-error`);
            if (messageElement) {
                messageElement.textContent = message;
                messageElement.hidden = message.length === 0;
            }
            field.classList.toggle('is-invalid', message.length > 0);
            field.setAttribute('aria-invalid', message.length > 0 ? 'true' : 'false');

            return message.length === 0;
        };

        fields.forEach((field) => {
            const counter = field.parentElement.querySelector('[data-character-count]');
            if (counter) {
                counter.textContent = `${field.value.length} characters`;
            }

            field.addEventListener('input', () => {
                validateField(field, true);
                fields.filter((relatedField) => relatedField.dataset.dateOrder).forEach((dateField) => validateField(dateField, true));
            });
            field.addEventListener('blur', () => validateField(field, true));
        });

        const neighborhoodSelection = form.querySelector('[data-neighborhood-selection]');
        const validateNeighborhoods = () => {
            if (!neighborhoodSelection) {
                return true;
            }

            const selected = neighborhoodSelection.querySelector('input[type="checkbox"]:checked');
            const messageElement = document.getElementById('neighborhoods-error');
            const isValid = Boolean(selected);

            messageElement.textContent = isValid ? '' : 'Select at least one affected neighborhood.';
            messageElement.hidden = isValid;
            neighborhoodSelection.classList.toggle('is-invalid', !isValid);
            neighborhoodSelection.setAttribute('aria-invalid', isValid ? 'false' : 'true');

            return isValid;
        };

        neighborhoodSelection?.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.addEventListener('change', validateNeighborhoods);
        });

        form.addEventListener('submit', (event) => {
            const invalidField = fields.find((field) => !validateField(field, true));
            const neighborhoodsAreValid = validateNeighborhoods();

            if (invalidField || !neighborhoodsAreValid) {
                event.preventDefault();
                if (invalidField) {
                    invalidField.focus();
                } else {
                    neighborhoodSelection.querySelector('input[type="checkbox"]')?.focus();
                }
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-outage-impact-summary]').forEach((button) => {
        button.addEventListener('click', async () => {
            const section = button.closest('.outage-impact-summary');
            const status = section.querySelector('[data-outage-impact-summary-status]');
            const result = section.querySelector('[data-outage-impact-summary-result]');
            const source = section.querySelector('[data-outage-impact-summary-source]');
            const text = section.querySelector('[data-outage-impact-summary-text]');

            button.disabled = true;
            status.textContent = 'Generating summary...';
            result.hidden = true;

            try {
                const response = await fetch(button.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': button.dataset.csrf,
                    },
                });

                if (!response.ok) {
                    throw new Error(`Summary request failed with status ${response.status}.`);
                }

                const summary = await response.json();
                text.textContent = summary.text;
                source.textContent = summary.source === 'ai' ? 'AI-generated draft — review before acting.' : 'Standard data summary — AI unavailable.';
                result.hidden = false;
                status.textContent = '';
            } catch {
                status.textContent = 'Could not generate a summary right now. Please try again later.';
            } finally {
                button.disabled = false;
            }
        });
    });
});

const applyTheme = (choice) => {
    const dark = choice === 'dark' || (choice === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    document.querySelectorAll('[data-theme-option]').forEach((button) => {
        button.classList.toggle('is-selected', button.dataset.themeOption === choice);
    });
};

const storedTheme = () => {
    try {
        return localStorage.getItem('theme') || 'light';
    } catch (e) {
        return 'light';
    }
};

const saveTheme = (choice) => {
    try {
        localStorage.setItem('theme', choice);
    } catch (e) {
        // Storage can be unavailable (private mode); the theme then only lasts for this page.
    }

    applyTheme(choice);
};

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(storedTheme());

    document.querySelectorAll('[data-theme-option]').forEach((button) => {
        button.addEventListener('click', () => saveTheme(button.dataset.themeOption));
    });

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

            saveTheme(isDark ? 'light' : 'dark');
        });
    });
});
