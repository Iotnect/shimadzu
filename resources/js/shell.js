import '../css/dashboard-theme.css';

const toggle = document.querySelector('#dark-mode-toggle');
const status = document.querySelector('#theme-status');

function updateToggle() {
    const dark = document.documentElement.dataset.theme === 'dark';
    if (toggle) toggle.setAttribute('aria-checked', String(dark));
    if (status) status.textContent = dark ? 'On' : 'Off';
}

updateToggle();
toggle?.addEventListener('click', () => {
    const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme;
    try { localStorage.setItem('shimadzu-theme', theme); } catch { /* Keep the theme usable when storage is unavailable. */ }
    updateToggle();
});
window.addEventListener('storage', (event) => {
    if (event.key === 'shimadzu-theme' || event.key === null) {
        document.documentElement.dataset.theme = event.newValue === 'dark' ? 'dark' : 'light';
        updateToggle();
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
