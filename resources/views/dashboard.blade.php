<x-app-layout>

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

    <x-slot:scripts>

    <dialog id="detail-dialog" class="detail-dialog"><div class="dialog-header"><div><span class="eyebrow" id="dialog-eyebrow">DET INTELLIGENCE</span><h2 id="dialog-title"></h2></div><button class="icon-button" id="close-dialog" aria-label="Close dialog"><x-icon name="close" /></button></div><div id="dialog-content"></div></dialog>
    <div class="toast" id="toast" role="status" hidden></div>
    <div id="load-status" class="sr-only" role="status" aria-live="polite"></div>
    <script>window.detDashboard = {{ Illuminate\Support\Js::from($dashboard) }}; window.detDataUrl = {{ Illuminate\Support\Js::from(route('dashboard.data', absolute: false)) }};</script>
    @vite('resources/js/app.js')
    </x-slot:scripts>
</x-app-layout>
