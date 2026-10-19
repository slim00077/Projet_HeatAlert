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
