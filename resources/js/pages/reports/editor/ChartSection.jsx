import { useState } from 'react';
import monthlyReportService from '@/services/monthlyReportService';

const cellInput = 'w-full rounded border border-gray-300 px-2 py-1 text-xs text-right focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-100';

// Chart preview + editable series table for the S-curve-style sections
// (2.2 physical progress %, 2.4 financial progress RM). The chart itself is
// rendered server-side as an SVG (no client charting lib needed) — this
// component only shows it and lets the underlying series be corrected.
//
// `merged.series` = { months: string[], scheduled: number[], actual: (number|null)[], ... }.
// Only months/scheduled/actual are sent back as overrides — other keys such
// as `actual_pct` (2.4) are server-derived and not editable here.
export default function ChartSection({ config, reportId, sectionKey, merged, canEdit, onOverridesChange, version }) {
    const series = merged?.series || { months: [], scheduled: [], actual: [] };
    const [rows, setRows] = useState(() => {
        const months = series.months || [];
        return months.map((m, i) => ({
            month: m,
            scheduled: series.scheduled?.[i] ?? null,
            actual: series.actual?.[i] ?? null,
        }));
    });

    const emit = (next) => {
        setRows(next);
        onOverridesChange({
            series: {
                months: next.map((r) => r.month),
                scheduled: next.map((r) => r.scheduled),
                actual: next.map((r) => r.actual),
            },
        });
    };

    const updateCell = (idx, key, raw) => {
        const val = raw === '' ? null : Number(raw);
        emit(rows.map((r, i) => (i === idx ? { ...r, [key]: val } : r)));
    };

    const chartUrl = monthlyReportService.getChartUrl(reportId, sectionKey, version);

    return (
        <div className="space-y-4">
            <div className="overflow-hidden rounded-lg bg-white ring-1 ring-gray-200">
                {/* Cookie-authenticated <img> — same pattern as the PDF export link. */}
                <img
                    key={`${sectionKey}-${version}`}
                    src={chartUrl}
                    alt={`${sectionKey} chart preview`}
                    className="mx-auto max-w-full"
                    loading="lazy"
                />
            </div>

            <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Month</th>
                            <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">
                                Scheduled{config?.unit ? ` (${config.unit})` : ''}
                            </th>
                            <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">
                                Actual{config?.unit ? ` (${config.unit})` : ''}
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rows.map((r, idx) => (
                            <tr key={idx}>
                                <td className="px-3 py-2 text-sm text-gray-600">{r.month}</td>
                                <td className="px-2 py-2">
                                    <input
                                        type="number"
                                        value={r.scheduled ?? ''}
                                        onChange={(e) => updateCell(idx, 'scheduled', e.target.value)}
                                        disabled={!canEdit}
                                        className={cellInput}
                                    />
                                </td>
                                <td className="px-2 py-2">
                                    <input
                                        type="number"
                                        value={r.actual ?? ''}
                                        onChange={(e) => updateCell(idx, 'actual', e.target.value)}
                                        disabled={!canEdit}
                                        className={cellInput}
                                    />
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={3} className="px-3 py-4 text-center text-sm text-gray-400">No data.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
