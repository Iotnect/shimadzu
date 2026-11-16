import React from 'react';
import { createRoot } from 'react-dom/client';
import { flushSync } from 'react-dom';
import { Check, ChevronDown, ChevronLeft, ChevronRight, Download, RotateCcw, Search, SlidersHorizontal } from 'lucide-react';
import { DropdownMenu } from 'radix-ui';
import { Button } from './ui/button';
import { Input } from './ui/input';

const roots = new WeakMap();

function mount(element, content) {
    if (element) {
        const root = roots.get(element) ?? createRoot(element);
        roots.set(element, root);
        flushSync(() => root.render(content));
    }
}

export function mountDashboardControls(data) {
    mount(document.querySelector('.filter-bar'), <>
        <div className="filter-intro"><SlidersHorizontal className="size-4 text-muted-foreground" /><strong>Worklog filters</strong></div>
        <label className="worklog-filter-field">From
            <Input type="date" id="from-filter" defaultValue={data.from} required className="w-full min-w-0" />
        </label>
        <label className="worklog-filter-field">To
            <Input type="date" id="to-filter" defaultValue={data.to} required className="w-full min-w-0" />
        </label>
        <Button type="submit" variant="outline" id="apply-range" className="cursor-pointer">Apply date range</Button>
        <Button type="button" variant="ghost" id="reset-filters" className="cursor-pointer text-muted-foreground"><RotateCcw />Reset filters</Button>
        <span id="data-period" className="text-xs text-muted-foreground" />
    </>);

    const exportButton = document.querySelector('#export-report');
    const exportMount = document.createElement('div');
    exportButton.replaceWith(exportMount);
    mount(exportMount, <Button id="export-report"><Download />Export report</Button>);

    mount(document.querySelector('.search-field'), <>
        <Search className="size-4 shrink-0 text-muted-foreground" />
        <Input id="job-search" type="search" placeholder="Search jobs, SN, products, operators…" aria-label="Search work log" className="border-0 bg-transparent p-0 shadow-none focus-visible:ring-0" />
    </>);
    mount(document.querySelector('.pagination'), <>
        <span id="pagination-label" />
        <div className="flex items-center gap-3"><Button variant="outline" size="sm" id="jobs-prev"><ChevronLeft />Previous</Button><span id="page-number" className="tabular-nums" /><Button variant="outline" size="sm" id="jobs-next">Next<ChevronRight /></Button></div>
    </>);
}

export function renderSensorOptions(data, selectSensor) {
    mount(document.querySelector('#sensor-options'), <DropdownMenu.Root modal={false}>
        <DropdownMenu.Trigger asChild>
            <Button type="button" variant="outline" className="cursor-pointer">Sensor Option <ChevronDown aria-hidden="true" /></Button>
        </DropdownMenu.Trigger>
        <DropdownMenu.Portal>
            <DropdownMenu.Content align="end" sideOffset={6} collisionPadding={12}
                className="z-50 max-h-[min(26rem,var(--radix-dropdown-menu-content-available-height))] w-52 max-w-[calc(100vw_-_1.5rem)] overflow-y-auto rounded-md border border-border bg-background p-1 text-foreground shadow-md">
                <DropdownMenu.RadioGroup value={data.sensor} onValueChange={selectSensor}>
                    {data.sensors.map(sensor => <DropdownMenu.RadioItem key={sensor} value={sensor}
                        className="flex min-h-10 cursor-pointer items-center justify-between gap-3 rounded px-3 text-sm outline-none data-highlighted:bg-accent data-highlighted:text-accent-foreground data-[state=checked]:font-semibold">
                        {data.sensorLabels?.[sensor] ?? sensor}
                        <DropdownMenu.ItemIndicator><Check className="size-4" aria-hidden="true" /></DropdownMenu.ItemIndicator>
                    </DropdownMenu.RadioItem>)}
                </DropdownMenu.RadioGroup>
            </DropdownMenu.Content>
        </DropdownMenu.Portal>
    </DropdownMenu.Root>);
}

export function renderStationOptions(data) {
    const canSelect = Boolean(window.detSensorPage || window.detStationPage);
    mount(document.querySelector('#place-slicer'), canSelect
        ? <label className="place-list">Section
            <select key={data.sensor} id="station-filter" aria-label="Section" size={Math.max(5, data.stations.length + 1)} defaultValue={data.station} disabled={Boolean(window.detStationPage)} className="border-0 bg-transparent text-sm focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60">
                {!window.detStationPage && <option value="all">All sections</option>}
                {data.stations.map(station => <option key={station} value={station}>{station}</option>)}
            </select>
        </label>
        : <div className="place-list">Sections<ul className="space-y-2 font-normal">{data.stations.map(station => <li key={station}>{station}</li>)}</ul></div>);
}
