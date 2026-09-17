import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { useConfirm } from '@/context/ConfirmContext';
import monthlyReportService from '@/services/monthlyReportService';
import projectService from '@/services/projectService';
import reportDataService from '@/services/reportDataService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import useDragScroll from '@/hooks/useDragScroll';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineDocumentReport,
    HiOutlineTrash,
    HiOutlineDownload,
    HiOutlineX,
    HiOutlineEye,
} from 'react-icons/hi';

const statusColors = {
    draft: 'bg-gray-100 text-gray-600',
    final: 'bg-green-100 text-green-700',
};

const statuses = ['draft', 'final'];

function emptyForm() {
    return {
        project_id: '',
        period_end: '',
        period_start: '',
        report_no: '',
        month_label: '',
        copy_from_report_id: '',
    };
}

export default function MonthlyReports() {
    const { can } = useAuth();
    const navigate = useNavigate();
    const canManage = can('reports.manage');
    const confirm = useConfirm();

    const [reports, setReports] = useState([]);
    const [loading, setLoading] = useState(true);
    const dragScrollRef = useDragScroll();
    const [statusFilter, setStatusFilter] = useState('');
    const [projectFilter, setProjectFilter] = useState('');
    const [pagination, setPagination] = useState({});
    const [projects, setProjects] = useState([]);

    const [showForm, setShowForm] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(emptyForm());
    const [previousReports, setPreviousReports] = useState([]);
    const [suggesting, setSuggesting] = useState(false);

    const fetchReports = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (statusFilter) params.status = statusFilter;
            if (projectFilter) params.project_id = projectFilter;
            const res = await monthlyReportService.list(params);
            setReports(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setReports([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchReports();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [statusFilter, projectFilter]);

    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
    }, []);

    const todayIso = () => new Date().toISOString().slice(0, 10);

    const openCreate = () => {
        setForm(emptyForm());
        setPreviousReports([]);
        setShowForm(true);
    };

    // Loads suggested period + next report number + prior reports for the
    // selected project, using today as the default period end.
    const loadProjectDefaults = async (projectId, periodEnd) => {
        if (!projectId) return;
        setSuggesting(true);
        try {
            const [suggestRes, listRes] = await Promise.all([
                reportDataService.suggestPeriod(projectId, periodEnd || todayIso()),
                monthlyReportService.listForProject(projectId, { per_page: 100 }),
            ]);
            const suggestion = suggestRes?.data || {};
            const priorReports = listRes?.data?.data || [];
            const maxReportNo = priorReports.reduce((max, r) => Math.max(max, r.report_no || 0), 0);

            setPreviousReports(priorReports);
            setForm((p) => ({
                ...p,
                project_id: projectId,
                period_start: suggestion.period_start || p.period_start,
                period_end: suggestion.period_end || periodEnd || p.period_end,
                report_no: String(maxReportNo + 1),
                month_label: monthLabelFromDate(suggestion.period_end || periodEnd),
                copy_from_report_id: '',
            }));
        } catch {
            toast.error('Failed to load report defaults for this project');
        } finally {
            setSuggesting(false);
        }
    };

    const monthLabelFromDate = (dateStr) => {
        if (!dateStr) return '';
        const d = new Date(`${dateStr}T00:00:00`);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    };

    const handleProjectChange = (projectId) => {
        setForm((p) => ({ ...emptyForm(), project_id: projectId }));
        if (projectId) loadProjectDefaults(projectId, todayIso());
    };

    const handlePeriodEndChange = (periodEnd) => {
        setForm((p) => ({ ...p, period_end: periodEnd }));
        if (form.project_id) loadProjectDefaults(form.project_id, periodEnd);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.project_id) {
            toast.error('Please select a project');
            return;
        }
        setSaving(true);
        try {
            const payload = {};
            if (form.report_no) payload.report_no = Number(form.report_no);
            if (form.period_start) payload.period_start = form.period_start;
            if (form.period_end) payload.period_end = form.period_end;
            if (form.month_label) payload.month_label = form.month_label;
            if (form.copy_from_report_id) payload.copy_from_report_id = Number(form.copy_from_report_id);

            const res = await monthlyReportService.create(form.project_id, payload);
            toast.success('Monthly report created');
            setShowForm(false);
            const newId = res.data?.id;
            if (newId) {
                navigate(`/projects/monthly-reports/${newId}`);
            } else {
                fetchReports();
            }
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to create monthly report');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id) => {
        if (!(await confirm({ message: 'Delete this monthly report? This cannot be undone.' }))) return;
        try {
            await monthlyReportService.remove(id);
            toast.success('Monthly report deleted');
            fetchReports();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete monthly report');
        }
    };

    const canCopyPrevious = useMemo(() => previousReports.length > 0, [previousReports]);

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Monthly Reports</h1>
                    <p className="text-sm text-gray-500">Monthly progress reports across all projects</p>
                </div>
                {canManage && (
                    <button
                        onClick={openCreate}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        New Report
                    </button>
                )}
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <select
                    value={projectFilter}
                    onChange={(e) => setProjectFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Projects</option>
                    {projects.map((p) => (
                        <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                </select>
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Statuses</option>
                    {statuses.map((s) => (
                        <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                    ))}
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : reports.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineDocumentReport className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No monthly reports found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div ref={dragScrollRef} className="overflow-x-auto cursor-grab active:cursor-grabbing">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Report No</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Period</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Generated</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {reports.map((r) => (
                                    <tr key={r.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.project?.name || '-'}</td>
                                        <td className="px-4 py-3">
                                            <button
                                                onClick={() => navigate(`/projects/monthly-reports/${r.id}`)}
                                                className="text-sm font-medium text-primary-700 hover:underline"
                                            >
                                                {r.report_no ?? '-'}
                                            </button>
                                            {r.title && <p className="text-xs text-gray-500">{r.title}</p>}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                            {r.period?.period_start || r.period?.period_end
                                                ? `${formatDate(r.period?.period_start)} - ${formatDate(r.period?.period_end)}`
                                                : (r.month_label || '-')}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[r.status] || 'bg-gray-100 text-gray-600'}`}>
                                                {r.status}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{formatDate(r.generated_at)}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    onClick={() => navigate(`/projects/monthly-reports/${r.id}`)}
                                                    className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                    title="Open"
                                                >
                                                    <HiOutlineEye className="h-4 w-4" />
                                                </button>
                                                <a
                                                    href={monthlyReportService.getPdfUrl(r.id)}
                                                    target="_blank"
                                                    rel="noopener"
                                                    className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                    title="Download PDF"
                                                >
                                                    <HiOutlineDownload className="h-4 w-4" />
                                                </a>
                                                {canManage && r.status === 'draft' && (
                                                    <button
                                                        onClick={() => handleDelete(r.id)}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                        title="Delete"
                                                    >
                                                        <HiOutlineTrash className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {pagination.last_page > 1 && (
                        <div className="flex items-center justify-between border-t px-4 py-3">
                            <p className="text-sm text-gray-500">
                                Showing {pagination.from}-{pagination.to} of {pagination.total}
                            </p>
                            <div className="flex gap-1">
                                {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                                    <button
                                        key={page}
                                        onClick={() => fetchReports(page)}
                                        className={`rounded px-3 py-1 text-sm ${
                                            page === pagination.current_page
                                                ? 'bg-primary-600 text-white'
                                                : 'text-gray-600 hover:bg-gray-100'
                                        }`}
                                    >
                                        {page}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* New Report Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowForm(false)}>
                    <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">New Monthly Report</h3>
                            <button onClick={() => setShowForm(false)} className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                <HiOutlineX className="h-5 w-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Project *</label>
                                <select
                                    value={form.project_id}
                                    onChange={(e) => handleProjectChange(e.target.value)}
                                    required
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                >
                                    <option value="">Select Project</option>
                                    {projects.map((p) => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Period End</label>
                                    <input
                                        type="date"
                                        value={form.period_end}
                                        onChange={(e) => handlePeriodEndChange(e.target.value)}
                                        disabled={!form.project_id}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-100"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Report No</label>
                                    <input
                                        type="number"
                                        min="1"
                                        value={form.report_no}
                                        onChange={(e) => setForm((p) => ({ ...p, report_no: e.target.value }))}
                                        disabled={!form.project_id}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-100"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Month Label</label>
                                <input
                                    type="text"
                                    value={form.month_label}
                                    onChange={(e) => setForm((p) => ({ ...p, month_label: e.target.value }))}
                                    disabled={!form.project_id}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-100"
                                />
                            </div>
                            {canCopyPrevious && (
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input
                                        type="checkbox"
                                        checked={!!form.copy_from_report_id}
                                        onChange={(e) => {
                                            const latest = previousReports[0];
                                            setForm((p) => ({
                                                ...p,
                                                copy_from_report_id: e.target.checked && latest ? String(latest.id) : '',
                                            }));
                                        }}
                                        className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    />
                                    Copy static sections from previous report
                                </label>
                            )}
                            {suggesting && <p className="text-xs text-gray-400">Loading suggested period...</p>}
                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowForm(false)}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={saving || !form.project_id}
                                    className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                                >
                                    {saving ? 'Creating...' : 'Create Report'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
