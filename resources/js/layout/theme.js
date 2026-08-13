export function initThemeToggle() {
    const themeCheckbox = document.getElementById('themeToggleCheckbox');
    if (!themeCheckbox) return;

    const currentTheme = document.documentElement.getAttribute('data-theme');
    themeCheckbox.checked = currentTheme !== 'light';

    themeCheckbox.addEventListener('change', (e) => {
        const newTheme = e.target.checked ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('app-theme', newTheme);
    });
}
