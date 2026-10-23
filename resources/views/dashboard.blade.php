<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e31b23">
    <meta name="description" content="DET production cycle times, station activity, and daily performance in one clear workspace.">
    <title>Shimadzu · Production overview</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
    </defs></svg>

    <a href="#main" class="skip-link">Skip to dashboard</a>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand company-brand" href="{{ route('dashboard') }}"><span class="company-logo-plate"><img src="{{ asset('images/shimadzu-logo.png') }}" alt="SHIMADZU" width="500" height="144"></span><small>PRODUCTION INTELLIGENCE</small></a>
            <div class="workspace"><span class="workspace-icon"><x-icon name="box" /></span><div><strong>DET Production</strong><small>Manufacturing workspace</small></div><x-icon name="arrow" /></div>
            <div class="nav-label">WORKSPACE</div>
            <nav class="primary-nav" aria-label="Main navigation">
                <a href="#overview" class="nav-link active" data-nav="overview"><x-icon name="grid" />Overview<span class="nav-active-dot"></span></a>
                <a href="#timeline" class="nav-link" data-nav="timeline"><x-icon name="clock" />Cycle timeline</a>
                <a href="#stations" class="nav-link" data-nav="stations"><x-icon name="chart" />Station analysis<span class="nav-count">4</span></a>
                <a href="#worklog" class="nav-link" data-nav="worklog"><x-icon name="list" />Work log</a>
            </nav>
            <div class="sidebar-bottom">
                <div class="source-card"><span class="source-card-icon"><x-icon name="database" /></span><strong>Your data, connected.</strong><p>One workspace for a clearer picture of production.</p><button class="source-link" data-dialog="source">View data source <span>↗</span></button></div>
                <button class="nav-link" data-dialog="method"><x-icon name="help" />Help & metric guide</button>
                <div class="profile"><div class="avatar">AD</div><div><strong>{{ auth()->user()->name }}</strong><small>Shimadzu · DET workspace</small></div><span class="profile-dot"></span></div>
                <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
                    @csrf
                    <button type="submit" class="logout-button">Sign out <x-icon name="arrow" /></button>
                </form>
            </div>
        </aside>
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <div class="main-shell">
            <header class="topbar"><div class="breadcrumb"><button class="icon-button mobile-menu" aria-label="Open navigation" aria-expanded="false" id="menu-toggle"><x-icon name="menu" /></button><span>Workspace</span><x-icon name="arrow" /><strong>Production overview</strong></div><div class="topbar-right"><span class="snapshot-badge"><i></i> Excel snapshot</span><span class="topbar-divider"></span><button class="icon-button" aria-label="Open metric guide" data-dialog="method"><x-icon name="help" /></button><div class="avatar small" title="{{ auth()->user()->name }}">AD</div></div></header>
            <main id="main">
                <section id="overview" class="page-heading"><div><div class="eyebrow"><span class="eyebrow-line"></span> PRODUCTION WORKSPACE</div><h1>Production overview<span class="title-dot">.</span></h1><p>Every station. Every cycle. One clear view.</p></div><button class="button button-primary" id="export-report"><x-icon name="download" />Export report</button></section>

                <section class="filter-bar" aria-label="Dashboard filters"><div class="filter-intro"><span class="filter-icon"><x-icon name="filter" /></span><strong>Daily overview</strong></div><div class="date-control"><button class="icon-button" id="previous-day" aria-label="Previous available day"><x-icon name="arrow" class="icon rotate-180" /></button><label class="date-field"><x-icon name="calendar" /><input type="date" id="date-filter" aria-label="Production date" value="{{ $dashboard['date'] }}" min="{{ $dashboard['source']['from'] }}" max="{{ $dashboard['source']['to'] }}"></label><button class="icon-button" id="next-day" aria-label="Next available day"><x-icon name="arrow" /></button></div><span class="filter-divider"></span><label class="station-select"><x-icon name="grid" /><select id="station-filter" aria-label="Filter station"><option value="all">All stations</option>@foreach($dashboard['stations'] as $station)<option value="{{ $station }}">Station {{ substr($station, 2) }}</option>@endforeach</select></label><button class="reset-button" id="reset-filters">Reset filters</button><span class="data-period" id="data-period"></span></section>

                <div class="error-banner" id="error-banner" role="alert" hidden></div>
                <section class="kpi-grid" aria-label="Key production metrics" id="kpi-grid"></section>

                <div class="dashboard-bento">
                <section id="timeline" class="panel timeline-panel">
                    <div class="panel-heading"><div><div class="section-heading"><span class="panel-symbol"><x-icon name="pulse" /></span><h2>Station activity</h2><span class="subtle-badge" id="station-count">4 stations</span></div><p>A minute-by-minute view of your production day.</p></div><div class="legend"><span><i class="dot running"></i>Running</span><span><i class="dot idle"></i>Idle</span><span><i class="dot break"></i>Scheduled break</span></div></div>
                    <div class="timeline-stats" id="timeline-stats"></div>
                    <div class="timeline-scroll"><div class="timeline-chart" id="timeline-chart" aria-label="Daily running and idle intervals by station"></div></div>
                    <div class="timeline-footer"><span><x-icon name="help" />Select an interval to explore cycle details.</span><button class="text-button" data-dialog="method">How is this calculated? <span>↗</span></button></div>
                </section>

                <div class="analysis-grid">
                    <section class="panel distribution-panel"><div class="panel-heading"><div><h2>Time distribution</h2><p>Where the recorded time goes.</p></div><span class="small-label">MINUTES</span></div><div class="distribution-body" id="distribution"></div><div class="distribution-foot"><x-icon name="clock" /><span>Based on recorded activity between jobs.</span></div></section>
                    <section id="stations" class="panel station-panel"><div class="panel-heading"><div><h2>Station performance</h2><p>A closer look at each station.</p></div><button class="text-button" id="compare-stations">Compare all <span>↗</span></button></div><div class="table-scroll"><table class="station-table"><thead><tr><th>Station</th><th>Efficiency</th><th>Running</th><th>Idle</th><th>Cycles</th><th></th></tr></thead><tbody id="station-rows"></tbody></table></div><div class="station-foot"><i class="dot running"></i>Efficiency = running time / available station time <button class="text-button" data-dialog="method">View metrics <span>↗</span></button></div></section>
                </div>
                </div>

                <section id="worklog" class="panel worklog-panel"><div class="panel-heading"><div><div class="section-heading"><h2>Production work log</h2><span class="subtle-badge" id="job-count"></span></div><p>The records behind your daily performance.</p></div><label class="search-field"><x-icon name="search" /><input id="job-search" type="search" placeholder="Search jobs, products, operators…" aria-label="Search work log"></label></div><div class="table-scroll"><table class="worklog-table"><thead><tr><th>Job reference</th><th>Station</th><th>Product / operator</th><th>Start → End</th><th>Cycle time</th><th>Recorded qty</th><th></th></tr></thead><tbody id="job-rows"></tbody></table></div><div class="pagination"><span id="pagination-label"></span><div><button class="button button-quiet" id="jobs-prev">Previous</button><span id="page-number"></span><button class="button button-quiet" id="jobs-next">Next</button></div></div></section>

                <footer class="page-footer"><span><span class="footer-brand company-footer">SHIMADZU</span> Built for a better production day.</span><button class="text-button" data-dialog="source"><x-icon name="database" />Source & data coverage</button></footer>
            </main>
        </div>
    </div>
    <dialog id="detail-dialog" class="detail-dialog"><div class="dialog-header"><div><span class="eyebrow" id="dialog-eyebrow">DET INTELLIGENCE</span><h2 id="dialog-title"></h2></div><button class="icon-button" id="close-dialog" aria-label="Close dialog"><x-icon name="close" /></button></div><div id="dialog-content"></div></dialog>
    <div class="toast" id="toast" role="status" hidden></div>
    <div id="load-status" class="sr-only" role="status" aria-live="polite"></div>
    <script>window.detDashboard = {{ Illuminate\Support\Js::from($dashboard) }}; window.detDataUrl = {{ Illuminate\Support\Js::from(route('dashboard.data', absolute: false)) }};</script>
</body>
</html>
