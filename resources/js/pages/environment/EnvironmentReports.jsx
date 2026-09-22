import { useState, useEffect, useCallback } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { useConfirm } from '@/context/ConfirmContext';
import environmentReportService from '@/services/environmentReportService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import EnvironmentProjectSettingsDialog from './EnvironmentProjectSettingsDialog';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus, HiOutlineSearch, HiOutlineDocumentReport, HiOutlineEye, HiOutlinePencil,
    HiOutlineTrash, HiOutlineDownload, HiOutlineCog,
} from 'react-icons/hi';

const STORAGE_KEY = 'environment.project';

const statusColors = {
    draft: 'bg-gray-100 text-gray-600',
    final: 'bg-green-100 text-green-700',
};

function previousMonth15() {
    const d = new Date();
    d.setDate(1);
    d.setMonth(d.getMonth() - 1);
    d.setDate(15);
    return d.toISOString().split('T')[0];
}
function thisMonth15() {
    const d = new Date();
    d.setDate(15);
    return d.toISOString().split('T')[0];
}

export default function EnvironmentReports() {
    const { can } = useAuth();
    const canCreate = can('environmental.create');
    const canManage = can('environmental.manage');
    const confirm = useConfirm();
    const navigate = useNavigate();

    const [reports, setReports] = useState([]);
    const [projects, setProjects] = useState([]);
    const [loading, setLoading] = useState(true);

    const [projectFilter, setProjectFilter] = useState(() => localStorage.getItem(STORAGE_KEY) || '');
    const [statusFilter, setStatusFilter] = useState('');
    const [search, setSearch] = useState('');

    const [showSettings, setShowSettings] = useState(false);
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState({ project_id: '', period_start: previousMonth15(), period_end: thisMonth15(), title: '' });
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
    }, []);

    useEffect(() => {
        if (projectFilter) localStorage.setItem(STORAGE_KEY, projectFilter);
        else localStorage.removeItem(STORAGE_KEY);
    }, [projectFilter]);

    const fetchReports = useCallback(async () => {
        setLoading(true);
        try {
            const params = {};
            if (projectFilter) params.project_id = projectFilter;
            if (statusFilter) params.status = statusFilter;
            if (search) params.search = search;
            const res = await environmentReportService.list(params);
            setReports(res.data?.data || res.data || []);
        } catch {
            setReports([]);
        } finally {
            setLoading(false);
        }
    }, [projectFilter, statusFilter, search]);

    useEffect(() => {
        const t = setTimeout(() => fetchReports(), 300);
        return () => clearTimeout(t);
    }, [fetchReports]);

    const openCreate = () => {
        setForm({ project_id: projectFilter || '', period_start: previousMonth15(), period_end: thisMonth15(), title: '' });
        setErrors({});
        setShowCreate(true);
    };

    const submitCreate = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const payload = { ...form };
            if (!payload.title) delete payload.title;
            const res = await environmentReportService.create(payload);
            toast.success('Report created');
            setShowCreate(false);
            navigate(`/environment/reports/${res.data.id}/edit`);
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors || {});
                toast.error('Please fix the highlighted fields');
            } else {
                toast.error(err.response?.data?.message || 'Failed to create report');
            }
        } finally {
            setSaving(false);
        }
    };

    const remove = async (report) => {
        if (!(await confirm({ title: 'Delete report?', message: `Delete report "${report.title || `No. ${report.report_no}`}"?` }))) return;
        try {
            await environmentReportService.remove(report.id);
            toast.success('Report deleted');
            fetchReports();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete report');
        }
    };

    const downloadPdf = async (report) => {
        try { await environmentReportService.downloadPdf(report.id); }
        catch { toast.error('Failed to download PDF'); }
    };
    const downloadDocx = async (report) => {
        try { await environmentReportService.downloadDocx(report.id); }
        catch { toast.error('Failed to download DOCX'); }
    };

    const preparedBy = (report) => {
        const prepared = (report.signatories || []).find((s) => s.slot === 'prepared');
        return prepared?.name || report.creator?.name || '-';
    };

    const fieldClass = (name) =>
        `w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1 ${errors[name] ? 'border-red-300 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500'}`;

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Monthly Environment Reports</h1>
                    <p className="text-sm text-gray-500">Compile and manage monthly environmental monitoring reports</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button
                        onClick={() => projectFilter && setShowSettings(true)}
                        disabled={!projectFilter}
                        title={!projectFilter ? 'Select a project first' : ''}
                        className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <HiOutlineCog className="h-5 w-5" /> Project Settings
                    </button>
                    {canCreate && (
                        <button onClick={openCreate} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                            <HiOutlinePlus className="h-5 w-5" /> New Report
                        </button>
                    )}
                </div>
            </div>

            {/* Toolbar */}
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
                <select value={projectFilter} onChange={(e) => setProjectFilter(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 lg:min-w-[16rem]">
                    <option value="">All Projects</option>
                    {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
                <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="final">Final</option>
                </select>
                <div className="relative max-w-xs flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input type="text" placeholder="Search title..." value={search} onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                </div>
            </div>

            {loading ? <LoadingSpinner /> : reports.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineDocumentReport className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No monthly environment reports found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">No.</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Period</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Prepared by</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Updated</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {reports.map((report) => (
                                    <tr key={report.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">{report.report_no}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{report.title || '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{formatDate(report.period_start)} – {formatDate(report.period_end)}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{report.project?.name || '-'}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[report.status]}`}>{report.status}</span></td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{preparedBy(report)}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{formatDate(report.updated_at)}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link to={`/environment/reports/${report.id}`} title="View" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><HiOutlineEye className="h-4 w-4" /></Link>
                                                {canManage && report.status !== 'final' && (
                                                    <Link to={`/environment/reports/${report.id}/edit`} title="Edit" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><HiOutlinePencil className="h-4 w-4" /></Link>
                                                )}
                                                <button onClick={() => downloadPdf(report)} title="Download PDF" className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600"><HiOutlineDownload className="h-4 w-4" /></button>
                                                <button onClick={() => downloadDocx(report)} title="Download DOCX" className="rounded px-1.5 py-1.5 text-[10px] font-bold text-gray-400 hover:bg-primary-50 hover:text-primary-600">DOCX</button>
                                                {canManage && <button onClick={() => remove(report)} title="Delete" className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineTrash className="h-4 w-4" /></button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {showSettings && projectFilter && (
                <EnvironmentProjectSettingsDialog projectId={projectFilter} onClose={() => setShowSettings(false)} />
            )}

            {showCreate && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowCreate(false)}>
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">New Monthly Environment Report</h3>
                        <form onSubmit={submitCreate} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Project *</label>
                                <select value={form.project_id} onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))} required className={fieldClass('project_id')}>
                                    <option value="">Select project...</option>
                                    {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                </select>
                                {errors.project_id && <p className="mt-1 text-xs text-red-500">{errors.project_id[0]}</p>}
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Period Start *</label>
                                    <input type="date" value={form.period_start} onChange={(e) => setForm((p) => ({ ...p, period_start: e.target.value }))} required className={fieldClass('period_start')} />
                                    {errors.period_start && <p className="mt-1 text-xs text-red-500">{errors.period_start[0]}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Period End *</label>
                                    <input type="date" value={form.period_end} onChange={(e) => setForm((p) => ({ ...p, period_end: e.target.value }))} required className={fieldClass('period_end')} />
                                    {errors.period_end && <p className="mt-1 text-xs text-red-500">{errors.period_end[0]}</p>}
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Title</label>
                                <input type="text" value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} className={fieldClass('title')}
                                    placeholder="MONTHLY ENVIRONMENT REPORT NO.{next}" />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowCreate(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Creating...' : 'Create Report'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
