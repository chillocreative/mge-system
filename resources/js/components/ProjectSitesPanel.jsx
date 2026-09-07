import { useState, useEffect, useCallback } from 'react';
import projectSiteService from '@/services/projectSiteService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlinePlus, HiOutlinePencil, HiOutlineTrash, HiOutlineLocationMarker } from 'react-icons/hi';

/**
 * Manage the sites/zones of a project (Ciri 25). Sites are optional: records
 * without one keep using their free-text location. Deleting a site never
 * deletes the records filed under it — they simply lose the link.
 */

const emptyForm = () => ({ id: null, name: '', code: '', address: '', is_active: true });

export default function ProjectSitesPanel({ project, canEdit }) {
    const [sites, setSites] = useState([]);
    const [loading, setLoading] = useState(true);
    const [form, setForm] = useState(null); // null = closed, else the form object
    const [saving, setSaving] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await projectSiteService.list(project.id);
            setSites(res.data || []);
        } catch {
            setSites([]);
        } finally {
            setLoading(false);
        }
    }, [project.id]);

    useEffect(() => { load(); }, [load]);

    const save = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = { ...form, project_id: project.id };
            if (form.id) {
                await projectSiteService.update(form.id, payload);
                toast.success('Site updated');
            } else {
                await projectSiteService.create(payload);
                toast.success('Site added');
            }
            setForm(null);
            load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save');
        } finally {
            setSaving(false);
        }
    };

    const remove = async (site) => {
        if (!confirm(`Remove site "${site.name}"? Records filed under it are kept and simply lose the link.`)) return;
        try {
            await projectSiteService.remove(site.id);
            toast.success('Site removed');
            load();
        } catch {
            toast.error('Failed to remove');
        }
    };

    const inputCls = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

    return (
        <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-gray-900">Sites</h2>
                    <p className="text-sm text-gray-500">Physical sites or zones within this project</p>
                </div>
                {canEdit && !form && (
                    <button onClick={() => setForm(emptyForm())} className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        <HiOutlinePlus className="h-4 w-4" /> Add site
                    </button>
                )}
            </div>

            {form && (
                <form onSubmit={save} className="mb-5 grid gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:grid-cols-2">
                    <div><label className="mb-1 block text-xs font-medium uppercase text-gray-500">Name *</label><input type="text" required value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} className={inputCls} /></div>
                    <div><label className="mb-1 block text-xs font-medium uppercase text-gray-500">Code</label><input type="text" value={form.code} onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))} className={inputCls} /></div>
                    <div className="sm:col-span-2"><label className="mb-1 block text-xs font-medium uppercase text-gray-500">Address</label><input type="text" value={form.address} onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))} className={inputCls} /></div>
                    <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={form.is_active} onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))} /> Active</label>
                    <div className="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" onClick={() => setForm(null)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cancel</button>
                        <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving…' : form.id ? 'Update' : 'Add'}</button>
                    </div>
                </form>
            )}

            {loading ? <LoadingSpinner /> : sites.length === 0 ? (
                <div className="py-10 text-center">
                    <HiOutlineLocationMarker className="mx-auto h-10 w-10 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-400">No sites yet. Records use their free-text location until you add sites.</p>
                </div>
            ) : (
                <ul className="divide-y divide-gray-100">
                    {sites.map((s) => (
                        <li key={s.id} className="flex items-center justify-between gap-3 py-3">
                            <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                    <span className="font-medium text-gray-900">{s.name}</span>
                                    {s.code && <span className="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{s.code}</span>}
                                    {!s.is_active && <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">inactive</span>}
                                </div>
                                {s.address && <p className="truncate text-xs text-gray-400">{s.address}</p>}
                            </div>
                            {canEdit && (
                                <div className="flex shrink-0 items-center gap-1">
                                    <button onClick={() => setForm({ id: s.id, name: s.name, code: s.code || '', address: s.address || '', is_active: s.is_active })} className="rounded p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600"><HiOutlinePencil className="h-4 w-4" /></button>
                                    <button onClick={() => remove(s)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineTrash className="h-4 w-4" /></button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
