import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import correspondenceService from '@/services/correspondenceService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineDocumentText,
    HiOutlinePencil,
    HiOutlineTrash,
    HiOutlineDownload,
    HiOutlinePaperClip,
} from 'react-icons/hi';

const TYPES = [
    { value: 'ncr', label: 'NCR', full: 'Non-Conformance Report' },
    { value: 'rfa', label: 'RFA', full: 'Request for Approval' },
    { value: 'rfi', label: 'RFI', full: 'Request for Information' },
    { value: 'rfwi', label: 'RFWI', full: 'Request for Work Inspection' },
];

const typeBadgeColors = {
    ncr: 'bg-red-100 text-red-700',
    rfa: 'bg-blue-100 text-blue-700',
    rfi: 'bg-amber-100 text-amber-700',
    rfwi: 'bg-purple-100 text-purple-700',
};

const statusColors = {
    open: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    closed: 'bg-gray-100 text-gray-600',
};

const emptyForm = {
    project_id: '',
    type: 'ncr',
    reference_no: '',
    title: '',
    description: '',
    status: 'open',
    raised_date: new Date().toISOString().split('T')[0],
    due_date: '',
    response: '',
    files: [],
};

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

    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(emptyForm);

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
    }, []);

    const openCreate = () => {
        setEditingId(null);
        setForm(emptyForm);
        setShowForm(true);
    };

    const openEdit = (item) => {
        setEditingId(item.id);
        setForm({
            project_id: item.project_id || '',
            type: item.type || 'ncr',
            reference_no: item.reference_no || '',
            title: item.title || '',
            description: item.description || '',
            status: item.status || 'open',
            raised_date: item.raised_date ? String(item.raised_date).split('T')[0] : '',
            due_date: item.due_date ? String(item.due_date).split('T')[0] : '',
            response: item.response || '',
            files: [],
        });
        setShowForm(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const formData = new FormData();
            formData.append('project_id', form.project_id);
            formData.append('type', form.type);
            formData.append('title', form.title);
            formData.append('status', form.status);
            formData.append('raised_date', form.raised_date);
            if (form.reference_no) formData.append('reference_no', form.reference_no);
            if (form.description) formData.append('description', form.description);
            if (form.due_date) formData.append('due_date', form.due_date);
            if (form.response) formData.append('response', form.response);
            form.files.forEach((f) => formData.append('files[]', f));

            if (editingId) {
                await correspondenceService.update(editingId, formData);
                toast.success('Correspondence updated');
            } else {
                await correspondenceService.create(formData);
                toast.success('Correspondence created');
            }
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
        try {
            await correspondenceService.remove(id);
            toast.success('Correspondence deleted');
            fetchItems();
        } catch {
            toast.error('Failed to delete correspondence');
        }
    };

    const handleDownload = async (file) => {
        try {
            await correspondenceService.downloadFile(file.id, file.file_name);
        } catch {
            toast.error('Failed to download file');
        }
    };

    const typeLabel = (val) => TYPES.find((t) => t.value === val)?.label || val?.toUpperCase();

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Project Correspondence</h1>
                    <p className="text-sm text-gray-500">NCR, RFA, RFI &amp; RFWI documents</p>
                </div>
                {canEdit && (
                    <button
                        onClick={openCreate}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        New Correspondence
                    </button>
                )}
            </div>

            {/* Type tabs */}
            <div className="mb-4 flex flex-wrap gap-2">
                <button
                    onClick={() => setTypeFilter('')}
                    className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
                        typeFilter === '' ? 'bg-primary-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'
                    }`}
                >
                    All
                </button>
                {TYPES.map((t) => (
                    <button
                        key={t.value}
                        onClick={() => setTypeFilter(t.value)}
                        title={t.full}
                        className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
                            typeFilter === t.value ? 'bg-primary-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search correspondence..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                </div>
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
                    <option value="open">Open</option>
                    <option value="pending">Pending</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : items.length === 0 ? (
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
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-semibold ${typeBadgeColors[item.type] || 'bg-gray-100 text-gray-600'}`}>
                                                {typeLabel(item.type)}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{item.reference_no || '-'}</td>
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">{item.title}</p>
                                            {item.files?.length > 0 && (
                                                <div className="mt-1 flex flex-wrap gap-2">
                                                    {item.files.map((f) => (
                                                        <button
                                                            key={f.id}
                                                            onClick={() => handleDownload(f)}
                                                            className="inline-flex items-center gap-1 text-xs text-primary-600 hover:underline"
                                                            title={f.file_name}
                                                        >
                                                            <HiOutlinePaperClip className="h-3.5 w-3.5" />
                                                            <span className="max-w-[10rem] truncate">{f.file_name}</span>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{item.project?.name || '-'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[item.status] || 'bg-gray-100 text-gray-600'}`}>
                                                {item.status}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                            {item.raised_date ? String(item.raised_date).split('T')[0] : '-'}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                            {item.due_date ? String(item.due_date).split('T')[0] : '-'}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                            {item.creator ? `${item.creator.first_name} ${item.creator.last_name}` : '-'}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {item.files?.length > 0 && (
                                                    <button
                                                        onClick={() => handleDownload(item.files[0])}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600"
                                                        title="Download first attachment"
                                                    >
                                                        <HiOutlineDownload className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {canEdit && (
                                                    <>
                                                        <button
                                                            onClick={() => openEdit(item)}
                                                            className="rounded p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600"
                                                            title="Edit"
                                                        >
                                                            <HiOutlinePencil className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            onClick={() => handleDelete(item.id)}
                                                            className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                            title="Delete"
                                                        >
                                                            <HiOutlineTrash className="h-4 w-4" />
                                                        </button>
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
                            <p className="text-sm text-gray-500">
                                Showing {pagination.from}-{pagination.to} of {pagination.total}
                            </p>
                            <div className="flex gap-1">
                                {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                                    <button
                                        key={page}
                                        onClick={() => fetchItems(page)}
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

            {/* Create / Edit Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 py-8" onClick={() => setShowForm(false)}>
                    <div className="mx-4 w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">
                            {editingId ? 'Edit Correspondence' : 'New Correspondence'}
                        </h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Project *</label>
                                    <select
                                        value={form.project_id}
                                        onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))}
                                        required
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        <option value="">Select project</option>
                                        {projects.map((p) => (
                                            <option key={p.id} value={p.id}>{p.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Type *</label>
                                    <select
                                        value={form.type}
                                        onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))}
                                        required
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        {TYPES.map((t) => (
                                            <option key={t.value} value={t.value}>{t.label} — {t.full}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Reference No</label>
                                    <input
                                        type="text"
                                        value={form.reference_no}
                                        onChange={(e) => setForm((p) => ({ ...p, reference_no: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                    <select
                                        value={form.status}
                                        onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        <option value="open">Open</option>
                                        <option value="pending">Pending</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Title *</label>
                                <input
                                    type="text"
                                    value={form.title}
                                    onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))}
                                    required
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                <textarea
                                    rows={3}
                                    value={form.description}
                                    onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Raised Date *</label>
                                    <input
                                        type="date"
                                        value={form.raised_date}
                                        onChange={(e) => setForm((p) => ({ ...p, raised_date: e.target.value }))}
                                        required
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Due Date</label>
                                    <input
                                        type="date"
                                        value={form.due_date}
                                        onChange={(e) => setForm((p) => ({ ...p, due_date: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Response</label>
                                <textarea
                                    rows={2}
                                    value={form.response}
                                    onChange={(e) => setForm((p) => ({ ...p, response: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Attachments</label>
                                <input
                                    type="file"
                                    multiple
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                    onChange={(e) => setForm((p) => ({ ...p, files: Array.from(e.target.files) }))}
                                    className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100"
                                />
                                <p className="mt-1 text-xs text-gray-400">PDF, Word, Excel or images. Max 25MB each.</p>
                            </div>

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
                                    disabled={saving}
                                    className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                                >
                                    {saving ? 'Saving...' : editingId ? 'Update' : 'Create'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
