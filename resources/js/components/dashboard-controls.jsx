import React from 'react';
import { createRoot } from 'react-dom/client';
import { flushSync } from 'react-dom';
import { ChevronLeft, ChevronRight, Download, RotateCcw, Search, SlidersHorizontal } from 'lucide-react';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Badge } from './ui/badge';

function mount(element, content) {
    if (element) {
        const root = createRoot(element);
        flushSync(() => root.render(content));
    }
}

export function mountDashboardControls(data) {
    const stationPage = window.detStationPage;
    mount(document.querySelector('.filter-bar'), <>
        <div className="filter-intro"><SlidersHorizontal className="size-4 text-muted-foreground" /><strong>Daily overview</strong></div>
        <div className="date-control">
            <Button variant="outline" size="icon" id="previous-day" aria-label="Previous available day"><ChevronLeft /></Button>
            <Input type="date" id="date-filter" aria-label="Production date" defaultValue={data.date} min={data.source.from} max={data.source.to} className="w-auto min-w-0" />
            <Button variant="outline" size="icon" id="next-day" aria-label="Next available day"><ChevronRight /></Button>
        </div>
        <select id="station-filter" aria-label="Filter station" defaultValue={data.station} disabled={stationPage !== null} className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60">
            {stationPage === null && <option value="all">All stations</option>}
            {(stationPage ? [stationPage] : data.stations).map(station => <option key={station} value={station}>Station {station.slice(2)}</option>)}
        </select>
        <Button variant="ghost" id="reset-filters" className="text-muted-foreground"><RotateCcw />Reset filters</Button>
        <Badge variant="outline" id="data-period" className="ml-auto" />
    </>);

    const exportButton = document.querySelector('#export-report');
    const exportMount = document.createElement('div');
    exportButton.replaceWith(exportMount);
    mount(exportMount, <Button id="export-report"><Download />Export report</Button>);

    mount(document.querySelector('.search-field'), <>
        <Search className="size-4 shrink-0 text-muted-foreground" />
        <Input id="job-search" type="search" placeholder="Search jobs, products, operators…" aria-label="Search work log" className="border-0 bg-transparent p-0 shadow-none focus-visible:ring-0" />
    </>);
    mount(document.querySelector('.pagination'), <>
        <span id="pagination-label" />
        <div className="flex items-center gap-3"><Button variant="outline" size="sm" id="jobs-prev"><ChevronLeft />Previous</Button><span id="page-number" className="tabular-nums" /><Button variant="outline" size="sm" id="jobs-next">Next<ChevronRight /></Button></div>
    </>);
}
