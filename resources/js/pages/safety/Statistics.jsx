import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import safetyService from '@/services/safetyService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineArrowLeft, HiOutlinePlus, HiOutlineTrash } from 'react-icons/hi';

/**
 * Safety Statistics — LTIFR & severity rate (Ciri 25).
 *
 * The two rates come straight from the server (per million man-hours); this
 * page only presents them and lets a manager maintain the man-hours figures
 * that feed the denominator. A null rate is shown as "—" — undefined, not zero
 * — when no man-hours have been recorded for the period.
 */

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function Kpi({ label, value, hint, accent = 'text-gray-900' }) {
    return (
        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <p className="text-xs font-medium uppercase tracking-wide text-gray-400">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${accent}`}>{value}</p>
            {hint && <p className="mt-0.5 text-xs text-gray-400">{hint}</p>}
        </div>
    );
}

export default function Statistics() {
    const { can } = useAuth();
    const canManage = can('safety.manage');
    const thisYear = new Date().getFullYear();
    const [year, setYear] = useState(thisYear);
    const [projectId, setProjectId] = useState('');
    const [projects, setProjects] = useState([]);
    const [stats, setStats] = useState(null);
    const [manHours, setManHours] = useState([]);
    const [loading, setLoading] = useState(true);
    const [form, setForm] = useState({ project_id: '', month: 1, man_hours: '' });

    const fetchAll = useCallback(async () => {
        setLoading(true);
        try {
            const params = { year };
            if (projectId) params.project_id = projectId;
            const [s, mh] = await Promise.all([
                safetyService.getStatistics(params),
                safetyService.listManHours(params),
            ]);
            setStats(s.data || null);
            setManHours(mh.data || []);
        } catch {
            setStats(null);
        } finally {
            setLoading(false);
        }
    }, [year, projectId]);

    useEffect(() => { fetchAll(); }, [fetchAll]);
    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
    }, []);

    const recordManHours = async (e) => {
        e.preventDefault();
        try {
            await safetyService.recordManHours({
                year,
                month: Number(form.month),
                man_hours: Number(form.man_hours),
                project_id: form.project_id || null,
            });
            toast.success('Man-hours recorded');
            setForm({ project_id: '', month: 1, man_hours: '' });
            fetchAll();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to record');
        }
    };

    const removeManHours = async (id) => {
        try {
            await safetyService.deleteManHours(id);
            fetchAll();
        } catch {
            toast.error('Failed to remove');
        }
    };

    const fmt = (v) => (v === null || v === undefined ? '—' : v);
    const inputCls = 'rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

    return (
        <div>
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link to="/safety" className="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                        <HiOutlineArrowLeft className="h-4 w-4" /> Back to Safety
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900">Safety Statistics</h1>
                    <p className="text-sm text-gray-500">LTIFR &amp; severity rate, per million man-hours</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <select value={year} onChange={(e) => setYear(Number(e.target.value))} className={inputCls}>
                        {[thisYear + 1, thisYear, thisYear - 1, thisYear - 2].map((y) => <option key={y} value={y}>{y}</option>)}
                    </select>
                    <select value={projectId} onChange={(e) => setProjectId(e.target.value)} className={inputCls}>
                        <option value="">All projects</option>
                        {projects.map((pr) => <option key={pr.id} value={pr.id}>{pr.name}</option>)}
                    </select>
                </div>
            </div>

            {loading ? <LoadingSpinner /> : !stats ? (
                <p className="py-12 text-center text-sm text-gray-400">No statistics available.</p>
            ) : (
                <>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <Kpi label="LTIFR" value={fmt(stats.ltifr)} hint="lost-time injuries / million hrs" accent="text-red-600" />
                        <Kpi label="Severity rate" value={fmt(stats.severity_rate)} hint="days lost / million hrs" accent="text-orange-600" />
                        <Kpi label="Man-hours" value={Number(stats.man_hours).toLocaleString()} hint={`${year}${projectId ? ' · project' : ' · all projects'}`} />
                        <Kpi label="Lost-time injuries" value={stats.lost_time_injuries} hint={`${stats.days_lost} days lost`} />
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <Kpi label="Total incidents" value={stats.total_incidents} />
                        <Kpi label="Injuries" value={stats.total_injuries} />
                        <Kpi label="Near misses" value={stats.near_misses} />
                        <Kpi label="Days lost" value={stats.days_lost} />
                    </div>

                    <div className="mt-6 grid gap-4 lg:grid-cols-2">
                        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                            <h3 className="mb-3 text-sm font-semibold text-gray-700">By severity</h3>
                            {Object.keys(stats.by_severity || {}).length === 0 ? <p className="text-sm text-gray-400">No incidents.</p> : (
                                <ul className="space-y-1 text-sm">
                                    {Object.entries(stats.by_severity).map(([k, v]) => (
                                        <li key={k} className="flex justify-between capitalize"><span className="text-gray-600">{k}</span><span className="font-medium">{v}</span></li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                            <h3 className="mb-3 text-sm font-semibold text-gray-700">By type</h3>
                            {Object.keys(stats.by_type || {}).length === 0 ? <p className="text-sm text-gray-400">No incidents.</p> : (
                                <ul className="space-y-1 text-sm">
                                    {Object.entries(stats.by_type).map(([k, v]) => (
                                        <li key={k} className="flex justify-between capitalize"><span className="text-gray-600">{String(k).replace(/_/g, ' ')}</span><span className="font-medium">{v}</span></li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>

                    <div className="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <h3 className="mb-3 text-sm font-semibold text-gray-700">Man-hours ({year})</h3>
                        {canManage && (
                            <form onSubmit={recordManHours} className="mb-4 flex flex-wrap items-end gap-2">
                                <select value={form.project_id} onChange={(e) => setForm((f) => ({ ...f, project_id: e.target.value }))} className={inputCls}>
                                    <option value="">All / company</option>
                                    {projects.map((pr) => <option key={pr.id} value={pr.id}>{pr.name}</option>)}
                                </select>
                                <select value={form.month} onChange={(e) => setForm((f) => ({ ...f, month: e.target.value }))} className={inputCls}>
                                    {MONTHS.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
                                </select>
                                <input type="number" min="0" required placeholder="Man-hours" value={form.man_hours} onChange={(e) => setForm((f) => ({ ...f, man_hours: e.target.value }))} className={inputCls} />
                                <button type="submit" className="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                    <HiOutlinePlus className="h-4 w-4" /> Record
                                </button>
                            </form>
                        )}
                        {manHours.length === 0 ? <p className="text-sm text-gray-400">No man-hours recorded for {year}.</p> : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                                        <th className="py-2 text-left font-medium">Month</th>
                                        <th className="py-2 text-left font-medium">Project</th>
                                        <th className="py-2 text-right font-medium">Man-hours</th>
                                        {canManage && <th className="py-2" />}
                                    </tr>
                                </thead>
                                <tbody>
                                    {manHours.map((r) => (
                                        <tr key={r.id} className="border-b border-gray-100 last:border-0">
                                            <td className="py-2">{MONTHS[(r.month || 1) - 1]}</td>
                                            <td className="py-2 text-gray-600">{r.project?.name || 'Company-wide'}</td>
                                            <td className="py-2 text-right font-medium">{Number(r.man_hours).toLocaleString()}</td>
                                            {canManage && (
                                                <td className="py-2 text-right">
                                                    <button onClick={() => removeManHours(r.id)} className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineTrash className="h-4 w-4" /></button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}
