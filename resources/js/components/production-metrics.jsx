import React from 'react';
import { createRoot } from 'react-dom/client';
import { Activity, Clock3, TrendingUp, Package, ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from './ui/card';
import { Badge } from './ui/badge';

const number = (value, digits = 0) => Number(value).toLocaleString('en-GB', { minimumFractionDigits: digits, maximumFractionDigits: digits });
let root;

export function renderProductionMetrics(data) {
    const { summary, previous } = data;
    const metrics = [
        { key: 'efficiency', label: 'Efficiency', icon: Activity, context: 'Running time / available time' },
        { key: 'availability', label: 'Availability', icon: Clock3, context: 'Running time / recorded time' },
        { key: 'performance', label: 'Performance', icon: TrendingUp, context: 'Expected time / running time' },
        { key: 'cycles', label: 'Logged cycles', icon: Package, context: `${number(summary.quantity)} units recorded${summary.missingQuantity ? ' · incomplete qty' : ''}` },
    ];
    root ??= createRoot(document.querySelector('#kpi-grid'));
    root.render(metrics.map(({ key, label, icon: Icon, context }) => {
        const value = summary[key];
        const isCycles = key === 'cycles';
        const canCompare = value !== null && previous && previous[key] !== null;
        const difference = canCompare ? value - previous[key] : null;
        const Trend = difference > 0 ? ArrowUpRight : difference < 0 ? ArrowDownRight : Minus;
        return <Card key={key} className="metric-card gap-4 py-5">
            <CardHeader className="flex items-center justify-between px-5">
                <CardTitle className="text-sm font-medium text-muted-foreground"><h2 className="text-sm font-medium text-muted-foreground">{label}</h2></CardTitle>
                <Icon className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-3 px-5">
                <div className="flex items-baseline gap-1.5 text-4xl font-semibold tracking-tight tabular-nums">{value === null ? '—' : number(value, isCycles ? 0 : 1)}<span className="text-base font-normal text-muted-foreground">{isCycles ? 'cycles' : '%'}</span></div>
                <CardDescription className="text-xs leading-relaxed">{value === null ? (summary.cycles ? 'Standard time not configured' : 'No activity on this date') : context}</CardDescription>
                <div className="mt-auto flex flex-wrap items-center gap-2 pt-2 text-xs text-muted-foreground">
                    <Badge variant="outline" className={difference > 0 ? 'metric-up' : difference < 0 ? 'metric-down' : ''}><Trend className="size-3" />{difference === null ? 'No comparison' : `${number(Math.abs(difference), isCycles ? 0 : 1)}${isCycles ? '' : ' pp'}`}</Badge>
                    <span>{canCompare ? `vs ${new Date(`${previous.date}T12:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}` : 'Previous available day'}</span>
                </div>
            </CardContent>
        </Card>;
    }));
}
