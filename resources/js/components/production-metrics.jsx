import React from 'react';
import { createRoot } from 'react-dom/client';

const number = (value, digits = 0, maximumDigits = digits) => Number(value).toLocaleString('en-GB', { minimumFractionDigits: digits, maximumFractionDigits: maximumDigits });
let root;

export function renderProductionMetrics({ summary, previous }) {
    const metrics = [
        { key: 'efficiency', label: 'Efficiency % by Station', formula: 'Running / Working Hour', name: 'Efficiency', rate: true },
        { key: 'availability', label: 'Availability %', formula: 'Running / (Running + Idle)', name: 'Availability', rate: true },
        { key: 'performance', label: 'Performance %', formula: 'Std Time / Running', name: 'Performance', rate: true },
        { key: 'running', label: 'Total Running (Min)' },
        { key: 'idle', label: 'Total Idle (Min)' },
        { key: 'available', label: 'Working Hour (Min)' },
        { key: 'cycles', label: 'Total Qty' },
    ];
    root ??= createRoot(document.querySelector('#kpi-grid'));
    root.render(metrics.map(({ key, label, formula, name, rate }) => {
        const digits = key === 'cycles' || key === 'available' ? 0 : 1;
        const value = summary[key] === null ? 'N/A' : number(summary[key], digits, key === 'available' ? 4 : digits);
        const valueClass = value.length >= 11 ? 'report-metric-value-wide' : value.length >= 8 ? 'report-metric-value-long' : undefined;
        const difference = previous && summary[key] !== null && previous[key] !== null ? summary[key] - previous[key] : null;
        return <div className="report-metric-cell" key={key}>
        <article className={`report-metric report-metric-${key}`} title={key === 'cycles' ? 'Count of Running records (cycles), as in Power BI' : label}>
            <h2>{label}</h2>
            <strong className={valueClass}>{value}{rate && summary[key] !== null && <span>%</span>}</strong>
            <small className="report-trend">{difference === null ? 'No comparison' : `${difference > 0 ? '+' : ''}${number(difference, key === 'cycles' ? 0 : 1)} ${rate ? 'pp' : key === 'cycles' ? 'cycles' : 'min'} vs ${new Date(`${previous.date}T12:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`}</small>
        </article>
        {formula && <div className="report-formula"><strong>{name}:</strong><span>{formula}</span></div>}
    </div>;
    }));
}
