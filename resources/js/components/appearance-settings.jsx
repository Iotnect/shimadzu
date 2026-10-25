import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Moon, Sun } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from './ui/card';
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
    return <Card className="gap-6 shadow-xs">
        <CardHeader><CardTitle><h2 id="appearance-heading">Appearance</h2></CardTitle><CardDescription>Choose how your workspace looks.</CardDescription></CardHeader>
        <CardContent>
            <div className="flex items-center justify-between gap-5 rounded-lg border bg-muted/30 p-5">
                <div className="flex items-start gap-3"><span className="rounded-md border bg-background p-2 text-muted-foreground">{dark ? <Moon className="size-5" /> : <Sun className="size-5" />}</span><div><label htmlFor="dark-mode-toggle" className="text-sm font-medium" id="dark-mode-label">Dark mode</label><p id="dark-mode-description" className="mt-1 max-w-md text-sm leading-relaxed text-muted-foreground">Use a darker palette for the dashboard and settings. Your preference is saved in this browser.</p></div></div>
                <div className="flex shrink-0 items-center gap-3"><span id="theme-status" className="text-xs text-muted-foreground" aria-live="polite">{dark ? 'On' : 'Off'}</span><Switch id="dark-mode-toggle" checked={dark} onCheckedChange={changeTheme} aria-labelledby="dark-mode-label" aria-describedby="dark-mode-description" /></div>
            </div>
        </CardContent>
    </Card>;
}

export function mountAppearanceSettings() {
    const element = document.querySelector('.settings-panel');
    if (element) {
        element.classList.remove('panel');
        createRoot(element).render(<AppearanceSettings />);
    }
}
