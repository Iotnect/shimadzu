import { printReport, reportHtml } from './report';
import { mountDashboardControls, renderSensorOptions, renderStationOptions } from './components/dashboard-controls';
import { renderProductionMetrics } from './components/production-metrics';

mountDashboardControls(window.detDashboard);

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];
const icon = (name, extra = '') => `<svg class="icon ${extra}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#icon-${name}"/></svg>`;
const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
const number = (value, digits = 0, maximumDigits = digits) => Number(value).toLocaleString('en-GB', { minimumFractionDigits: digits, maximumFractionDigits: maximumDigits });
const percent = (value) => value === null ? 'N/A' : `${number(value, 1)}%`;
const dayLabel = (date, options = {}) => new Date(`${date}T12:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', ...options });
const timeLabel = (date) => date.slice(11, 16);
const duration = (start, end) => (new Date(end) - new Date(start)) / 60000;
const state = { data: window.detDashboard, page: 1, search: '', controller: null, detailStation: null };
let defaultRange = !new URLSearchParams(window.location.search).has('from');
let toastTimer;

function notify(message) {
    clearTimeout(toastTimer);
    $('#toast').textContent = message;
    $('#toast').hidden = false;
    toastTimer = setTimeout(() => { $('#toast').hidden = true; }, 3500);
}

function renderMetrics() {
    renderProductionMetrics(state.data);
}

function renderTimeline() {
    const { summary, date } = state.data;
    $('#station-count').textContent = `${summary.rows.length} station${summary.rows.length === 1 ? '' : 's'}`;
    const stats = [
        ['box', 'Recorded qty (Excel)', `${number(summary.quantity)} units${summary.missingQuantity ? ` · ${number(summary.missingQuantity)} missing` : ''}`],
        ['grid', 'Working Hour', `${number(summary.available, 0, 4)} min · effective station settings`],
    ];
    $('#timeline-stats').innerHTML = stats.map(([symbol, label, value]) => `<span class="timeline-stat">${icon(symbol)}${label}<strong>${value}</strong></span>`).join('');

    const minuteOffset = (timestamp) => duration(`${date}T00:00:00`, timestamp);
    const starts = summary.segments.map((segment) => minuteOffset(segment.start));
    const ends = summary.segments.map((segment) => minuteOffset(segment.end));
    const startMinute = Math.max(0, Math.floor(Math.min(8 * 60, ...starts) / 60) * 60);
    const endMinute = Math.min(1440, Math.ceil(Math.max(17 * 60, ...ends) / 60) * 60);
    const span = endMinute - startMinute;
    const position = (minute) => (minute - startMinute) / span * 100;
    const hourCount = span / 60;
    const ticks = Array.from({ length: hourCount + 1 }, (_, index) => {
        const minute = startMinute + index * 60;
        const label = `${String(Math.floor(minute / 60) % 24).padStart(2, '0')}:${String(Math.round(minute % 60)).padStart(2, '0')}`;
        return `<span class="axis-tick" style="left:${index / hourCount * 100}%">${label}${minute >= 1440 ? ' +1d' : ''}</span>`;
    }).join('');
    const breaks = [[615, 630, 'Morning Break'], [735, 780, 'Lunch Break'], [915, 930, 'Tea Break']];
    const breakBands = breaks.map(([start, end, label]) => `<span class="break-band" style="left:${position(start)}%;width:${(end - start) / span * 100}%" title="${label}: ${Math.floor(start / 60)}:${String(start % 60).padStart(2, '0')}–${Math.floor(end / 60)}:${String(end % 60).padStart(2, '0')}"></span>`).join('');
    $('#timeline-chart').innerHTML = `<div class="timeline-axis"><div class="timeline-axis-label">STATION</div><div class="axis-track">${ticks}</div></div>${summary.rows.map((row) => {
        const intervals = summary.segments.map((segment, index) => {
            if (segment.station !== row.station) return '';
            const label = escape(`${segment.station} · ${segment.status} · ${timeLabel(segment.start)}–${timeLabel(segment.end)} · ${number(segment.minutes, 1)} min`);
            const end = Math.min(endMinute, minuteOffset(segment.end));
            return `<button class="interval ${segment.status.toLowerCase()}" style="left:${position(minuteOffset(segment.start))}%;width:${Math.max(.12, (end - minuteOffset(segment.start)) / span * 100)}%" title="${label}" aria-label="${label}" data-segment="${index}"></button>`;
        }).join('');
        return `<div class="timeline-row"><div class="station-label"><button type="button" class="station-label-text timeline-station" data-detail-station="${escape(row.station)}" aria-pressed="${state.detailStation === row.station}" aria-controls="running-count idle-count standard-time total-standard-time"><span><strong>${escape(row.station)}</strong><small>Total Qty: ${row.cycles}</small></span>${icon('arrow')}</button></div><div class="track" style="background-image:linear-gradient(to right,var(--line) 1px,transparent 1px);background-size:${100 / hourCount}% 100%">${breakBands}${intervals || '<span class="timeline-empty">No recorded activity</span>'}</div></div>`;
    }).join('')}<div class="break-labels">${breaks.map(([start, end, label]) => `<span class="break-label" style="left:${position((start + end) / 2)}%">${label}</span>`).join('')}</div>`;
}

function renderDistribution() {
    const { running, idle, availability } = state.data.summary;
    const total = running + idle;
    const runningShare = availability ?? 0;
    $('#distribution').innerHTML = `<div class="donut" role="img" aria-label="${total ? `${percent(availability)} of recorded time is running` : 'No recorded activity'}" style="background:${total ? `conic-gradient(var(--chart-running) 0 ${runningShare}%, var(--chart-idle) ${runningShare}% 100%)` : '#ededed'}"><div class="donut-center"><strong>${number(total, 1)}</strong><small>recorded min</small></div></div><div class="distribution-key"><div class="distribution-key-item"><span><i class="dot running"></i>Running</span><strong>${number(running, 1)} <small>min · ${number(runningShare, 1)}%</small></strong></div><div class="distribution-key-item"><span><i class="dot idle"></i>Idle</span><strong>${number(idle, 1)} <small>min · ${number(total ? 100 - runningShare : 0, 1)}%</small></strong></div></div>`;
}

function renderStations() {
    const canSelect = window.detSensorPage && !window.detStationPage;
    $('#station-rows').innerHTML = state.data.summary.rows.map((row) => `<tr><td><span class="station-name">${escape(row.station)}</span></td><td><div class="efficiency-cell"><span>${percent(row.efficiency)}</span><div class="efficiency-track"><i style="width:${Math.min(100, row.efficiency ?? 0)}%"></i></div></div></td><td>${number(row.running)} <small>min</small></td><td>${number(row.idle)} <small>min</small></td><td>${row.cycles}</td><td>${canSelect ? `<button class="icon-button" data-station="${escape(row.station)}" aria-label="Explore ${escape(row.station)}">${icon('arrow')}</button>` : ''}</td></tr>`).join('');
}

function renderStationDetails() {
    const { summary } = state.data;
    const selectedRow = summary.rows.find(row => row.station === state.detailStation);
    const rows = selectedRow ? [selectedRow] : summary.rows;
    const totals = selectedRow ?? summary;
    const scope = selectedRow?.station ?? (state.data.station === 'all' ? 'All stations' : state.data.station);
    $$('[data-detail-scope]').forEach(label => { label.textContent = scope; });
    $$('[data-detail-station]').forEach(button => {
        const selected = button.dataset.detailStation === state.detailStation;
        button.setAttribute('aria-pressed', String(selected));
        button.title = selected ? 'Show totals for all displayed stations' : `Show ${button.dataset.detailStation} counts and standard times`;
    });
    $('#running-count').textContent = totals.runningCount;
    $('#idle-count').textContent = totals.idleCount;
    const standard = rows.length === 1 ? rows[0].standard : null;
    let standardLabel = 'Not configured';
    if (rows.some(row => row.standardVaries)) {
        standardLabel = 'Varies over period';
    } else if (standard !== null) {
        standardLabel = `${number(standard, 4)} min`;
    } else if (rows.some(row => row.standard !== null)) {
        standardLabel = 'Varies by section';
    }
    $('#standard-time').textContent = standardLabel;
    $('#total-standard-time').textContent = totals.expected === null ? 'Not configured' : `${number(totals.expected, 1)} min`;
}

function renderReportDetails() {
    renderStationDetails();
    const { summary } = state.data;
    const maximum = Math.max(summary.running, summary.idle, 1);
    $('#duration-chart').innerHTML = `<div class="duration-columns" role="img" aria-label="Running ${number(summary.running, 1)} min, Idle ${number(summary.idle, 1)} min">${[['Running', summary.running], ['Idle', summary.idle]].map(([status, minutes]) => `<div class="duration-column"><strong>${number(minutes, 1)}</strong><div class="duration-bar ${status.toLowerCase()}" style="height:${minutes / maximum * 120}px"></div><span>${status}</span></div>`).join('')}</div><p class="duration-date">${periodLabel()} · Duration (min)</p>`;
}

function matchingJobs() {
    return state.data.jobs.filter((job) => [job.id, job.serial, job.reference, job.product, job.worker, job.station].join(' ').toLowerCase().includes(state.search));
}

function renderJobs() {
    const jobs = matchingJobs();
    const pageCount = Math.max(1, Math.ceil(jobs.length / 6));
    state.page = Math.min(state.page, pageCount);
    const offset = (state.page - 1) * 6;
    $('#job-count').textContent = `${number(state.data.jobs.length)} ${state.data.jobs.length === 1 ? 'record' : 'records'}`;
    $('#job-rows').innerHTML = jobs.slice(offset, offset + 6).map((job) => `<tr><td><span class="job-ref">#${escape(job.id)}</span><span class="worker-name">${escape(job.serial ?? job.reference ?? 'Not recorded')}</span></td><td><span class="station-pill">${escape(job.station)}</span></td><td><span class="product-name">${escape(job.product ?? 'Not recorded')}</span><span class="worker-name">${escape(job.worker ?? 'Not recorded')}</span></td><td><span class="worker-name">${dayLabel(job.start.slice(0, 10))}</span>${timeLabel(job.start)} <span style="color:var(--muted)">→</span> ${job.end.slice(0, 10) !== job.start.slice(0, 10) ? `${dayLabel(job.end.slice(0, 10))} ` : ''}${timeLabel(job.end)}</td><td>${duration(job.start, job.end) < 0 ? 'Invalid timestamps' : `${number(duration(job.start, job.end), 1)} <small>min</small>`}</td><td>${job.quantity === null ? '<span title="Quantity missing in source">Not recorded</span>' : number(job.quantity)}</td><td><button class="icon-button" data-job="${escape(job.id)}" aria-label="View job ${escape(job.id)}">${icon('arrow')}</button></td></tr>`).join('') || `<tr><td colspan="7" class="empty-state">${state.search ? 'No jobs match your search. Try a serial number, product, or operator.' : 'No activity was recorded for this period and section. Choose another date range.'}</td></tr>`;
    $('#pagination-label').textContent = jobs.length ? `Showing ${offset + 1}–${Math.min(offset + 6, jobs.length)} of ${jobs.length} records` : 'No matching records';
    $('#page-number').textContent = `${state.page} / ${pageCount}`;
    $('#jobs-prev').disabled = state.page === 1;
    $('#jobs-next').disabled = state.page === pageCount;
}

function periodLabel() {
    return state.data.from === state.data.to ? dayLabel(state.data.from) : `${dayLabel(state.data.from)} – ${dayLabel(state.data.to)}`;
}

function render() {
    $('#from-filter').value = state.data.from;
    $('#to-filter').value = state.data.to;
    renderSensorOptions(state.data, selectSensor);
    renderStationOptions(state.data);
    if ($('#station-filter')) $('#station-filter').value = state.data.station;
    $('#data-period').textContent = `Coverage ${dayLabel(state.data.source.from)} – ${dayLabel(state.data.source.to)}`;
    $('#sensor-label').textContent = state.data.sensorLabels?.[state.data.sensor] ?? state.data.sensor;
    const worklogLink = $('#sensor-worklog-link');
    if (worklogLink) {
        const title = `${state.data.sensorLabels?.[state.data.sensor] ?? state.data.sensor} cycle timeline`;
        $('#overview h1').textContent = title;
        $('.breadcrumb strong').textContent = title;
        document.title = `Shimadzu · ${title}`;
        const url = new URL(worklogLink.dataset.route.replace('__sensor__', state.data.sensor), location.href);
        url.search = new URLSearchParams({ from: state.data.from, to: state.data.to, date: state.data.date }).toString();
        worklogLink.href = url.href;
    }
    $('#timeline-day-control').hidden = state.data.from === state.data.to;
    const timelineDates = state.data.periodDates.length ? state.data.periodDates : [state.data.date];
    $('#timeline-date').innerHTML = timelineDates.map(date => `<option value="${escape(date)}">${dayLabel(date)}</option>`).join('');
    $('#timeline-date').value = state.data.date;
    $('#timeline-date').disabled = timelineDates.length < 2;
    renderMetrics();
    renderTimeline();
    renderDistribution();
    renderStations();
    renderReportDetails();
    renderJobs();
}

async function loadData(filters = {}, { useDefault = defaultRange } = {}) {
    state.controller?.abort();
    const controller = new AbortController();
    state.controller = controller;
    $('#main').setAttribute('aria-busy', 'true');
    $('#export-report').disabled = true;
    $('#error-banner').hidden = true;
    $('#load-status').textContent = 'Loading production data';
    try {
        const query = new URLSearchParams({
            sensor: state.data.sensor,
            station: $('#station-filter')?.value ?? state.data.station,
            ...filters,
        });
        if (!useDefault) {
            if (!query.has('from')) query.set('from', state.data.from);
            if (!query.has('to')) query.set('to', state.data.to);
        }
        const response = await fetch(`${window.detDataUrl}?${query}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Unable to load these filters. Check the date range and try again.');
        state.data = await response.json();
        state.detailStation = null;
        defaultRange = useDefault;
        state.page = 1;
        render();
        const url = new URL(window.location.href);
        url.search = query.toString();
        history.replaceState(null, '', url);
        $('#load-status').textContent = `Loaded ${state.data.jobs.length} jobs for ${state.data.sensorLabels?.[state.data.sensor] ?? state.data.sensor}, ${periodLabel()}`;
    } catch (error) {
        if (error.name === 'AbortError') return;
        $('#error-banner').textContent = error.message;
        $('#error-banner').hidden = false;
        $('#from-filter').value = state.data.from;
        $('#to-filter').value = state.data.to;
        if ($('#station-filter')) $('#station-filter').value = state.data.station;
        $('#timeline-date').value = state.data.date;
        $('#load-status').textContent = 'Data could not be loaded. Previous results are still displayed.';
    } finally {
        if (state.controller === controller) {
            $('#main').removeAttribute('aria-busy');
            $('#export-report').disabled = false;
        }
    }
}

function showDialog(title, content, eyebrow = 'PRODUCTION INTELLIGENCE') {
    $('#dialog-title').textContent = title;
    $('#dialog-eyebrow').textContent = eyebrow;
    $('#dialog-content').innerHTML = content;
    $('#detail-dialog').showModal();
}

function details(fields) {
    return `<dl class="detail-grid">${fields.map(([label, value]) => `<div><dt>${escape(label)}</dt><dd>${escape(value)}</dd></div>`).join('')}</dl>`;
}

function showJob(id) {
    const job = state.data.jobs.find((job) => String(job.id) === String(id));
    if (!job) return;
    const elapsed = duration(job.start, job.end);
    showDialog(`Job #${job.id}`, `<p class="dialog-copy">${escape(job.product ?? 'Product not recorded')}</p>${details([
        ['Sensor', state.data.sensorLabels?.[state.data.sensor] ?? state.data.sensor], ['Section', job.station], ['SN', job.serial ?? 'Not recorded'], ['WorkDate', dayLabel(job.start.slice(0, 10))],
        ['Start', `${timeLabel(job.start)}:${job.start.slice(17, 19)}`], ['End', `${dayLabel(job.end.slice(0, 10))} · ${job.end.slice(11, 19)}`],
        ['Cycle duration', elapsed < 0 ? 'End precedes start' : `${number(elapsed, 2)} minutes`], ['Recorded quantity', job.quantity === null ? 'Not recorded in source' : number(job.quantity)],
        ['Operator', job.worker ?? 'Not recorded'], ['Instruction reference', job.reference || 'Not recorded'],
        ['UnitTime', job.unitMinutes == null ? 'Not recorded' : `${number(job.unitMinutes, 2)} minutes`],
        ['WorkTime', job.workMinutes == null ? 'Not recorded' : `${number(job.workMinutes, 2)} minutes`],
        ['Skill', job.skill ?? 'Not recorded'], ['Job code', job.jobCode ?? 'Not recorded'],
        ['Source', `${state.data.source.worksheet} · row ${job.sourceRow ?? 'unavailable'}`],
    ])}<div class="dialog-note">A logged cycle is a job record. Recorded quantity is the Excel qty value; a zero quantity is preserved as zero.</div>`, 'PRODUCTION WORK LOG');
}

function showMethod() {
    showDialog('Production metric guide', `<div class="metric-guide">
        <div><h3>Efficiency % by Station · Working Hour (Min)</h3><p>Total Running / Working Hour × 100. Working Hour sums each selected station's daily working time for the production dates recorded by the sensor. Each date uses the revision effective on that date. The default is 435 minutes per station per day. Overtime can exceed 100%.</p></div>
        <div><h3>Availability % · Total Running (Min) · Total Idle (Min)</h3><p>Running / (Running + Idle) × 100. Idle covers same-day gaps shorter than 12 hours between jobs at the same station. Time before the first job and after the last job is excluded. Overlapping jobs count once in Total Running.</p></div>
        <div><h3>Performance % · Total Std Time (Min)</h3><p>Total Std Time / Total Running × 100. Each worklog cycle contributes the station standard effective on its start date. A date range can include several revisions. A missing standard for any included cycle leaves total performance unavailable.</p></div>
        <div><h3>St Std Time (Min)</h3><p>Configure standard cycle time for each sensor and station in Station Settings. A new revision applies from its effective work date. The DET sensor has default cycle times for St1 through St4. A period spanning different standards is labelled as varying over the period.</p></div>
        <div><h3>Total Qty · Recorded qty (Excel)</h3><p>Total Qty counts worklog records. Recorded qty sums the imported Excel quantities, including genuine zero values. Where an export row shifts qty into an earlier column, the import aligns it with the quantity field. Single-day comparisons use the previous available production day, with rates compared in percentage points (pp).</p></div>
        <div><h3>Date range · Sensor · Section</h3><p>From and To include both dates and filter by each job's start date. KPIs, distribution, station totals and the work log cover the full selected period. The timeline displays the selected timeline day. Section filtering is available inside each sensor page. Records with an end time before their start remain in the work log and are excluded from running and idle time.</p></div>
        <div><h3>Scheduled breaks</h3><p>Morning Break 10:15–10:30 · Lunch Break 12:15–13:00 · Tea Break 15:15–15:30. Shading is a visual reference and does not subtract time from recorded intervals.</p></div>
    </div>`, 'HELP & METRIC GUIDE');
}

function showSource() {
    const { source } = state.data;
    showDialog('Your production data', `<p class="dialog-copy">${source.isDemo ? 'This demo uses built-in synthetic records to demonstrate the dashboard. They are not actual production records.' : 'This workspace uses an imported Excel snapshot. The original workbook and PBIX have not been modified.'}</p>${details([
        ['Workbook', source.name], ['Worksheet', source.worksheet],
        ['Coverage begins', dayLabel(source.from)], ['Coverage ends', dayLabel(source.to)],
        ['Imported job records', number(source.records)], ['Stations', state.data.stations.join(', ')],
        ['Imported on', dayLabel(source.importedAt.slice(0, 10))], ['Standard time source', source.standardSource ?? 'Not configured for this sensor'],
    ])}<div class="dialog-note">${source.isDemo ? 'Sample data is included with the demo. No database or private file hosting is required.' : `This is historical data, not a live feed. The workbook snapshot ends on ${dayLabel(source.to)}. New workbook changes require a new import. Production dates and times are displayed as recorded in the source.`}</div>`, 'DATA SOURCE & COVERAGE');
}

function exportReport() {
    const jobs = matchingJobs();
    if (!jobs.length) return notify('There are no matching job records to export.');
    printReport(reportHtml(state.data, jobs, state.search));
    notify('Choose Save as PDF in the print dialog to save your report.');
}

function selectedRange() {
    const from = $('#from-filter');
    const to = $('#to-filter');
    if (!from.reportValidity() || !to.reportValidity()) return null;
    if (from.value > to.value) {
        notify('The To date must be on or after the From date.');
        return null;
    }
    return { from: from.value, to: to.value };
}

$('#worklog-filters').addEventListener('submit', (event) => {
    event.preventDefault();
    const range = selectedRange();
    if (range) loadData(range, { useDefault: false });
});
function selectSensor(sensor) {
    const range = selectedRange();
    if (!range) return;
    const useDefault = defaultRange && range.from === state.data.from && range.to === state.data.to;
    loadData({ sensor, station: 'all', ...(useDefault ? {} : range) }, { useDefault });
}
$('#timeline-date').addEventListener('change', (event) => loadData({ date: event.target.value }, { useDefault: false }));
$('#reset-filters').addEventListener('click', () => {
    state.search = '';
    $('#job-search').value = '';
    loadData({ station: window.detStationPage ?? 'all' }, { useDefault: true });
});
$('#compare-stations')?.addEventListener('click', () => loadData({ station: 'all', date: state.data.date }));
$('#job-search').addEventListener('input', (event) => { state.search = event.target.value.trim().toLowerCase(); state.page = 1; renderJobs(); });
$('#jobs-prev').addEventListener('click', () => { state.page--; renderJobs(); });
$('#jobs-next').addEventListener('click', () => { state.page++; renderJobs(); });
$('#export-report').addEventListener('click', exportReport);
$('#close-dialog').addEventListener('click', () => $('#detail-dialog').close());
$('#detail-dialog').addEventListener('click', (event) => { if (event.target === $('#detail-dialog') && event.offsetX >= 0 && event.offsetY >= 0 && (event.offsetX > event.target.clientWidth || event.offsetY > event.target.clientHeight)) event.target.close(); });

document.addEventListener('click', (event) => {
    const detailStationButton = event.target.closest('[data-detail-station]');
    if (detailStationButton) {
        const station = detailStationButton.dataset.detailStation;
        state.detailStation = state.detailStation === station ? null : station;
        renderStationDetails();
        $('#load-status').textContent = `Counts and standard times updated for ${state.detailStation ?? 'all displayed stations'}`;
    }
    const dialogButton = event.target.closest('[data-dialog]');
    if (dialogButton) dialogButton.dataset.dialog === 'source' ? showSource() : showMethod();
    const jobButton = event.target.closest('[data-job]');
    if (jobButton) showJob(jobButton.dataset.job);
    const intervalButton = event.target.closest('[data-segment]');
    if (intervalButton) {
        const segment = state.data.summary.segments[Number(intervalButton.dataset.segment)];
        if (segment.jobId) showJob(segment.jobId);
        else showDialog(`Station ${segment.station.slice(2)} · Idle interval`, `<p class="dialog-copy">A recorded gap between consecutive jobs at this station.</p>${details([['Date', dayLabel(segment.start.slice(0, 10))], ['Idle duration', `${number(segment.minutes, 2)} minutes`], ['From', timeLabel(segment.start)], ['Until', timeLabel(segment.end)]])}<div class="dialog-note">The source records the interval, not a reason for the stop. Scheduled break shading does not change this duration.</div>`, 'STATION ACTIVITY');
    }
    const stationButton = event.target.closest('[data-station]');
    if (stationButton) { loadData({ station: stationButton.dataset.station, date: state.data.date }); $('#timeline').scrollIntoView({ behavior: 'smooth' }); }
});

document.addEventListener('change', (event) => {
    if (event.target.id === 'station-filter') loadData({ station: event.target.value, date: state.data.date });
});

render();
