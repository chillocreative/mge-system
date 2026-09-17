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

function minutesToClock(min) {
    if (min === null || min === undefined) return '-';
    const h = Math.floor(min / 60).toString().padStart(2, '0');
    const m = Math.floor(min % 60).toString().padStart(2, '0');
    return `${h}:${m}`;
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
            <ul className="divide-y divide-gray-100 rounded-lg ring-1 ring-gray-200">
                {days.map((d, i) => (
                    <li key={i} className="flex items-center justify-between px-3 py-2 text-sm">
                        <span className="text-gray-700">{d.date}</span>
                        <span className="text-gray-500">
                            {d.intervals?.length
                                ? d.intervals.map(([s, e]) => `${minutesToClock(s)}–${minutesToClock(e)}`).join(', ')
                                : 'No rain'}
                        </span>
                    </li>
                ))}
            </ul>
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

    return <p className="text-sm text-gray-500">No data.</p>;
}
