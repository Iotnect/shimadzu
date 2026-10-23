import '../css/login.css';

const password = document.querySelector('#password');
const toggle = document.querySelector('#password-toggle');

toggle.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    toggle.setAttribute('aria-pressed', String(show));
});

document.querySelector('#login-form').addEventListener('submit', () => {
    const button = document.querySelector('#login-submit');
    button.disabled = true;
    button.querySelector('span').textContent = 'Signing in…';
});
