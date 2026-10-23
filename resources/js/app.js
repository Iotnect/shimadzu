const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];
const icon = (name, extra = '') => `<svg class="icon ${extra}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#icon-${name}"/></svg>`;
const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
const number = (value, digits = 0) => Number(value).toLocaleString('en-GB', { minimumFractionDigits: digits, maximumFractionDigits: digits });
const percent = (value) => value === null ? '—' : `${number(value, 1)}%`;
const dayLabel = (date, options = {}) => new Date(`${date}T12:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', ...options });
const timeLabel = (date) => date.slice(11, 16);
const duration = (start, end) => (new Date(end) - new Date(start)) / 60000;
const state = { data: window.detDashboard, page: 1, search: '', controller: null };
let toastTimer;

function notify(message) {
    clearTimeout(toastTimer);
    $('#toast').textContent = message;
    $('#toast').hidden = false;
    toastTimer = setTimeout(() => { $('#toast').hidden = true; }, 3500);
}

function renderMetrics() {
    const { summary, previous } = state.data;
    const metrics = [
        { key: 'efficiency', label: 'Efficiency', icon: 'pulse', context: 'Running time / available time' },
        { key: 'availability', label: 'Availability', icon: 'clock', context: 'Running time / recorded time' },
        { key: 'performance', label: 'Performance', icon: 'trend', context: 'Expected time / running time' },
        { key: 'cycles', label: 'Logged cycles', icon: 'box', context: `${number(summary.quantity)} units recorded in source${summary.missingQuantity ? ' · incomplete qty' : ''}` },
    ];
    $('#kpi-grid').innerHTML = metrics.map((metric) => {
        const value = summary[metric.key];
        const isCycles = metric.key === 'cycles';
        const canCompare = value !== null && previous && previous[metric.key] !== null;
        const difference = canCompare ? value - previous[metric.key] : null;
        const deltaClass = difference > 0 ? '' : difference < 0 ? 'negative' : 'neutral';
        const deltaText = difference === null ? 'No comparison' : `${difference > 0 ? '↗' : difference < 0 ? '↘' : '–'} ${number(Math.abs(difference), isCycles ? 0 : 1)}${isCycles ? '' : ' pp'}`;
        return `<article class="kpi-card"><div class="kpi-top"><h2 class="kpi-label">${metric.label}</h2><span class="kpi-icon">${icon(metric.icon)}</span></div><div class="kpi-number">${value === null ? '—' : number(value, isCycles ? 0 : 1)}<small>${isCycles ? 'cycles' : '%'}</small></div><p class="kpi-context">${value === null ? (summary.cycles ? 'Standard time not configured' : 'No activity on this date') : metric.context}</p><div class="kpi-bottom"><span class="delta ${deltaClass}">${deltaText}</span><span>${canCompare ? `vs ${dayLabel(previous.date, { year: undefined })}` : 'Choose a day with activity'}</span></div><div class="kpi-mini-track" aria-hidden="true"><span style="width:${Math.min(100, Math.max(0, isCycles ? summary.cycles / Math.max(summary.cycles, previous?.cycles ?? 0, 1) * 100 : value ?? 0))}%"></span></div></article>`;
    }).join('');
}

function renderTimeline() {
    const { summary, date } = state.data;
    $('#station-count').textContent = `${summary.rows.length} station${summary.rows.length === 1 ? '' : 's'}`;
    const stats = [
        ['clock', 'Running', `${number(summary.running, 1)} min`],
        ['clock', 'Idle', `${number(summary.idle, 1)} min`],
        ['list', 'Expected', summary.expected === null ? 'Not configured' : `${number(summary.expected, 1)} min`],
        ['grid', 'Available', `${number(summary.available)} min`],
    ];
    $('#timeline-stats').innerHTML = stats.map(([symbol, label, value]) => `<span class="timeline-stat">${icon(symbol)}${label}<strong>${value}</strong></span>`).join('');

    const minuteOffset = (timestamp) => duration(`${date}T00:00:00`, timestamp);
    const starts = summary.segments.map((segment) => minuteOffset(segment.start));
    const ends = summary.segments.map((segment) => minuteOffset(segment.end));
    const startMinute = Math.floor(Math.min(8 * 60, ...starts) / 60) * 60;
    const endMinute = Math.ceil(Math.max(17 * 60, ...ends) / 60) * 60;
    const span = endMinute - startMinute;
    const position = (minute) => (minute - startMinute) / span * 100;
    const hourCount = span / 60;
    const ticks = Array.from({ length: hourCount + 1 }, (_, index) => {
        const minute = startMinute + index * 60;
        const label = `${String(Math.floor(minute / 60) % 24).padStart(2, '0')}:${String(Math.round(minute % 60)).padStart(2, '0')}`;
        return `<span class="axis-tick" style="left:${index / hourCount * 100}%">${label}${minute >= 1440 ? ' +1d' : ''}</span>`;
    }).join('');
    const breaks = [[615, 630, 'Morning break'], [735, 780, 'Lunch break'], [915, 930, 'Tea break']];
    const breakBands = breaks.map(([start, end, label]) => `<span class="break-band" style="left:${position(start)}%;width:${(end - start) / span * 100}%" title="${label}: ${Math.floor(start / 60)}:${String(start % 60).padStart(2, '0')}–${Math.floor(end / 60)}:${String(end % 60).padStart(2, '0')}"></span>`).join('');
    $('#timeline-chart').innerHTML = `<div class="timeline-axis"><div class="timeline-axis-label">STATION</div><div class="axis-track">${ticks}</div></div>${summary.rows.map((row) => {
        const intervals = summary.segments.map((segment, index) => {
            if (segment.station !== row.station) return '';
            const label = `${segment.station} · ${segment.status} · ${timeLabel(segment.start)}–${timeLabel(segment.end)} · ${number(segment.minutes, 1)} min`;
            return `<button class="interval ${segment.status.toLowerCase()}" style="left:${position(minuteOffset(segment.start))}%;width:${Math.max(.12, duration(segment.start, segment.end) / span * 100)}%" title="${label}" aria-label="${label}" data-segment="${index}"></button>`;
        }).join('');
        return `<div class="timeline-row"><div class="station-label"><span class="station-number">${escape(row.station.slice(2))}</span><div class="station-label-text"><strong>Station ${escape(row.station.slice(2))}</strong><small>${row.cycles} logged cycles</small></div></div><div class="track" style="background-image:linear-gradient(to right,var(--line) 1px,transparent 1px);background-size:${100 / hourCount}% 100%">${breakBands}${intervals || '<span class="timeline-empty">No recorded activity</span>'}</div></div>`;
    }).join('')}<div class="break-labels">${breaks.map(([start, end, label]) => `<span class="break-label" style="left:${position((start + end) / 2)}%">${label}</span>`).join('')}</div>`;
}

function renderDistribution() {
    const { running, idle, availability } = state.data.summary;
    const total = running + idle;
    const runningShare = availability ?? 0;
    $('#distribution').innerHTML = `<div class="donut" role="img" aria-label="${total ? `${percent(availability)} of recorded time is running` : 'No recorded activity'}" style="background:${total ? `conic-gradient(var(--chart-running) 0 ${runningShare}%, var(--chart-idle) ${runningShare}% 100%)` : '#ededed'}"><div class="donut-center"><strong>${number(total, 1)}</strong><small>recorded min</small></div></div><div class="distribution-key"><div class="distribution-key-item"><span><i class="dot running"></i>Running</span><strong>${number(running, 1)} <small>min · ${number(runningShare, 1)}%</small></strong></div><div class="distribution-key-item"><span><i class="dot idle"></i>Idle</span><strong>${number(idle, 1)} <small>min · ${number(total ? 100 - runningShare : 0, 1)}%</small></strong></div></div>`;
}

function renderStations() {
    $('#station-rows').innerHTML = state.data.summary.rows.map((row) => `<tr><td><span class="station-name"><span class="station-number">${escape(row.station.slice(2))}</span>Station ${escape(row.station.slice(2))}</span></td><td><div class="efficiency-cell"><span>${percent(row.efficiency)}</span><div class="efficiency-track"><i style="width:${Math.min(100, row.efficiency ?? 0)}%"></i></div></div></td><td>${number(row.running)} <small>min</small></td><td>${number(row.idle)} <small>min</small></td><td>${row.cycles}</td><td><button class="icon-button" data-station="${escape(row.station)}" aria-label="Explore Station ${escape(row.station.slice(2))}">${icon('arrow')}</button></td></tr>`).join('');
}

function matchingJobs() {
    return state.data.jobs.filter((job) => [job.id, job.reference, job.product, job.worker, job.station].join(' ').toLowerCase().includes(state.search));
}

function renderJobs() {
    const jobs = matchingJobs();
    const pageCount = Math.max(1, Math.ceil(jobs.length / 6));
    state.page = Math.min(state.page, pageCount);
    const offset = (state.page - 1) * 6;
    $('#job-count').textContent = `${state.data.jobs.length} records`;
    $('#job-rows').innerHTML = jobs.slice(offset, offset + 6).map((job) => `<tr><td><span class="job-ref">#${escape(job.id)}</span><span class="worker-name">${escape(job.reference)}</span></td><td><span class="station-pill">Station ${escape(job.station.slice(2))}</span></td><td><span class="product-name">${escape(job.product)}</span><span class="worker-name">${escape(job.worker.toLowerCase())}</span></td><td>${timeLabel(job.start)} <span style="color:#aaaaaa">→</span> ${timeLabel(job.end)}</td><td>${number(duration(job.start, job.end), 1)} <small>min</small></td><td>${job.quantity === null ? '<span title="Quantity missing in source">Not recorded</span>' : number(job.quantity)}</td><td><button class="icon-button" data-job="${escape(job.id)}" aria-label="View job ${escape(job.id)}">${icon('arrow')}</button></td></tr>`).join('') || `<tr><td colspan="7" class="empty-state">${state.search ? 'No jobs match your search. Try a job reference, product, or operator.' : 'No activity was recorded for this date and station. Choose another date to explore.'}</td></tr>`;
    $('#pagination-label').textContent = jobs.length ? `Showing ${offset + 1}–${Math.min(offset + 6, jobs.length)} of ${jobs.length} records` : 'No matching records';
    $('#page-number').textContent = `${state.page} / ${pageCount}`;
    $('#jobs-prev').disabled = state.page === 1;
    $('#jobs-next').disabled = state.page === pageCount;
}

function render() {
    $('#date-filter').value = state.data.date;
    $('#station-filter').value = state.data.station;
    $('#previous-day').disabled = !state.data.dates.some((date) => date < state.data.date);
    $('#next-day').disabled = !state.data.dates.some((date) => date > state.data.date);
    $('#data-period').textContent = state.data.date === state.data.source.to ? 'Latest available day' : dayLabel(state.data.date, { weekday: 'short' });
    renderMetrics();
    renderTimeline();
    renderDistribution();
    renderStations();
    renderJobs();
}

async function loadData(date, station = state.data.station) {
    state.controller?.abort();
    const controller = new AbortController();
    state.controller = controller;
    $('#main').setAttribute('aria-busy', 'true');
    $('#export-report').disabled = true;
    $('#error-banner').hidden = true;
    $('#load-status').textContent = 'Loading production data';
    try {
        const query = new URLSearchParams({ date, station });
        const response = await fetch(`${window.detDataUrl}?${query}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Unable to load this date. Please check your connection and try again.');
        state.data = await response.json();
        state.page = 1;
        render();
        $('#load-status').textContent = `Loaded ${state.data.jobs.length} jobs for ${dayLabel(date)}`;
    } catch (error) {
        if (error.name === 'AbortError') return;
        $('#error-banner').textContent = error.message;
        $('#error-banner').hidden = false;
        $('#date-filter').value = state.data.date;
        $('#station-filter').value = state.data.station;
        $('#load-status').textContent = 'Data could not be loaded. Previous results are still displayed.';
    } finally {
        if (state.controller === controller) {
            $('#main').removeAttribute('aria-busy');
            $('#export-report').disabled = false;
        }
    }
}

function showDialog(title, content, eyebrow = 'DET INTELLIGENCE') {
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
    showDialog(`Job #${job.id}`, `<p class="dialog-copy">${escape(job.product)}</p>${details([
        ['Station', `Station ${job.station.slice(2)}`], ['Work date', dayLabel(job.start.slice(0, 10))],
        ['Start', `${timeLabel(job.start)}:${job.start.slice(17, 19)}`], ['End', `${dayLabel(job.end.slice(0, 10))} · ${job.end.slice(11, 19)}`],
        ['Cycle duration', `${number(duration(job.start, job.end), 2)} minutes`], ['Recorded quantity', job.quantity === null ? 'Not recorded in source' : `${job.quantity} units`],
        ['Operator', job.worker], ['Instruction reference', job.reference || 'Not recorded'],
    ])}<div class="dialog-note">A logged cycle is a job record. Recorded quantity is the Excel qty value; a zero quantity is preserved as zero.</div>`, 'PRODUCTION WORK LOG');
}

function showMethod() {
    showDialog('A clear view of your metrics', `<div class="metric-guide"><div><h3>Efficiency</h3><p>Running minutes ÷ available minutes. Available time is 435 minutes (7.25 hours) per selected station per day, following the PBIX assumption. All stations are included in the daily capacity, even if no activity is recorded. Overtime can produce values above 100%.</p></div><div><h3>Availability</h3><p>Running minutes ÷ (running + idle minutes). Idle is a same-day gap of less than 12 hours between jobs at the same station. Time before the first job and after the last job is excluded. Overlapping jobs are counted once in total running time.</p></div><div><h3>Performance</h3><p>Expected minutes ÷ running minutes. Expected time is logged cycles × the standard minutes for each station, summed across selected stations. These standards come from the PBIX: St1 25.515, St2 37.24, St3 23.695, St4 14.8925 minutes. A missing standard leaves performance unavailable.</p></div><div><h3>Cycles & recorded quantity</h3><p>Cycles count job records. Units sum the Excel qty field. Blank quantities are marked as missing; zero remains zero. Comparisons use the previous available production date, with percentage changes shown in percentage points (pp).</p></div><div><h3>Scheduled breaks</h3><p>Morning 10:15–10:30 · Lunch 12:15–13:00 · Tea 15:15–15:30. Shading is a visual reference. Breaks are not subtracted from recorded job or idle intervals.</p></div></div>`, 'HELP & METRIC GUIDE');
}

function showSource() {
    const { source } = state.data;
    showDialog('Your production data', `<p class="dialog-copy">This workspace uses an imported Excel snapshot. The original workbook and PBIX have not been modified.</p>${details([
        ['Workbook', source.name], ['Worksheet', 'lc2kdet_worklog'],
        ['Coverage begins', dayLabel(source.from)], ['Coverage ends', dayLabel(source.to)],
        ['Imported job records', number(source.records)], ['Stations', state.data.stations.join(', ')],
        ['Imported on', dayLabel(source.importedAt.slice(0, 10))], ['Standard time source', source.standardSource],
    ])}<div class="dialog-note">This is historical data, not a live feed. The workbook snapshot ends on ${dayLabel(source.to)}. New workbook changes require a new import. Production dates and times are displayed as recorded in the source.</div>`, 'DATA SOURCE & COVERAGE');
}

function exportReport() {
    const jobs = matchingJobs();
    if (!jobs.length) return notify('There are no matching job records to export.');
    const safeCell = (value) => {
        let content = String(value ?? '');
        if (/^[=+@\-\t\r]/.test(content)) content = `'${content}`;
        return `"${content.replace(/"/g, '""')}"`;
    };
    const rows = [['Job ID', 'Station', 'Product', 'Operator', 'Instruction reference', 'Start', 'End', 'Cycle minutes', 'Recorded quantity'], ...jobs.map((job) => [job.id, job.station, job.product, job.worker, job.reference, job.start, job.end, duration(job.start, job.end).toFixed(2), job.quantity])];
    const url = URL.createObjectURL(new Blob(['\uFEFF', rows.map((row) => row.map(safeCell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `DET-production-${state.data.date}-${state.data.station}.csv`;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    notify(`${jobs.length} job records exported${state.search ? ' matching your search' : ''}.`);
}

$('#date-filter').addEventListener('change', (event) => {
    if (!event.target.value || !event.target.checkValidity()) {
        event.target.value = state.data.date;
        return notify('Choose a date within the workbook coverage.');
    }
    loadData(event.target.value);
});
$('#station-filter').addEventListener('change', (event) => loadData(state.data.date, event.target.value));
$('#previous-day').addEventListener('click', () => {
    const dates = state.data.dates.filter((date) => date < state.data.date);
    if (dates.length) loadData(dates.at(-1));
});
$('#next-day').addEventListener('click', () => {
    const date = state.data.dates.find((date) => date > state.data.date);
    if (date) loadData(date);
});
$('#reset-filters').addEventListener('click', () => {
    state.search = '';
    $('#job-search').value = '';
    loadData(state.data.source.to, window.detStationPage ?? 'all');
});
$('#compare-stations')?.addEventListener('click', () => loadData(state.data.date, 'all'));
$('#job-search').addEventListener('input', (event) => { state.search = event.target.value.trim().toLowerCase(); state.page = 1; renderJobs(); });
$('#jobs-prev').addEventListener('click', () => { state.page--; renderJobs(); });
$('#jobs-next').addEventListener('click', () => { state.page++; renderJobs(); });
$('#export-report').addEventListener('click', exportReport);
$('#close-dialog').addEventListener('click', () => $('#detail-dialog').close());
$('#detail-dialog').addEventListener('click', (event) => { if (event.target === $('#detail-dialog') && event.offsetX >= 0 && event.offsetY >= 0 && (event.offsetX > event.target.clientWidth || event.offsetY > event.target.clientHeight)) event.target.close(); });

document.addEventListener('click', (event) => {
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
    if (stationButton) { loadData(state.data.date, stationButton.dataset.station); $('#timeline').scrollIntoView({ behavior: 'smooth' }); }
});

render();
