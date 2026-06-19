import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import correspondenceService from '@/services/correspondenceService';
import projectService from '@/services/projectService';
import ProjectFilesPanel from '@/components/ProjectFilesPanel';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus, HiOutlineSearch, HiOutlineDocumentText, HiOutlinePencil,
    HiOutlineTrash, HiOutlineDownload, HiOutlinePaperClip, HiOutlineCog, HiOutlineX,
} from 'react-icons/hi';

const BADGE_COLORS = {
    gray: 'bg-gray-100 text-gray-700', red: 'bg-red-100 text-red-700', orange: 'bg-orange-100 text-orange-700',
    amber: 'bg-amber-100 text-amber-700', green: 'bg-green-100 text-green-700', teal: 'bg-teal-100 text-teal-700',
    blue: 'bg-blue-100 text-blue-700', indigo: 'bg-indigo-100 text-indigo-700', purple: 'bg-purple-100 text-purple-700',
    pink: 'bg-pink-100 text-pink-700',
};
const DOT_COLORS = {
    gray: 'bg-gray-400', red: 'bg-red-500', orange: 'bg-orange-500', amber: 'bg-amber-500', green: 'bg-green-500',
    teal: 'bg-teal-500', blue: 'bg-blue-500', indigo: 'bg-indigo-500', purple: 'bg-purple-500', pink: 'bg-pink-500',
};
const PALETTE = Object.keys(BADGE_COLORS);

const statusColors = {
    open: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    closed: 'bg-gray-100 text-gray-600',
};

const baseForm = {
    project_id: '', type: '', reference_no: '', title: '', description: '',
    status: 'open', raised_date: new Date().toISOString().split('T')[0], due_date: '', response: '', files: [],
};
const emptyTypeForm = { name: '', code: '', full_name: '', color: 'gray', sort_order: 0, is_active: true };

export default function Correspondence() {
    const { can } = useAuth();
    const canEdit = can('projects.edit');

    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [projectFilter, setProjectFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [pagination, setPagination] = useState({});
    const [projects, setProjects] = useState([]);
    const [types, setTypes] = useState([]);

    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(baseForm);

    // Manage types modal
    const [showTypes, setShowTypes] = useState(false);
    const [typeForm, setTypeForm] = useState(emptyTypeForm);
    const [typeEditId, setTypeEditId] = useState(null);
    const [typeSaving, setTypeSaving] = useState(false);

    const activeTypes = types.filter((t) => t.is_active);
    const typeMap = Object.fromEntries(types.map((t) => [t.code, t]));
    const badgeClass = (code) => BADGE_COLORS[typeMap[code]?.color] || 'bg-gray-100 text-gray-600';
    const typeLabel = (code) => typeMap[code]?.name || code?.toUpperCase();

    const fetchTypes = async () => {
        try {
            const res = await correspondenceService.types();
            setTypes(res.data || []);
        } catch { /* ignore */ }
    };

    const fetchItems = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (typeFilter) params.type = typeFilter;
            if (projectFilter) params.project_id = projectFilter;
            if (statusFilter) params.status = statusFilter;
            const res = await correspondenceService.list(params);
            setItems(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setItems([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchItems(), 400);
        return () => clearTimeout(timer);
    }, [search, typeFilter, projectFilter, statusFilter]);

    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
        fetchTypes();
    }, []);

    const openCreate = () => {
        setEditingId(null);
        setForm({ ...baseForm, type: activeTypes[0]?.code || '' });
        setShowForm(true);
    };

    const openEdit = (item) => {
        setEditingId(item.id);
        setForm({
            project_id: item.project_id || '', type: item.type || '', reference_no: item.reference_no || '',
            title: item.title || '', description: item.description || '', status: item.status || 'open',
            raised_date: item.raised_date ? String(item.raised_date).split('T')[0] : '',
            due_date: item.due_date ? String(item.due_date).split('T')[0] : '',
            response: item.response || '', files: [],
        });
        setShowForm(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const fd = new FormData();
            fd.append('project_id', form.project_id);
            fd.append('type', form.type);
            fd.append('title', form.title);
            fd.append('status', form.status);
            fd.append('raised_date', form.raised_date);
            if (form.reference_no) fd.append('reference_no', form.reference_no);
            if (form.description) fd.append('description', form.description);
            if (form.due_date) fd.append('due_date', form.due_date);
            if (form.response) fd.append('response', form.response);
            form.files.forEach((f) => fd.append('files[]', f));

            if (editingId) { await correspondenceService.update(editingId, fd); toast.success('Correspondence updated'); }
            else { await correspondenceService.create(fd); toast.success('Correspondence created'); }
            setShowForm(false);
            fetchItems(editingId ? pagination.current_page : 1);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save correspondence');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this correspondence?')) return;
        try { await correspondenceService.remove(id); toast.success('Correspondence deleted'); fetchItems(); }
        catch { toast.error('Failed to delete correspondence'); }
    };

    const handleDownload = async (file) => {
        try { await correspondenceService.downloadFile(file.id, file.file_name); }
        catch { toast.error('Failed to download file'); }
    };

    // ── Manage types ──
    const openTypeCreate = () => { setTypeEditId(null); setTypeForm({ ...emptyTypeForm, sort_order: types.length + 1 }); };
    const openTypeEdit = (t) => { setTypeEditId(t.id); setTypeForm({ name: t.name, code: t.code, full_name: t.full_name || '', color: t.color, sort_order: t.sort_order, is_active: t.is_active }); };

    const saveType = async (e) => {
        e.preventDefault();
        setTypeSaving(true);
        try {
            if (typeEditId) {
                await correspondenceService.updateType(typeEditId, {
                    name: typeForm.name, full_name: typeForm.full_name || null, color: typeForm.color,
                    sort_order: Number(typeForm.sort_order) || 0, is_active: typeForm.is_active,
                });
                toast.success('Type updated');
            } else {
                await correspondenceService.createType({
                    name: typeForm.name, code: typeForm.code || undefined, full_name: typeForm.full_name || null,
                    color: typeForm.color, sort_order: Number(typeForm.sort_order) || 0, is_active: typeForm.is_active,
                });
                toast.success('Type added');
            }
            setTypeForm(emptyTypeForm); setTypeEditId(null);
            await fetchTypes();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save type');
        } finally {
            setTypeSaving(false);
        }
    };

    const removeType = async (t) => {
        if (!confirm(`Delete type "${t.name}"?`)) return;
        try { await correspondenceService.deleteType(t.id); toast.success('Type deleted'); fetchTypes(); }
        catch (err) { toast.error(err.response?.data?.message || 'Failed to delete type'); }
    };

    const toggleTypeActive = async (t) => {
        try { await correspondenceService.updateType(t.id, { is_active: !t.is_active }); fetchTypes(); }
        catch { toast.error('Failed to update type'); }
    };

    const tabBtn = (active) =>
        `rounded-lg px-3 py-1.5 text-sm font-medium ${active ? 'bg-primary-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'}`;

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Project Correspondence</h1>
                    <p className="text-sm text-gray-500">Track project document types &amp; requests</p>
                </div>
                <div className="flex gap-2">
                    {canEdit && (
                        <button onClick={() => { setShowTypes(true); openTypeCreate(); }}
                            className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            <HiOutlineCog className="h-5 w-5" /> Manage Types
                        </button>
                    )}
                    {canEdit && (
                        <button onClick={openCreate} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                            <HiOutlinePlus className="h-5 w-5" /> New Correspondence
                        </button>
                    )}
                </div>
            </div>

            {/* Dynamic type tabs */}
            <div className="mb-4 flex flex-wrap gap-2">
                <button onClick={() => setTypeFilter('')} className={tabBtn(typeFilter === '')}>All</button>
                {activeTypes.map((t) => (
                    <button key={t.code} onClick={() => setTypeFilter(t.code)} title={t.full_name} className={tabBtn(typeFilter === t.code)}>
                        {t.name}
                    </button>
                ))}
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input type="text" placeholder="Search correspondence..." value={search} onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                </div>
                <select value={projectFilter} onChange={(e) => setProjectFilter(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">All Projects</option>
                    {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
                <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">All Statuses</option>
                    <option value="open">Open</option>
                    <option value="pending">Pending</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            {loading ? <LoadingSpinner /> : items.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineDocumentText className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No correspondence found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Reference No</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Raised Date</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Due Date</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Created By</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {items.map((item) => (
                                    <tr key={item.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3"><span className={`inline-block rounded-full px-2 py-0.5 text-xs font-semibold ${badgeClass(item.type)}`}>{typeLabel(item.type)}</span></td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{item.reference_no || '-'}</td>
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">{item.title}</p>
                                            {item.files?.length > 0 && (
                                                <div className="mt-1 flex flex-wrap gap-2">
                                                    {item.files.map((f) => (
                                                        <button key={f.id} onClick={() => handleDownload(f)} className="inline-flex items-center gap-1 text-xs text-primary-600 hover:underline" title={f.file_name}>
                                                            <HiOutlinePaperClip className="h-3.5 w-3.5" /><span className="max-w-[10rem] truncate">{f.file_name}</span>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{item.project?.name || '-'}</td>
                                        <td className="px-4 py-3"><span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[item.status] || 'bg-gray-100 text-gray-600'}`}>{item.status}</span></td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{item.raised_date ? String(item.raised_date).split('T')[0] : '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{item.due_date ? String(item.due_date).split('T')[0] : '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{item.creator ? `${item.creator.first_name} ${item.creator.last_name}` : '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {item.files?.length > 0 && (
                                                    <button onClick={() => handleDownload(item.files[0])} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Download first attachment"><HiOutlineDownload className="h-4 w-4" /></button>
                                                )}
                                                {canEdit && (
                                                    <>
                                                        <button onClick={() => openEdit(item)} className="rounded p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600" title="Edit"><HiOutlinePencil className="h-4 w-4" /></button>
                                                        <button onClick={() => handleDelete(item.id)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete"><HiOutlineTrash className="h-4 w-4" /></button>
                                                    </>
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
                            <p className="text-sm text-gray-500">Showing {pagination.from}-{pagination.to} of {pagination.total}</p>
                            <div className="flex gap-1">
                                {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                                    <button key={page} onClick={() => fetchItems(page)} className={`rounded px-3 py-1 text-sm ${page === pagination.current_page ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}>{page}</button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Create / Edit Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 py-8" onClick={() => setShowForm(false)}>
                    <div className="mx-4 w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">{editingId ? 'Edit Correspondence' : 'New Correspondence'}</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Project *</label>
                                    <select value={form.project_id} onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="">Select project</option>
                                        {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Type *</label>
                                    <select value={form.type} onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="">Select type</option>
                                        {activeTypes.map((t) => <option key={t.code} value={t.code}>{t.name}{t.full_name ? ` — ${t.full_name}` : ''}</option>)}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Reference No</label>
                                    <input type="text" value={form.reference_no} onChange={(e) => setForm((p) => ({ ...p, reference_no: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                    <select value={form.status} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="open">Open</option><option value="pending">Pending</option><option value="closed">Closed</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Title *</label>
                                <input type="text" value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                <textarea rows={3} value={form.description} onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Raised Date *</label>
                                    <input type="date" value={form.raised_date} onChange={(e) => setForm((p) => ({ ...p, raised_date: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Due Date</label>
                                    <input type="date" value={form.due_date} onChange={(e) => setForm((p) => ({ ...p, due_date: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Response</label>
                                <textarea rows={2} value={form.response} onChange={(e) => setForm((p) => ({ ...p, response: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            {form.project_id && (
                                <div className="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <label className="mb-2 block text-xs font-bold uppercase text-gray-500">Project Files (reference)</label>
                                    <ProjectFilesPanel projectId={form.project_id} readOnly />
                                </div>
                            )}

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Attachments</label>
                                <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" onChange={(e) => setForm((p) => ({ ...p, files: Array.from(e.target.files) }))}
                                    className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100" />
                                <p className="mt-1 text-xs text-gray-400">PDF, Word, Excel or images. Max 25MB each.</p>
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : editingId ? 'Update' : 'Create'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Manage Types Modal */}
            {showTypes && (
                <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 py-8" onClick={() => setShowTypes(false)}>
                    <div className="mx-4 w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">Manage Correspondence Types</h3>
                            <button onClick={() => setShowTypes(false)} className="rounded p-1 text-gray-400 hover:bg-gray-100"><HiOutlineX className="h-5 w-5" /></button>
                        </div>

                        {/* Add / edit form */}
                        <form onSubmit={saveType} className="mb-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <p className="mb-3 text-xs font-bold uppercase text-gray-500">{typeEditId ? 'Edit Type' : 'Add New Type'}</p>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Label (short) *</label>
                                    <input type="text" value={typeForm.name} onChange={(e) => setTypeForm((p) => ({ ...p, name: e.target.value }))} required maxLength={50} placeholder="e.g. SST" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Code {typeEditId ? '(locked)' : '(optional)'}</label>
                                    <input type="text" value={typeForm.code} disabled={!!typeEditId} onChange={(e) => setTypeForm((p) => ({ ...p, code: e.target.value }))} placeholder="auto from label" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100 disabled:text-gray-500" />
                                </div>
                            </div>
                            <div className="mt-3">
                                <label className="mb-1 block text-xs font-medium text-gray-700">Full Name</label>
                                <input type="text" value={typeForm.full_name} onChange={(e) => setTypeForm((p) => ({ ...p, full_name: e.target.value }))} placeholder="e.g. Site Safety Test" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                            </div>
                            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Colour</label>
                                    <div className="flex flex-wrap gap-1.5">
                                        {PALETTE.map((c) => (
                                            <button type="button" key={c} onClick={() => setTypeForm((p) => ({ ...p, color: c }))}
                                                className={`h-6 w-6 rounded-full ${DOT_COLORS[c]} ${typeForm.color === c ? 'ring-2 ring-offset-1 ring-gray-700' : ''}`} title={c} />
                                        ))}
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Sort Order</label>
                                    <input type="number" min="0" value={typeForm.sort_order} onChange={(e) => setTypeForm((p) => ({ ...p, sort_order: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div className="flex items-end">
                                    <label className="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" checked={typeForm.is_active} onChange={(e) => setTypeForm((p) => ({ ...p, is_active: e.target.checked }))} className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                        Active (show as tab)
                                    </label>
                                </div>
                            </div>
                            <div className="mt-3 flex justify-end gap-2">
                                {typeEditId && <button type="button" onClick={openTypeCreate} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">New</button>}
                                <button type="submit" disabled={typeSaving} className="rounded-lg bg-primary-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{typeSaving ? 'Saving...' : typeEditId ? 'Update Type' : 'Add Type'}</button>
                            </div>
                        </form>

                        {/* Existing types list */}
                        <div className="space-y-1.5">
                            {types.map((t) => (
                                <div key={t.id} className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-2">
                                    <span className={`h-3 w-3 shrink-0 rounded-full ${DOT_COLORS[t.color] || 'bg-gray-400'}`} />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold text-gray-900">{t.name} {!t.is_active && <span className="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">inactive</span>}</p>
                                        <p className="truncate text-xs text-gray-400">{t.full_name || t.code}</p>
                                    </div>
                                    <button onClick={() => toggleTypeActive(t)} className="rounded px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100" title={t.is_active ? 'Deactivate' : 'Activate'}>{t.is_active ? 'Hide' : 'Show'}</button>
                                    <button onClick={() => openTypeEdit(t)} className="rounded p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600" title="Edit"><HiOutlinePencil className="h-4 w-4" /></button>
                                    <button onClick={() => removeType(t)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete"><HiOutlineTrash className="h-4 w-4" /></button>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
