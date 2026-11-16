import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Moon, Sun } from 'lucide-react';
import { Switch } from './ui/switch';

function AppearanceSettings() {
    const [dark, setDark] = useState(document.documentElement.dataset.theme === 'dark');
    function changeTheme(checked) {
        setDark(checked);
        document.documentElement.dataset.theme = checked ? 'dark' : 'light';
        try { localStorage.setItem('shimadzu-theme', checked ? 'dark' : 'light'); } catch { /* Theme remains usable without storage. */ }
    }
    useEffect(() => {
        function syncTheme(event) {
            if (event.key === 'shimadzu-theme' || event.key === null) {
                const checked = event.key !== null && event.newValue === 'dark';
                document.documentElement.dataset.theme = checked ? 'dark' : 'light';
                setDark(checked);
            }
        }
        window.addEventListener('storage', syncTheme);
        return () => window.removeEventListener('storage', syncTheme);
    }, []);
    const ThemeIcon = dark ? Moon : Sun;

    return (
        <div className="setting-row">
            <span className="settings-section-icon" aria-hidden="true"><ThemeIcon className="size-5" /></span>
            <div className="setting-copy">
                <label htmlFor="dark-mode-toggle" id="dark-mode-label">Dark mode</label>
                <p id="dark-mode-description">Change the theme for the dashboard and settings in this browser.</p>
            </div>
            <div className="flex shrink-0 items-center gap-3">
                <span id="theme-status" aria-hidden="true">{dark ? 'On' : 'Off'}</span>
                <Switch id="dark-mode-toggle" checked={dark} onCheckedChange={changeTheme} aria-labelledby="dark-mode-label" aria-describedby="dark-mode-description" />
            </div>
        </div>
    );
}

export function mountAppearanceSettings() {
    const element = document.querySelector('#appearance-settings');
    if (element) {
        createRoot(element).render(<AppearanceSettings />);
    }
}
