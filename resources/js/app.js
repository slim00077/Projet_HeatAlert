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
