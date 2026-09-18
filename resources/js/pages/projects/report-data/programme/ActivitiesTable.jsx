import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import reportDataService from '@/services/reportDataService';
import LoadingSpinner from '@/components/LoadingSpinner';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

// Read-only, paginated (100/page) activities view for one programme version.
// The search box filters the currently loaded page only (client-side), to
// keep the interaction simple — it does not query the full activity set.
export default function ActivitiesTable({ projectId, version }) {
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [rows, setRows] = useState([]);
    const [search, setSearch] = useState('');

    useEffect(() => {
        setLoading(true);
        setLoadError(false);
        reportDataService.listProgrammeActivities(projectId, version.id, { page, per_page: 100 })
            .then((res) => {
                const d = res.data;
                setRows(d.data || []);
                setLastPage(d.last_page || 1);
                setTotal(d.total ?? (d.data || []).length);
            })
            .catch(() => { setLoadError(true); toast.error('Failed to load activities'); })
            .finally(() => setLoading(false));
    }, [projectId, version.id, page]);

    const filtered = search.trim()
        ? rows.filter((r) => (r.name || '').toLowerCase().includes(search.trim().toLowerCase()))
        : rows;

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900">{version.label}</h3>
                    <p className="text-xs text-gray-500">
                        {version.status_date ? `Status date ${version.status_date} · ` : ''}{total} activities
                    </p>
                </div>
                <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search this page…"
                    className={`${input} max-w-xs`}
                />
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : loadError ? (
                <div className="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">Failed to load activities.</div>
            ) : (
                <div className="max-h-[60vh] overflow-auto rounded-lg ring-1 ring-gray-200">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead className="sticky top-0 z-10 bg-gray-50">
                            <tr className="text-left text-xs font-semibold uppercase text-gray-500">
                                <th className="px-3 py-2">#</th>
                                <th className="px-3 py-2">Task</th>
                                <th className="px-3 py-2">Duration</th>
                                <th className="px-3 py-2">Start</th>
                                <th className="px-3 py-2">Finish</th>
                                <th className="px-3 py-2">Actual %</th>
                                <th className="px-3 py-2">Plan %</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {filtered.map((r) => (
                                <tr key={r.seq}>
                                    <td className="px-3 py-1.5 text-gray-400">{r.seq}</td>
                                    <td className={`px-3 py-1.5 ${r.is_summary ? 'font-semibold text-gray-900' : 'text-gray-700'}`} style={{ paddingLeft: 12 + Math.max(0, (r.outline_level || 1) - 1) * 16 }}>
                                        {r.name}
                                    </td>
                                    <td className="px-3 py-1.5 text-gray-600">{r.duration_days ?? '-'}</td>
                                    <td className="px-3 py-1.5 text-gray-600">{r.start || '-'}</td>
                                    <td className="px-3 py-1.5 text-gray-600">{r.finish || '-'}</td>
                                    <td className="px-3 py-1.5 text-gray-600">{r.actual_pct ?? '-'}</td>
                                    <td className="px-3 py-1.5 text-gray-600">{r.plan_pct ?? '-'}</td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr><td colSpan={7} className="px-3 py-4 text-center text-gray-500">No activities on this page match.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            {lastPage > 1 && (
                <div className="flex items-center justify-between text-sm text-gray-600">
                    <button type="button" disabled={page <= 1} onClick={() => setPage((p) => p - 1)} className="rounded-lg border border-gray-300 px-3 py-1.5 font-medium hover:bg-gray-50 disabled:opacity-40">Previous</button>
                    <span>Page {page} of {lastPage}</span>
                    <button type="button" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)} className="rounded-lg border border-gray-300 px-3 py-1.5 font-medium hover:bg-gray-50 disabled:opacity-40">Next</button>
                </div>
            )}
        </div>
    );
}
