import '../css/dashboard-theme.css';

import { mountAppearanceSettings } from './components/appearance-settings';

mountAppearanceSettings();
document.querySelector('#station-parameters-form')?.addEventListener('submit', event => {
    if (event.target.dataset.preview === 'true') {
        event.preventDefault();
        const status = document.querySelector('#station-preview-status');
        status.textContent = 'Preview complete. Your changes have not been saved or applied to the dashboard.';
        status.hidden = false;
        return;
    }
    const button = event.target.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Saving…';
});
window.addEventListener('storage', (event) => {
    if (event.key === 'shimadzu-theme' || event.key === null) {
        document.documentElement.dataset.theme = event.key !== null && event.newValue === 'dark' ? 'dark' : 'light';
    }
});

const sidebar = document.querySelector('#sidebar');
const menu = document.querySelector('#menu-toggle');
function closeSidebar() {
    sidebar.classList.remove('open');
    menu.setAttribute('aria-expanded', 'false');
}
menu.addEventListener('click', () => {
    menu.setAttribute('aria-expanded', String(sidebar.classList.toggle('open')));
});
document.querySelector('#sidebar-overlay').addEventListener('click', closeSidebar);
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeSidebar(); });
document.querySelectorAll('[data-nav]').forEach((link) => link.addEventListener('click', () => {
    if (window.detDashboard) {
        document.querySelectorAll('[data-nav]').forEach((item) => { item.classList.remove('active'); item.removeAttribute('aria-current'); });
        link.classList.add('active');
        link.setAttribute('aria-current', 'location');
    }
    closeSidebar();
}));
