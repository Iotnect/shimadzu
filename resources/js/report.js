const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
const number = (value, digits = 1) => Number(value).toLocaleString('en-GB', { maximumFractionDigits: digits });
const rate = (value) => value === null ? 'Unavailable' : `${number(value)}%`;

export function reportHtml(data, jobs, search = '', exportedAt = new Date()) {
    const station = data.station === 'all' ? 'All stations' : `Station ${data.station.slice(2)}`;
    const dateParts = new Intl.DateTimeFormat('en', { timeZone: 'Asia/Kuala_Lumpur', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(exportedAt);
    const datePart = (type) => dateParts.find((part) => part.type === type).value;
    const exportDate = `${datePart('year')}-${datePart('month')}-${datePart('day')}`;
    const title = `shimadzu ${station} ${exportDate}`;
    const summary = data.summary;
    const metrics = [
        ['Efficiency', rate(summary.efficiency)],
        ['Availability', rate(summary.availability)],
        ['Performance', rate(summary.performance)],
        ['Logged cycles', number(summary.cycles, 0)],
        ['Running time', `${number(summary.running)} min`],
        ['Idle time', `${number(summary.idle)} min`],
    ];
    const rows = jobs.map((job) => {
        const minutes = (new Date(job.end) - new Date(job.start)) / 60000;
        return `<tr><td>${escapeHtml(job.id)}</td><td>${escapeHtml(job.station)}</td><td>${escapeHtml(job.reference)}</td><td>${escapeHtml(job.product)}</td><td>${escapeHtml(job.worker)}</td><td class="time">${escapeHtml(job.start.replace('T', ' '))}</td><td class="time">${escapeHtml(job.end.replace('T', ' '))}</td><td class="numeric">${number(minutes, 2)}</td><td class="numeric">${job.quantity === null ? 'Not recorded' : escapeHtml(job.quantity)}</td></tr>`;
    }).join('');

    return `<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>${escapeHtml(title)}</title>
<style>
    @page { size: A4 landscape; margin: 14mm; }
    * { box-sizing: border-box; }
    html { color-scheme: light; }
    body { margin: 0; color: #252a31; background: #fff; font: 10px/1.5 Arial, sans-serif; }
    header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #d91b25; padding-bottom: 14px; margin-bottom: 18px; }
    .brand { font-size: 21px; font-weight: bold; letter-spacing: 1px; }
    .brand small { display: block; color: #68707b; font-size: 8px; letter-spacing: 2px; margin-top: 3px; }
    h1 { font-size: 21px; margin: 0 0 3px; }
    h2 { font-size: 13px; margin: 22px 0 8px; }
    p { margin: 4px 0; }
    .report-heading { text-align: right; }
    .metrics { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; break-inside: avoid; }
    .metric { padding: 12px; border: 1px solid #dce0e5; border-radius: 5px; }
    .metric span { display: block; color: #626b77; font-size: 9px; }
    .metric strong { display: block; font-size: 19px; margin-top: 4px; }
    .note { color: #626b77; font-size: 9px; margin-top: 10px; }
    table { border-collapse: collapse; width: 100%; table-layout: fixed; font-size: 8px; }
    thead { display: table-header-group; }
    th { color: #404753; background: #f2f3f5; text-align: left; font-size: 8px; }
    th, td { border-bottom: 1px solid #dce0e5; padding: 7px 5px; vertical-align: top; overflow-wrap: anywhere; }
    tr { break-inside: avoid; }
    .numeric { text-align: right; }
    .time { font-variant-numeric: tabular-nums; }
    footer { margin-top: 16px; border-top: 1px solid #dce0e5; padding-top: 8px; color: #626b77; font-size: 8px; }
    h2 { break-after: avoid; }
</style></head><body>
<header><div class="brand">SHIMADZU<small>PRODUCTION INTELLIGENCE</small></div><div class="report-heading"><h1>Production report</h1><p>${escapeHtml(data.date)} &middot; ${escapeHtml(station)}</p></div></header>
<section class="metrics" aria-label="Daily production metrics">${metrics.map(([label, value]) => `<div class="metric"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('')}</section>
<p class="note">Metrics cover all jobs for the selected date and station(s). Available time: ${number(summary.available, 0)} minutes. Recorded quantity: ${number(summary.quantity, 0)}${summary.missingQuantity ? ` (${number(summary.missingQuantity, 0)} jobs with missing quantity)` : ''}.</p>
<h2>Production work log &middot; ${jobs.length} matching records</h2>
${search ? `<p class="note">Search: ${escapeHtml(search)}. The table includes all matching records across every page.</p>` : ''}
<table><colgroup><col style="width:5%"><col style="width:6%"><col style="width:12%"><col style="width:16%"><col style="width:13%"><col style="width:15%"><col style="width:15%"><col style="width:9%"><col style="width:9%"></colgroup><thead><tr><th>Job ID</th><th>Station</th><th>Reference</th><th>Product</th><th>Operator</th><th>Start</th><th>End</th><th class="numeric">Cycle (min)</th><th class="numeric">Quantity</th></tr></thead><tbody>${rows}</tbody></table>
<footer>Source: ${escapeHtml(data.source.name)} &middot; Imported: ${escapeHtml(data.source.importedAt)}<br>Historical snapshot. Efficiency = running / available time. Availability = running / (running + idle time). Performance = expected / running time. Missing standards are shown as unavailable.</footer>
</body></html>`;
}

export function printReport(html) {
    const frame = document.createElement('iframe');
    frame.title = 'Production report PDF preview';
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1px;height:1px;border:0;';
    frame.addEventListener('load', () => {
        const originalTitle = document.title;
        document.title = frame.contentDocument.title;
        frame.contentWindow.addEventListener('afterprint', () => {
            document.title = originalTitle;
            frame.remove();
        }, { once: true });
        frame.contentWindow.focus();
        frame.contentWindow.print();
    }, { once: true });
    frame.srcdoc = html;
    document.body.append(frame);
}
