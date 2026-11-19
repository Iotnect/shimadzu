@php
    $stationPage = $stationPage ?? null;
    $sensorPage = $sensorPage ?? null;
    $sensorLabel = $dashboard['sensorLabels'][$dashboard['sensor']] ?? $dashboard['sensor'];
    $overviewTitle = $sensorLabel.' cycle timeline';
    $pageTitle = $sensorPage !== null ? $sensorLabel.' worklog' : ($stationPage === null ? $overviewTitle : $overviewTitle.' · '.$stationPage);
@endphp
<x-app-layout :title="$pageTitle">

                <section id="overview" class="page-heading">
                    <div><h1>{{ $pageTitle }}</h1><p>Running, idle and performance by station.</p></div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if($sensorPage === null && $stationPage === null)
                            <div id="sensor-options"></div>
                        @endif
                        <button class="button button-primary" id="export-report"><x-icon name="download" />Export report</button>
                    </div>
                </section>
                @if($dashboard['source']['isDemo'] ?? false)<p>Demo workspace</p>@endif
                <div class="powerbi-report">

                <form id="worklog-filters" class="filter-bar" aria-label="Dashboard filters"></form>

                <div class="error-banner" id="error-banner" role="alert" hidden></div>
                <section class="kpi-grid" aria-label="Key production metrics" id="kpi-grid"></section>

                <div class="dashboard-bento">
                <section id="timeline" class="panel timeline-panel">
                    <div class="panel-heading"><h2 class="sr-only">Station · Working Time</h2><span class="sr-only" id="station-count"></span><label class="timeline-day" id="timeline-day-control" hidden>Timeline day <select id="timeline-date" aria-label="Timeline day"></select></label><div class="legend"><strong>Status</strong><span><i class="dot running"></i>Running</span><span><i class="dot idle"></i>Idle</span></div></div>
                    <div class="timeline-stats" id="timeline-stats"></div>
                    <div class="timeline-scroll"><div class="timeline-chart" id="timeline-chart" aria-label="Daily running and idle intervals by station"></div></div>
                    <div class="timeline-footer"><span><x-icon name="help" />Select an interval to explore cycle details.</span><button class="text-button" data-dialog="method">How is this calculated? <span>↗</span></button></div>
                </section>

                <div class="analysis-grid">
                    <section class="panel status-count-panel" aria-label="Count of StartTimeOnly by Status"><dl><div><dt><span class="detail-scope" data-detail-scope></span>Running</dt><dd id="running-count"></dd></div><div><dt>Idle</dt><dd id="idle-count"></dd></div></dl></section>
                    <section class="panel place-panel" id="place-slicer" aria-label="Place"></section>
                    <section class="panel duration-panel"><div class="panel-heading"><div><h2>Sum of DurationMin by Status</h2><p>Running and Idle for the selected WorkDate.</p></div></div><div id="duration-chart"></div></section>
                    <section class="panel standard-panel"><dl class="standard-metrics"><div><dt><span class="detail-scope" data-detail-scope></span>St Std Time (Min)</dt><dd id="standard-time"></dd></div><div><dt>Total Std Time (Min)</dt><dd id="total-standard-time"></dd></div></dl></section>
                    <section class="panel distribution-panel"><div class="panel-heading"><div><h2>Sum of DurationMin by Status</h2><p>Share of Running and Idle time.</p></div></div><div class="distribution-body" id="distribution"></div><div class="distribution-foot"><x-icon name="clock" /><span>Duration (min) · Running / (Running + Idle)</span></div></section>
                    <section id="stations" class="panel station-panel">
                        <div class="panel-heading">
                            <div><h2>Station performance</h2><p>A closer look at each station.</p></div>
                            @if($sensorPage !== null)
                                <button class="text-button" id="compare-stations">Compare all <x-icon name="arrow" /></button>
                            @elseif($stationPage !== null)
                                <a class="text-button" href="{{ route('sensors.show', $dashboard['sensor']) }}#stations">View all stations <x-icon name="arrow" /></a>
                            @else
                                <a class="text-button" id="sensor-worklog-link" href="{{ route('sensors.show', $dashboard['sensor']) }}" data-route="{{ route('sensors.show', ['sensor' => '__sensor__'], absolute: false) }}">Open line worklog <x-icon name="arrow" /></a>
                            @endif
                        </div>
                        <div class="table-scroll"><table class="station-table"><thead><tr><th>Station</th><th>Efficiency</th><th>Running</th><th>Idle</th><th>Cycles</th><th></th></tr></thead><tbody id="station-rows"></tbody></table></div>
                        <div class="station-foot"><i class="dot running"></i>Efficiency = running time / available station time <button class="text-button" data-dialog="method">View metrics <span>↗</span></button></div>
                    </section>
                </div>
                </div>

                <section id="worklog" class="panel worklog-panel"><div class="panel-heading"><div><div class="section-heading"><h2>Production work log</h2><span class="subtle-badge" id="job-count"></span></div><p>The records behind your daily performance.</p></div><label class="search-field"><x-icon name="search" /><input id="job-search" type="search" placeholder="Search jobs, products, operators…" aria-label="Search work log"></label></div><div class="table-scroll"><table class="worklog-table"><thead><tr><th>Job reference</th><th>Station</th><th>Product / operator</th><th>Start → End</th><th>Cycle time</th><th>Recorded qty</th><th></th></tr></thead><tbody id="job-rows"></tbody></table></div><div class="pagination"><span id="pagination-label"></span><div><button class="button button-quiet" id="jobs-prev">Previous</button><span id="page-number"></span><button class="button button-quiet" id="jobs-next">Next</button></div></div></section>

                </div>
                <footer class="page-footer"><span>SHIMADZU · <span id="sensor-label">{{ $sensorLabel }}</span> production</span><button class="text-button" data-dialog="source"><x-icon name="database" />Source & data coverage</button></footer>

    <x-slot:scripts>

    <dialog id="detail-dialog" class="detail-dialog"><div class="dialog-header"><div><span class="eyebrow" id="dialog-eyebrow">PRODUCTION INTELLIGENCE</span><h2 id="dialog-title"></h2></div><button class="icon-button" id="close-dialog" aria-label="Close dialog"><x-icon name="close" /></button></div><div id="dialog-content"></div></dialog>
    <div class="toast" id="toast" role="status" hidden></div>
    <div id="load-status" class="sr-only" role="status" aria-live="polite"></div>
    <script>
        window.detSensorPage = {{ Illuminate\Support\Js::from($sensorPage) }};
        window.detStationPage = {{ Illuminate\Support\Js::from($stationPage) }};
        window.detDashboard = {{ Illuminate\Support\Js::from($dashboard) }};
        window.detDataUrl = {{ Illuminate\Support\Js::from($sensorPage !== null ? route('sensors.data', $sensorPage, absolute: false) : ($stationPage !== null ? route('stations.data', $stationPage, absolute: false) : route('dashboard.data', absolute: false))) }};
    </script>
    @vite('resources/js/app.js')
    </x-slot:scripts>
</x-app-layout>
