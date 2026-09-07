import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import safetyService from '@/services/safetyService';
import projectService from '@/services/projectService';
import projectSiteService from '@/services/projectSiteService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlinePlus, HiOutlineArrowLeft, HiOutlinePencil } from 'react-icons/hi';

/**
 * Permit To Work (PTW) — Ciri 25 Safety.
 *
 * The status shown is the server's `effective_status`, which folds expiry in:
 * an approved permit past its window shows as "expired" without anything having
 * to update the row. Which actions appear depends on that status and the
 * viewer's permissions — the server enforces the same rules, this only hides
 * buttons that would fail.
 */

const STATUS_COLORS = {
    draft: 'bg-gray-100 text-gray-600',
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    expired: 'bg-orange-100 text-orange-700',
    closed: 'bg-slate-200 text-slate-600',
};

const TYPES = [
    ['hot_work', 'Hot work'],
    ['confined_space', 'Confined space'],
    ['working_at_height', 'Working at height'],
    ['electrical', 'Electrical'],
    ['excavation', 'Excavation'],
    ['lifting', 'Lifting'],
    ['general', 'General'],
];

const emptyForm = () => ({ id: null, title: '', type: 'general', project_id: '', site_id: '', location: '', description: '', precautions: '', valid_from: '', valid_to: '' });

export default function Permits() {
    const { can } = useAuth();
    const canCreate = can('safety.create');
    const canManage = can('safety.manage');
    const [list, setList] = useState([]);
    const [projects, setProjects] = useState([]);
    const [sites, setSites] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(emptyForm());

    const fetchList = useCallback(async () => {
        setLoading(true);
        try {
            const res = await safetyService.listPermits({ per_page: 100 });
            setList(res.data?.data || []);
        } catch {
            setList([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { fetchList(); }, [fetchList]);
    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
    }, []);
    useEffect(() => {
        if (!form.project_id) { setSites([]); return; }
        projectSiteService.list(form.project_id, true).then((r) => setSites(r.data || [])).catch(() => setSites([]));
    }, [form.project_id]);

    const openCreate = () => { setForm(emptyForm()); setShowForm(true); };
    const openEdit = (row) => {
        setForm({
            id: row.id, title: row.title || '', type: row.type || 'general', project_id: row.project_id || '', site_id: row.site_id || '',
            location: row.location || '', description: row.description || '', precautions: row.precautions || '',
            valid_from: (row.valid_from || '').slice(0, 16), valid_to: (row.valid_to || '').slice(0, 16),
        });
        setShowForm(true);
    };

    const submitForm = async (e, submitForApproval = false) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = { ...form, project_id: form.project_id || null, site_id: form.site_id || null, submit: submitForApproval };
            if (form.id) {
                await safetyService.updatePermit(form.id, payload);
                toast.success('Permit updated');
            } else {
                await safetyService.createPermit(payload);
                toast.success(submitForApproval ? 'Permit submitted' : 'Permit saved as draft');
            }
            setShowForm(false);
            fetchList();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save');
        } finally {
            setSaving(false);
        }
    };

    const act = async (fn, id, ...args) => {
        try {
            await fn(id, ...args);
            fetchList();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Action failed');
        }
    };

    const inputCls = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

    return (
        <div>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link to="/safety" className="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                        <HiOutlineArrowLeft className="h-4 w-4" /> Back to Safety
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900">Permit To Work</h1>
                    <p className="text-sm text-gray-500">Authorise high-risk work for a bounded window, with approval</p>
                </div>
                {canCreate && (
                    <button onClick={openCreate} className="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700">
                        <HiOutlinePlus className="h-4 w-4" /> New Permit
                    </button>
                )}
            </div>

            {loading ? <LoadingSpinner /> : (
                <div className="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    {list.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-gray-400">No permits yet.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                                    <th className="px-4 py-3 text-left font-medium">Permit</th>
                                    <th className="px-4 py-3 text-left font-medium">Type</th>
                                    <th className="px-4 py-3 text-left font-medium">Window</th>
                                    <th className="px-4 py-3 text-left font-medium">Status</th>
                                    <th className="px-4 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((p) => {
                                    const st = p.effective_status || p.status;
                                    const typeLabel = (TYPES.find((t) => t[0] === p.type) || [null, p.type])[1];
                                    return (
                                        <tr key={p.id} className="border-b border-gray-100 last:border-0 align-top">
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-gray-900">{p.title}</div>
                                                <div className="text-xs text-gray-400">{p.permit_no}{p.project?.name ? ` · ${p.project.name}` : ''}</div>
                                            </td>
                                            <td className="px-4 py-3 capitalize text-gray-600">{typeLabel}</td>
                                            <td className="px-4 py-3 text-xs text-gray-500">
                                                {(p.valid_from || '').replace('T', ' ').slice(0, 16)}<br />→ {(p.valid_to || '').replace('T', ' ').slice(0, 16)}
                                            </td>
                                            <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STATUS_COLORS[st] || 'bg-gray-100 text-gray-600'}`}>{st}</span></td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap justify-end gap-1.5">
                                                    {canCreate && (p.status === 'draft' || p.status === 'pending') && (
                                                        <button onClick={() => openEdit(p)} className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Edit"><HiOutlinePencil className="h-4 w-4" /></button>
                                                    )}
                                                    {canCreate && p.status === 'draft' && (
                                                        <button onClick={() => act(safetyService.submitPermit, p.id)} className="rounded-md bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 hover:bg-amber-100">Submit</button>
                                                    )}
                                                    {canManage && p.status === 'pending' && (
                                                        <>
                                                            <button onClick={() => act(safetyService.approvePermit, p.id)} className="rounded-md bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-100">Approve</button>
                                                            <button onClick={() => { const r = prompt('Reason for rejection (optional):'); if (r !== null) act(safetyService.rejectPermit, p.id, r); }} className="rounded-md bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100">Reject</button>
                                                        </>
                                                    )}
                                                    {canManage && p.status === 'approved' && (
                                                        <button onClick={() => act(safetyService.closePermit, p.id)} className="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">Close</button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </div>
            )}

            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowForm(false)}>
                    <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">{form.id ? 'Edit Permit' : 'New Permit'}</h3>
                        <form onSubmit={(e) => submitForm(e, false)} className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div><label className="mb-1 block text-sm font-medium text-gray-700">Title *</label><input type="text" required value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} className={inputCls} /></div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Type</label>
                                    <select value={form.type} onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))} className={inputCls}>
                                        {TYPES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Project</label>
                                    <select value={form.project_id} onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value, site_id: '' }))} className={inputCls}>
                                        <option value="">None</option>
                                        {projects.map((pr) => <option key={pr.id} value={pr.id}>{pr.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Site</label>
                                    <select value={form.site_id} onChange={(e) => setForm((p) => ({ ...p, site_id: e.target.value }))} className={inputCls} disabled={!form.project_id || sites.length === 0}>
                                        <option value="">{form.project_id ? (sites.length ? 'No specific site' : 'No sites defined') : 'Select a project first'}</option>
                                        {sites.map((st) => <option key={st.id} value={st.id}>{st.name}</option>)}
                                    </select>
                                </div>
                                <div><label className="mb-1 block text-sm font-medium text-gray-700">Location</label><input type="text" value={form.location} onChange={(e) => setForm((p) => ({ ...p, location: e.target.value }))} className={inputCls} /></div>
                                <div><label className="mb-1 block text-sm font-medium text-gray-700">Valid from *</label><input type="datetime-local" required value={form.valid_from} onChange={(e) => setForm((p) => ({ ...p, valid_from: e.target.value }))} className={inputCls} /></div>
                                <div><label className="mb-1 block text-sm font-medium text-gray-700">Valid to *</label><input type="datetime-local" required value={form.valid_to} onChange={(e) => setForm((p) => ({ ...p, valid_to: e.target.value }))} className={inputCls} /></div>
                            </div>
                            <div><label className="mb-1 block text-sm font-medium text-gray-700">Description of work</label><textarea rows={2} value={form.description} onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))} className={inputCls} /></div>
                            <div><label className="mb-1 block text-sm font-medium text-gray-700">Precautions / controls</label><textarea rows={2} value={form.precautions} onChange={(e) => setForm((p) => ({ ...p, precautions: e.target.value }))} className={inputCls} /></div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">{saving ? 'Saving…' : 'Save draft'}</button>
                                {!form.id && (
                                    <button type="button" disabled={saving} onClick={(e) => submitForm(e, true)} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">Save &amp; submit</button>
                                )}
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
