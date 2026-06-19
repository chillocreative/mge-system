import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import projectInvoiceService from '@/services/projectInvoiceService';
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

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-blue-100 text-blue-700',
    approved: 'bg-green-100 text-green-700',
    paid: 'bg-emerald-100 text-emerald-700',
};

const statuses = ['draft', 'submitted', 'approved', 'paid'];

function formatCurrency(val) {
    return 'RM ' + Number(val || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 });
}

function userName(u) {
    if (!u) return '-';
    return `${u.first_name || ''} ${u.last_name || ''}`.trim() || '-';
}

const emptyForm = {
    project_id: '',
    invoice_no: '',
    invoice_date: new Date().toISOString().split('T')[0],
    amount: '',
    status: 'draft',
    client_approved_date: '',
    notes: '',
    files: [],
};

export default function ProjectInvoices() {
    const { can } = useAuth();
    const canEdit = can('projects.edit');

    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [projectFilter, setProjectFilter] = useState('');
    const [pagination, setPagination] = useState({});
    const [projects, setProjects] = useState([]);

    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(emptyForm);

    const fetchInvoices = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            if (projectFilter) params.project_id = projectFilter;
            const res = await projectInvoiceService.list(params);
            setInvoices(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setInvoices([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchInvoices(), 400);
        return () => clearTimeout(timer);
    }, [search, statusFilter, projectFilter]);

    useEffect(() => {
        fetchInvoices();
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
    }, []);

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm);
        setShowForm(true);
    };

    const openEdit = (inv) => {
        setEditing(inv);
        setForm({
            project_id: inv.project_id || '',
            invoice_no: inv.invoice_no || '',
            invoice_date: inv.invoice_date ? String(inv.invoice_date).split('T')[0] : '',
            amount: inv.amount ?? '',
            status: inv.status || 'draft',
            client_approved_date: inv.client_approved_date ? String(inv.client_approved_date).split('T')[0] : '',
            notes: inv.notes || '',
            files: [],
        });
        setShowForm(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const fd = new FormData();
            fd.append('project_id', form.project_id);
            if (form.invoice_no) fd.append('invoice_no', form.invoice_no);
            fd.append('invoice_date', form.invoice_date);
            fd.append('amount', form.amount);
            fd.append('status', form.status);
            if (form.client_approved_date) fd.append('client_approved_date', form.client_approved_date);
            if (form.notes) fd.append('notes', form.notes);
            (form.files || []).forEach((f) => fd.append('files[]', f));

            if (editing) {
                await projectInvoiceService.update(editing.id, fd);
                toast.success('Invoice updated');
            } else {
                await projectInvoiceService.create(fd);
                toast.success('Invoice created');
            }
            setShowForm(false);
            setForm(emptyForm);
            setEditing(null);
            fetchInvoices();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save invoice');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this invoice?')) return;
        try {
            await projectInvoiceService.remove(id);
            toast.success('Invoice deleted');
            fetchInvoices();
        } catch {
            toast.error('Failed to delete invoice');
        }
    };

    const handleDeleteFile = async (fileId) => {
        if (!confirm('Delete this file?')) return;
        try {
            await projectInvoiceService.deleteFile(fileId);
            toast.success('File deleted');
            fetchInvoices();
        } catch {
            toast.error('Failed to delete file');
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Project Invoices</h1>
                    <p className="text-sm text-gray-500">Manage invoices raised against projects</p>
                </div>
                {canEdit && (
                    <button
                        onClick={openCreate}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        New Invoice
                    </button>
                )}
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search invoices..."
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
                    {statuses.map((s) => (
                        <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                    ))}
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : invoices.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineDocumentText className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No invoices found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Invoice No</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Invoice Date</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Amount</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Client Approved</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Created By</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {invoices.map((inv) => (
                                    <tr key={inv.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm text-gray-600">{inv.project?.name || '-'}</td>
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">{inv.invoice_no || '-'}</p>
                                            {inv.files?.length > 0 && (
                                                <div className="mt-1 flex flex-wrap gap-1">
                                                    {inv.files.map((f) => (
                                                        <span key={f.id} className="inline-flex items-center gap-1 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">
                                                            <a
                                                                href={projectInvoiceService.getFileDownloadUrl(f.id)}
                                                                className="inline-flex items-center gap-1 hover:text-primary-600"
                                                                title={f.file_name}
                                                            >
                                                                <HiOutlinePaperClip className="h-3 w-3" />
                                                                <span className="max-w-[120px] truncate">{f.file_name}</span>
                                                            </a>
                                                            {canEdit && (
                                                                <button
                                                                    onClick={() => handleDeleteFile(f.id)}
                                                                    className="text-gray-400 hover:text-red-600"
                                                                    title="Remove file"
                                                                >
                                                                    <HiOutlineTrash className="h-3 w-3" />
                                                                </button>
                                                            )}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{inv.invoice_date || '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900">
                                            {formatCurrency(inv.amount)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[inv.status] || 'bg-gray-100 text-gray-700'}`}>
                                                {inv.status}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{inv.client_approved_date || '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{userName(inv.creator)}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {inv.files?.length > 0 && (
                                                    <a
                                                        href={projectInvoiceService.getFileDownloadUrl(inv.files[0].id)}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600"
                                                        title="Download first file"
                                                    >
                                                        <HiOutlineDownload className="h-4 w-4" />
                                                    </a>
                                                )}
                                                {canEdit && (
                                                    <>
                                                        <button
                                                            onClick={() => openEdit(inv)}
                                                            className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600"
                                                            title="Edit"
                                                        >
                                                            <HiOutlinePencil className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            onClick={() => handleDelete(inv.id)}
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
                                        onClick={() => fetchInvoices(page)}
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

            {/* Create/Edit Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowForm(false)}>
                    <div className="mx-4 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">
                            {editing ? 'Edit Invoice' : 'New Invoice'}
                        </h3>
                        <form onSubmit={handleSubmit} className="max-h-[75vh] space-y-4 overflow-y-auto">
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
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Invoice No</label>
                                    <input
                                        type="text"
                                        value={form.invoice_no}
                                        onChange={(e) => setForm((p) => ({ ...p, invoice_no: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Invoice Date *</label>
                                    <input
                                        type="date"
                                        value={form.invoice_date}
                                        onChange={(e) => setForm((p) => ({ ...p, invoice_date: e.target.value }))}
                                        required
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Amount (RM) *</label>
                                    <input
                                        type="number"
                                        value={form.amount}
                                        onChange={(e) => setForm((p) => ({ ...p, amount: e.target.value }))}
                                        min="0"
                                        step="0.01"
                                        required
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Status *</label>
                                    <select
                                        value={form.status}
                                        onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))}
                                        required
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        {statuses.map((s) => (
                                            <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Date Approved by Client</label>
                                <input
                                    type="date"
                                    value={form.client_approved_date}
                                    onChange={(e) => setForm((p) => ({ ...p, client_approved_date: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <textarea
                                    rows={2}
                                    value={form.notes}
                                    onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Related Files</label>
                                <input
                                    type="file"
                                    multiple
                                    onChange={(e) => setForm((p) => ({ ...p, files: Array.from(e.target.files || []) }))}
                                    className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100"
                                />
                                <p className="mt-1 text-xs text-gray-400">PDF, Word, Excel or images. Max 25MB each.</p>
                                {editing?.files?.length > 0 && (
                                    <div className="mt-2 flex flex-wrap gap-1">
                                        {editing.files.map((f) => (
                                            <a
                                                key={f.id}
                                                href={projectInvoiceService.getFileDownloadUrl(f.id)}
                                                className="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600 hover:text-primary-600"
                                            >
                                                <HiOutlinePaperClip className="h-3 w-3" />
                                                {f.file_name}
                                            </a>
                                        ))}
                                    </div>
                                )}
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
                                    {saving ? 'Saving...' : editing ? 'Update Invoice' : 'Create Invoice'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
