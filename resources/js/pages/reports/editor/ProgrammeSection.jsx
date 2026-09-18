import { Link } from 'react-router-dom';

const MAX_ROWS = 100;

// Read-only render of the imported work programme rows for section 2.5.
// `data` is the section's merged data: `{ rows: [{no,task,level,summary,
// duration,start,finish,actual,plan}], version?: {label,status_date}, note? }`.
// Row overrides are not supported for 2.5 — this is intentionally read-only.
export default function ProgrammeSection({ data, projectId }) {
    if (!data) return <p className="text-sm text-gray-500">No data.</p>;

    const rows = Array.isArray(data.rows) ? data.rows : [];
    const version = data.version;

    return (
        <div className="space-y-3">
            {version ? (
                <p className="text-sm text-gray-600">
                    Programme: <span className="font-medium text-gray-900">{version.label}</span>
                    {version.status_date && ` (status date ${version.status_date})`}
                </p>
            ) : data.note ? (
                <p className="text-sm text-gray-500">{data.note}</p>
            ) : null}

            {rows.length > 0 && (
                <>
                    <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr className="text-left text-xs font-semibold uppercase text-gray-500">
                                    <th className="px-3 py-2">No.</th>
                                    <th className="px-3 py-2">Task</th>
                                    <th className="px-3 py-2">Duration</th>
                                    <th className="px-3 py-2">Start</th>
                                    <th className="px-3 py-2">Finish</th>
                                    <th className="px-3 py-2">Actual %</th>
                                    <th className="px-3 py-2">Plan %</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {rows.slice(0, MAX_ROWS).map((row, i) => (
                                    <tr key={i}>
                                        <td className="px-3 py-1.5 text-gray-400">{row.no ?? ''}</td>
                                        <td
                                            className={`px-3 py-1.5 ${row.summary ? 'font-semibold text-gray-900' : 'text-gray-700'}`}
                                            style={{ paddingLeft: 12 + Math.max(0, (row.level || 1) - 1) * 12 }}
                                        >
                                            {row.task}
                                        </td>
                                        <td className="px-3 py-1.5 text-gray-600">{row.duration ?? '-'}</td>
                                        <td className="px-3 py-1.5 text-gray-600">{row.start ?? '-'}</td>
                                        <td className="px-3 py-1.5 text-gray-600">{row.finish ?? '-'}</td>
                                        <td className="px-3 py-1.5 text-gray-600">{row.actual ?? '-'}</td>
                                        <td className="px-3 py-1.5 text-gray-600">{row.plan ?? '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {rows.length > MAX_ROWS && (
                        <p className="text-xs text-gray-500">… and {rows.length - MAX_ROWS} more rows — the full table is in the export.</p>
                    )}
                </>
            )}

            {projectId && (
                <Link to={`/projects/${projectId}`} className="inline-block text-sm text-primary-600 hover:underline">
                    Manage the work programme in Report Data
                </Link>
            )}
        </div>
    );
}
