<x-app-layout title="Setting">
    <section class="page-heading">
        <div>
            <div class="eyebrow"><span class="eyebrow-line"></span> MANAGEMENT</div>
            <h1>Setting<span class="title-dot">.</span></h1>
            <p>Make this workspace feel right for you.</p>
        </div>
    </section>

    <section class="panel settings-panel" aria-labelledby="appearance-heading">
        <div class="panel-heading">
            <div>
                <h2 id="appearance-heading">Appearance</h2>
                <p>Choose how your workspace looks.</p>
            </div>
        </div>
        <div class="setting-row">
            <div>
                <h3 id="dark-mode-label">Dark mode</h3>
                <p id="dark-mode-description">Use a darker palette for the dashboard and settings. Your preference is saved in this browser.</p>
            </div>
            <div class="setting-control">
                <span id="theme-status" aria-live="polite">Off</span>
                <button type="button" id="dark-mode-toggle" class="theme-switch" role="switch" aria-checked="false" aria-labelledby="dark-mode-label" aria-describedby="dark-mode-description"><span class="theme-switch-thumb"></span></button>
            </div>
        </div>
    </section>
</x-app-layout>
