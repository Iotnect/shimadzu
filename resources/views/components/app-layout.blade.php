@props(['title' => 'Production overview'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e31b23">
    <meta name="description" content="DET production cycle times, station activity, and daily performance in one clear workspace.">
    <title>Shimadzu · {{ $title }}</title>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('shimadzu-theme') === 'dark' ? 'dark' : 'light'; } catch { document.documentElement.dataset.theme = 'light'; }</script>
    @vite(['resources/css/app.css', 'resources/js/shell.js'])
</head>
<body>
    <svg class="svg-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
        <symbol id="icon-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></symbol>
        <symbol id="icon-chart" viewBox="0 0 24 24"><path d="M4 3v17h17M8 15v-4m5 4V6m5 9v-6"/></symbol>
        <symbol id="icon-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
        <symbol id="icon-list" viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></symbol>
        <symbol id="icon-database" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 4 16 4 16 0V5M4 12c0 4 16 4 16 0"/></symbol>
        <symbol id="icon-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 .5c0 2-2.5 2-2.5 4M12 17h.01"/></symbol>
        <symbol id="icon-arrow" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></symbol>
        <symbol id="icon-download" viewBox="0 0 24 24"><path d="M12 3v12m-4-4 4 4 4-4M4 16v5h16v-5"/></symbol>
        <symbol id="icon-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18M7 15h2m4 0h2"/></symbol>
        <symbol id="icon-filter" viewBox="0 0 24 24"><path d="M4 7h16M7 12h10m-7 5h4"/><circle cx="9" cy="7" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"/></symbol>
        <symbol id="icon-pulse" viewBox="0 0 24 24"><path d="M2 12h5l3-8 4 16 3-8h5"/></symbol>
        <symbol id="icon-trend" viewBox="0 0 24 24"><path d="m3 17 7-7 4 4 7-9m-6 0h6v6"/></symbol>
        <symbol id="icon-box" viewBox="0 0 24 24"><path d="m12 3 9 5v9l-9 5-9-5V8l9-5ZM3 8l9 5 9-5m-9 5v9M7.5 5.5l9 5"/></symbol>
        <symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
        <symbol id="icon-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></symbol>
        <symbol id="icon-close" viewBox="0 0 24 24"><path d="m6 6 12 12M6 18 18 6"/></symbol>
        <symbol id="icon-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
        <symbol id="icon-spark" viewBox="0 0 24 24"><path d="m12 3 2.6 6.4L21 12l-6.4 2.6L12 21l-2.6-6.4L3 12l6.4-2.6L12 3Z"/></symbol>
        <symbol id="icon-refresh" viewBox="0 0 24 24"><path d="M20 7v5h-5M4 17v-5h5M5.5 7a7.5 7.5 0 0 1 12.8-1L20 9M4 15l1.7 3A7.5 7.5 0 0 0 18.5 17"/></symbol>
        <symbol id="icon-settings" viewBox="0 0 24 24"><path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="15" cy="17" r="3"/></symbol>
    </defs></svg>

    <a href="#main" class="skip-link">Skip to dashboard</a>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand company-brand" href="{{ route('dashboard') }}"><span class="company-logo-plate"><img src="{{ asset('images/shimadzu-logo.png') }}" alt="SHIMADZU" width="500" height="144"></span><small>PRODUCTION INTELLIGENCE</small></a>
            <div class="workspace"><span class="workspace-icon"><x-icon name="box" /></span><div><strong>DET Production</strong><small>Manufacturing workspace</small></div><x-icon name="arrow" /></div>
            <div class="nav-label">WORKSPACE</div>
            <nav class="primary-nav" aria-label="Main navigation">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="grid" />Overview @if(request()->routeIs('dashboard'))<span class="nav-active-dot"></span>@endif</a>
                @foreach(['St1', 'St2', 'St3', 'St4'] as $station)
                    <a href="{{ route('stations.show', $station) }}" class="nav-link {{ request()->routeIs('stations.show') && request()->route('station') === $station ? 'active' : '' }}" @if(request()->routeIs('stations.show') && request()->route('station') === $station) aria-current="page" @endif><x-icon name="chart" />Station {{ substr($station, 2) }}</a>
                @endforeach
            </nav>
            <div class="nav-label management-label">MANAGEMENT</div>
            <nav class="primary-nav" aria-label="Management navigation">
                <a href="{{ route('settings') }}" class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}" @if(request()->routeIs('settings')) aria-current="page" @endif><x-icon name="settings" />Setting</a>
            </nav>
            <div class="sidebar-bottom">
                <div class="profile"><div class="avatar">AD</div><div><strong>{{ auth()->user()->name }}</strong><small>Shimadzu · DET workspace</small></div><span class="profile-dot"></span></div>
                <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
                    @csrf
                    <button type="submit" class="logout-button">Sign out <x-icon name="arrow" /></button>
                </form>
            </div>
        </aside>
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <div class="main-shell">
            <header class="topbar"><div class="breadcrumb"><button class="icon-button mobile-menu" aria-label="Open navigation" aria-expanded="false" id="menu-toggle"><x-icon name="menu" /></button><span>{{ request()->routeIs('settings') ? 'Management' : 'Workspace' }}</span><x-icon name="arrow" /><strong>{{ $title }}</strong></div><div class="topbar-right">@if(request()->routeIs('dashboard', 'stations.show'))<button class="icon-button" aria-label="Open metric guide" data-dialog="method"><x-icon name="help" /></button>@endif</div></header>
            <main id="main">{{ $slot }}</main>
        </div>
    </div>
    {{ $scripts ?? '' }}
</body>
</html>
