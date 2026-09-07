import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import safetyService from '@/services/safetyService';
import projectService from '@/services/projectService';
import projectSiteService from '@/services/projectSiteService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlinePlus, HiOutlineArrowLeft, HiOutlineTrash, HiOutlinePencil } from 'react-icons/hi';

/**
 * HIRARC — Hazard Identification, Risk Assessment & Risk Control (Ciri 25).
 *
 * The risk rating (likelihood × severity) and its band are computed on the
 * server, so the live preview here is only guidance — what gets stored is the
 * server's calculation, never a value typed in.
 */

const LEVEL_COLORS = {
    low: 'bg-green-100 text-green-700',
    medium: 'bg-amber-100 text-amber-700',
    high: 'bg-orange-100 text-orange-700',
    critical: 'bg-red-100 text-red-700',
};

// Mirrors app/Services/Safety/RiskMatrix for a live preview only.
function previewLevel(l, s) {
    const r = Math.max(1, Math.min(5, l)) * Math.max(1, Math.min(5, s));
    const level = r >= 15 ? 'critical' : r >= 8 ? 'high' : r >= 4 ? 'medium' : 'low';
    return { rating: r, level };
}

const emptyItem = () => ({ hazard: '', risk: '', existing_control: '', likelihood: 1, severity: 1, recommended_control: '', pic: '', due_date: '' });
const emptyForm = () => ({ id: null, title: '', process: '', location: '', project_id: '', site_id: '', assessment_date: '', review_date: '', items: [emptyItem()] });

export default function Hirarc() {
    const { can } = useAuth();
    const canManage = can('safety.manage') || can('safety.create');
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
            const res = await safetyService.listHirarc({ per_page: 100 });
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

    const openEdit = async (row) => {
        try {
            const res = await safetyService.getHirarc(row.id);
            const a = res.data;
            setForm({
                id: a.id, title: a.title || '', process: a.process || '', location: a.location || '',
                project_id: a.project_id || '', site_id: a.site_id || '', assessment_date: a.assessment_date || '', review_date: a.review_date || '',
                items: (a.items || []).map((it) => ({
                    hazard: it.hazard || '', risk: it.risk || '', existing_control: it.existing_control || '',
                    likelihood: it.likelihood || 1, severity: it.severity || 1,
                    recommended_control: it.recommended_control || '', pic: it.pic || '', due_date: it.due_date || '',
                })),
            });
            if (!res.data.items?.length) setForm((p) => ({ ...p, items: [emptyItem()] }));
            setShowForm(true);
        } catch {
            toast.error('Failed to load assessment');
        }
    };

    const setItem = (i, field, value) => setForm((p) => ({ ...p, items: p.items.map((it, idx) => idx === i ? { ...it, [field]: value } : it) }));
    const addItem = () => setForm((p) => ({ ...p, items: [...p.items, emptyItem()] }));
    const removeItem = (i) => setForm((p) => ({ ...p, items: p.items.filter((_, idx) => idx !== i) }));

    const submit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = { ...form, project_id: form.project_id || null, site_id: form.site_id || null, items: form.items.filter((it) => it.hazard.trim()) };
            if (form.id) {
                await safetyService.updateHirarc(form.id, payload);
                toast.success('HIRARC updated');
            } else {
                await safetyService.createHirarc(payload);
                toast.success('HIRARC created');
            }
            setShowForm(false);
            fetchList();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save');
        } finally {
            setSaving(false);
        }
    };

    const archive = async (id) => {
        if (!confirm('Archive this HIRARC?')) return;
        try {
            await safetyService.archiveHirarc(id);
            toast.success('Archived');
            fetchList();
        } catch {
            toast.error('Failed to archive');
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
                    <h1 className="text-2xl font-bold text-gray-900">HIRARC</h1>
                    <p className="text-sm text-gray-500">Hazard Identification, Risk Assessment &amp; Risk Control</p>
                </div>
                {canManage && (
                    <button onClick={openCreate} className="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700">
                        <HiOutlinePlus className="h-4 w-4" /> New HIRARC
                    </button>
                )}
            </div>

            {loading ? <LoadingSpinner /> : (
                <div className="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    {list.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-gray-400">No HIRARC assessments yet.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                                    <th className="px-6 py-3 text-left font-medium">Title</th>
                                    <th className="px-6 py-3 text-left font-medium">Project</th>
                                    <th className="px-6 py-3 text-right font-medium">Hazards</th>
                                    <th className="px-6 py-3 text-left font-medium">Status</th>
                                    <th className="px-6 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((a) => (
                                    <tr key={a.id} className="border-b border-gray-100 last:border-0">
                                        <td className="px-6 py-3 font-medium text-gray-900">{a.title}<div className="text-xs font-normal text-gray-400">{a.process}</div></td>
                                        <td className="px-6 py-3 text-gray-600">{a.project?.name || '-'}</td>
                                        <td className="px-6 py-3 text-right text-gray-600">{a.items_count ?? '-'}</td>
                                        <td className="px-6 py-3"><span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${a.status === 'archived' ? 'bg-gray-100 text-gray-500' : 'bg-green-50 text-green-700'}`}>{a.status}</span></td>
                                        <td className="px-6 py-3 text-right">
                                            {canManage && (
                                                <>
                                                    <button onClick={() => openEdit(a)} className="mr-1 rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><HiOutlinePencil className="h-4 w-4" /></button>
                                                    {a.status !== 'archived' && <button onClick={() => archive(a.id)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineTrash className="h-4 w-4" /></button>}
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            )}

            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowForm(false)}>
                    <div className="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">{form.id ? 'Edit HIRARC' : 'New HIRARC'}</h3>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div><label className="mb-1 block text-sm font-medium text-gray-700">Title *</label><input type="text" required value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} className={inputCls} /></div>
                                <div><label className="mb-1 block text-sm font-medium text-gray-700">Process / Activity</label><input type="text" value={form.process} onChange={(e) => setForm((p) => ({ ...p, process: e.target.value }))} className={inputCls} /></div>
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
                            </div>

                            <div>
                                <div className="mb-2 flex items-center justify-between">
                                    <label className="text-sm font-medium text-gray-700">Hazards</label>
                                    <button type="button" onClick={addItem} className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700"><HiOutlinePlus className="h-4 w-4" /> Add hazard</button>
                                </div>
                                <div className="space-y-3">
                                    {form.items.map((it, i) => {
                                        const { rating, level } = previewLevel(Number(it.likelihood), Number(it.severity));
                                        return (
                                            <div key={i} className="rounded-lg border border-gray-200 p-3">
                                                <div className="mb-2 flex items-start gap-2">
                                                    <input type="text" placeholder="Hazard *" value={it.hazard} onChange={(e) => setItem(i, 'hazard', e.target.value)} className={inputCls} />
                                                    <button type="button" onClick={() => removeItem(i)} className="mt-1 rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineTrash className="h-4 w-4" /></button>
                                                </div>
                                                <div className="grid gap-2 sm:grid-cols-2">
                                                    <input type="text" placeholder="Risk / who can be harmed" value={it.risk} onChange={(e) => setItem(i, 'risk', e.target.value)} className={inputCls} />
                                                    <input type="text" placeholder="Existing control" value={it.existing_control} onChange={(e) => setItem(i, 'existing_control', e.target.value)} className={inputCls} />
                                                </div>
                                                <div className="mt-2 flex flex-wrap items-center gap-3">
                                                    <label className="text-xs text-gray-500">Likelihood
                                                        <select value={it.likelihood} onChange={(e) => setItem(i, 'likelihood', Number(e.target.value))} className="ml-1 rounded border border-gray-300 px-2 py-1 text-sm">
                                                            {[1, 2, 3, 4, 5].map((n) => <option key={n} value={n}>{n}</option>)}
                                                        </select>
                                                    </label>
                                                    <label className="text-xs text-gray-500">Severity
                                                        <select value={it.severity} onChange={(e) => setItem(i, 'severity', Number(e.target.value))} className="ml-1 rounded border border-gray-300 px-2 py-1 text-sm">
                                                            {[1, 2, 3, 4, 5].map((n) => <option key={n} value={n}>{n}</option>)}
                                                        </select>
                                                    </label>
                                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${LEVEL_COLORS[level]}`}>Risk {rating} · {level}</span>
                                                </div>
                                                <div className="mt-2 grid gap-2 sm:grid-cols-3">
                                                    <input type="text" placeholder="Recommended control" value={it.recommended_control} onChange={(e) => setItem(i, 'recommended_control', e.target.value)} className={`${inputCls} sm:col-span-1`} />
                                                    <input type="text" placeholder="PIC" value={it.pic} onChange={(e) => setItem(i, 'pic', e.target.value)} className={inputCls} />
                                                    <input type="date" value={it.due_date} onChange={(e) => setItem(i, 'due_date', e.target.value)} className={inputCls} />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">{saving ? 'Saving…' : form.id ? 'Update' : 'Create'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
