// Read-only render of "text" sections (org tree, S-curve/claim series, or
// weather log). None of these have editable data in Phase B — only the
// section's `notes` (rendered separately by the shell via SectionNotes).
function TreeNode({ node, depth = 0 }) {
    return (
        <li>
            <div className="flex items-baseline gap-2 py-1" style={{ paddingLeft: depth * 16 }}>
                <span className="text-sm font-medium text-gray-900">{node.name || '-'}</span>
                {node.designation && <span className="text-xs text-gray-500">{node.designation}</span>}
            </div>
            {node.children?.length > 0 && (
                <ul>
                    {node.children.map((c, i) => (
                        <TreeNode key={i} node={c} depth={depth + 1} />
                    ))}
                </ul>
            )}
        </li>
    );
}

function SeriesTable({ series }) {
    const months = series?.months || [];
    return (
        <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
            <table className="min-w-full divide-y divide-gray-200 text-sm">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Month</th>
                        {months.map((m, i) => (
                            <th key={i} className="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">{m}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    <tr>
                        <td className="px-3 py-2 font-medium text-gray-700">Scheduled</td>
                        {(series?.scheduled || []).map((v, i) => (
                            <td key={i} className="px-3 py-2 text-right text-gray-600">{v ?? '-'}</td>
                        ))}
                    </tr>
                    <tr>
                        <td className="px-3 py-2 font-medium text-gray-700">Actual</td>
                        {(series?.actual || []).map((v, i) => (
                            <td key={i} className="px-3 py-2 text-right text-gray-600">{v ?? '-'}</td>
                        ))}
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

const HOURS = Array.from({ length: 24 }, (_, h) => h);

// A hour cell is filled when any recorded interval [s, e) (minutes since
// midnight) overlaps that hour's [h*60, (h+1)*60) window — same rule as the
// PDF's Blade template.
function hourFilled(intervals, h) {
    return (intervals || []).some(([s, e]) => s < (h + 1) * 60 && e > h * 60);
}

function WeatherLog({ data }) {
    const days = data?.days || [];
    const summary = data?.summary || {};
    return (
        <div className="space-y-3">
            <div className="flex flex-wrap gap-4 text-sm text-gray-600">
                <span><strong>{summary.total_days ?? '-'}</strong> total days</span>
                <span><strong>{summary.raining_days ?? '-'}</strong> raining days</span>
                <span><strong>{summary.raining_hours ?? '-'}</strong> raining hours</span>
            </div>
            <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200 text-xs">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="sticky left-0 z-10 bg-gray-50 px-3 py-2 text-left font-semibold uppercase text-gray-500">Date</th>
                            {HOURS.map((h) => (
                                <th key={h} className="px-1 py-2 text-center font-medium text-gray-400">{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {days.map((d, i) => (
                            <tr key={i}>
                                <td className="sticky left-0 z-10 bg-white px-3 py-2 font-medium text-gray-700">{d.date}</td>
                                {HOURS.map((h) => (
                                    <td key={h} className="px-1 py-1 text-center">
                                        <span
                                            className={`inline-block h-4 w-4 rounded-sm ${hourFilled(d.intervals, h) ? 'bg-blue-500' : 'bg-gray-100'}`}
                                            title={hourFilled(d.intervals, h) ? 'Rain' : 'No rain'}
                                        />
                                    </td>
                                ))}
                            </tr>
                        ))}
                        {days.length === 0 && (
                            <tr>
                                <td colSpan={25} className="px-3 py-4 text-center text-gray-400">No data.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function TextSection({ data }) {
    if (!data) return <p className="text-sm text-gray-500">No data.</p>;

    if (Array.isArray(data.tree)) {
        return (
            <div>
                <ul>
                    {data.tree.map((n, i) => (
                        <TreeNode key={i} node={n} />
                    ))}
                </ul>
                {data.note && <p className="mt-3 text-sm text-gray-500">{data.note}</p>}
            </div>
        );
    }

    if (data.series) {
        return <SeriesTable series={data.series} />;
    }

    if (data.summary && data.days) {
        return <WeatherLog data={data} />;
    }

    // 2.5 (Gantt) merged data is `{ rows: [], note }` — the activity table
    // itself is edited via GanttAssetsPanel (see MonthlyReportEditor's
    // 'gantt' case), so this host only surfaces the note here.
    if (data.note) {
        return <p className="text-sm text-gray-500">{data.note}</p>;
    }

    return <p className="text-sm text-gray-500">No data.</p>;
}
