import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import projectInvoiceService from '@/services/projectInvoiceService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus, HiOutlineSearch, HiOutlineDocumentText, HiOutlinePencil, HiOutlineTrash,
    HiOutlineDownload, HiOutlinePaperClip, HiOutlineTrendingUp, HiOutlineArrowUp, HiOutlineArrowDown,
} from 'react-icons/hi';

const fmt = (v) => 'RM ' + Number(v || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 });
const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-blue-100 text-blue-700',
    approved: 'bg-amber-100 text-amber-700',
    paid: 'bg-green-100 text-green-700',
};
const typeBadge = {
    client: { label: 'MGE → Client', cls: 'bg-emerald-100 text-emerald-700' },
    subcon: { label: 'Subcon → MGE', cls: 'bg-orange-100 text-orange-700' },
};
const today = () => new Date().toISOString().split('T')[0];
const emptyForm = {
    type: 'client', project_id: '', party_name: '', invoice_no: '', invoice_date: today(),
    amount: '', status: 'submitted', client_approved_date: '', notes: '',
};

export default function ProjectInvoices() {
    const { can } = useAuth();
    const canEdit = can('projects.edit');
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [pagination, setPagination] = useState({});
    const [projects, setProjects] = useState([]);
    const [summary, setSummary] = useState({ total_client: 0, total_subcon: 0, profit: 0 });

    const [projectFilter, setProjectFilter] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [search, setSearch] = useState('');

    const [showForm, setShowForm] = useState(false);
    const [editId, setEditId] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [files, setFiles] = useState([]);
    const [existingFiles, setExistingFiles] = useState([]);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    const fetchInvoices = useCallback(async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (projectFilter) params.project_id = projectFilter;
            if (typeFilter) params.type = typeFilter;
            if (statusFilter) params.status = statusFilter;
            if (search) params.search = search;
            const res = await projectInvoiceService.list(params);
            setInvoices(res.data?.data || []);
            setPagination(res.data || {});
        } catch {
            setInvoices([]);
        } finally {
            setLoading(false);
        }
    }, [projectFilter, typeFilter, statusFilter, search]);

    const fetchSummary = useCallback(async () => {
        try {
            const params = projectFilter ? { project_id: projectFilter } : {};
            const res = await projectInvoiceService.summary(params);
            setSummary(res.data || { total_client: 0, total_subcon: 0, profit: 0 });
        } catch { /* ignore */ }
    }, [projectFilter]);

    useEffect(() => {
        const t = setTimeout(() => fetchInvoices(), 300);
        return () => clearTimeout(t);
    }, [fetchInvoices]);

    useEffect(() => { fetchSummary(); }, [fetchSummary]);

    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
    }, []);

    const refresh = () => { fetchInvoices(); fetchSummary(); };

    const openCreate = () => { setEditId(null); setForm({ ...emptyForm, project_id: projectFilter || '' }); setFiles([]); setExistingFiles([]); setErrors({}); setShowForm(true); };
    const openEdit = (inv) => {
        setEditId(inv.id);
        setForm({
            type: inv.type || 'client', project_id: inv.project_id, party_name: inv.party_name || '',
            invoice_no: inv.invoice_no || '', invoice_date: inv.invoice_date || today(),
            amount: inv.amount || '', status: inv.status || 'submitted',
            client_approved_date: inv.client_approved_date || '', notes: inv.notes || '',
        });
        setFiles([]); setExistingFiles(inv.files || []); setErrors({}); setShowForm(true);
    };

    const submit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const fd = new FormData();
            fd.append('project_id', form.project_id);
            fd.append('type', form.type);
            if (form.party_name) fd.append('party_name', form.party_name);
            if (form.invoice_no) fd.append('invoice_no', form.invoice_no);
            fd.append('invoice_date', form.invoice_date);
            fd.append('amount', form.amount);
            fd.append('status', form.status);
            if (form.type === 'client' && form.client_approved_date) fd.append('client_approved_date', form.client_approved_date);
            if (form.notes) fd.append('notes', form.notes);
            files.forEach((f) => fd.append('files[]', f));

            if (editId) await projectInvoiceService.update(editId, fd);
            else await projectInvoiceService.create(fd);
            toast.success(editId ? 'Invoice updated' : 'Invoice created');
            setShowForm(false);
            refresh();
        } catch (err) {
            if (err.response?.status === 422) { setErrors(err.response.data.errors || {}); toast.error('Please fix the highlighted fields'); }
            else toast.error(err.response?.data?.message || 'Failed to save invoice');
        } finally {
            setSaving(false);
        }
    };

    const remove = async (inv) => {
        if (!window.confirm(`Delete invoice ${inv.invoice_no || `#${inv.id}`}?`)) return;
        try { await projectInvoiceService.remove(inv.id); toast.success('Invoice deleted'); refresh(); }
        catch (err) { toast.error(err.response?.data?.message || 'Failed to delete'); }
    };

    const deleteExistingFile = async (fileId) => {
        try { await projectInvoiceService.deleteFile(fileId); setExistingFiles((p) => p.filter((f) => f.id !== fileId)); toast.success('File removed'); }
        catch { toast.error('Failed to remove file'); }
    };

    const fieldClass = (name) =>
        `w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1 ${errors[name] ? 'border-red-300 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500'}`;

    const profitPositive = (summary.profit ?? 0) >= 0;

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Invoice Tracking</h1>
                    <p className="text-sm text-gray-500">Track client billing (MGE → Client) vs subcontractor cost (Subcon → MGE) and project profit</p>
                </div>
                {canEdit && (
                    <button onClick={openCreate} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                        <HiOutlinePlus className="h-5 w-5" /> New Invoice
                    </button>
                )}
            </div>

            {/* Summary / profit cards */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center justify-between">
                        <p className="text-sm font-medium text-gray-500">Invoiced to Client</p>
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50"><HiOutlineArrowDown className="h-5 w-5 text-emerald-600" /></span>
                    </div>
                    <p className="mt-2 text-2xl font-bold text-emerald-700">{fmt(summary.total_client)}</p>
                    <p className="text-xs text-gray-400">{summary.client_count || 0} invoice(s) · revenue from client</p>
                </div>
                <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center justify-between">
                        <p className="text-sm font-medium text-gray-500">Paid to Subcon</p>
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-orange-50"><HiOutlineArrowUp className="h-5 w-5 text-orange-600" /></span>
                    </div>
                    <p className="mt-2 text-2xl font-bold text-orange-700">{fmt(summary.total_subcon)}</p>
                    <p className="text-xs text-gray-400">{summary.subcon_count || 0} invoice(s) · cost to subcon</p>
                </div>
                <div className={`rounded-xl p-5 shadow-sm ring-1 ${profitPositive ? 'bg-primary-600 ring-primary-700' : 'bg-red-600 ring-red-700'}`}>
                    <div className="flex items-center justify-between">
                        <p className="text-sm font-medium text-white/80">Net Profit</p>
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15"><HiOutlineTrendingUp className="h-5 w-5 text-white" /></span>
                    </div>
                    <p className="mt-2 text-2xl font-bold text-white">{fmt(summary.profit)}</p>
                    <p className="text-xs text-white/70">Client invoiced − Subcon cost{projectFilter ? ' (this project)' : ' (all projects)'}</p>
                </div>
            </div>

            {/* Filters */}
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
                <div className="relative max-w-xs flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input type="text" placeholder="Search invoice no / party..." value={search} onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                </div>
                <select value={projectFilter} onChange={(e) => setProjectFilter(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">All Projects</option>
                    {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
                <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="approved">Approved</option>
                    <option value="paid">Paid</option>
                </select>
            </div>

            {/* Type tabs */}
            <div className="mb-4 inline-flex rounded-lg bg-gray-100 p-1">
                {[{ k: '', l: 'All' }, { k: 'client', l: 'MGE → Client' }, { k: 'subcon', l: 'Subcon → MGE' }].map((t) => (
                    <button key={t.k} onClick={() => setTypeFilter(t.k)}
                        className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${typeFilter === t.k ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}>
                        {t.l}
                    </button>
                ))}
            </div>

            {loading ? <LoadingSpinner /> : invoices.length === 0 ? (
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
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Invoice No</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Party</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Amount</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Files</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {invoices.map((inv) => (
                                    <tr key={inv.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs font-medium ${typeBadge[inv.type]?.cls}`}>{typeBadge[inv.type]?.label}</span></td>
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">{inv.invoice_no || '-'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{inv.project?.name || '-'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{inv.party_name || (inv.type === 'client' ? '(Client)' : '(Subcon)')}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-gray-900">{fmt(inv.amount)}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[inv.status]}`}>{inv.status}</span></td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{inv.invoice_date}</td>
                                        <td className="px-4 py-3">
                                            {inv.files?.length ? (
                                                <span className="inline-flex items-center gap-1 text-xs text-gray-500"><HiOutlinePaperClip className="h-3.5 w-3.5" />{inv.files.length}</span>
                                            ) : <span className="text-xs text-gray-300">—</span>}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {inv.files?.map((f) => (
                                                    <a key={f.id} href={projectInvoiceService.getFileDownloadUrl(f.id)} target="_blank" rel="noreferrer" title={f.file_name}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600"><HiOutlineDownload className="h-4 w-4" /></a>
                                                ))}
                                                {canEdit && <button onClick={() => openEdit(inv)} title="Edit" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><HiOutlinePencil className="h-4 w-4" /></button>}
                                                {canEdit && <button onClick={() => remove(inv)} title="Delete" className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineTrash className="h-4 w-4" /></button>}
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
                                    <button key={page} onClick={() => fetchInvoices(page)} className={`rounded px-3 py-1 text-sm ${page === pagination.current_page ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}>{page}</button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Create / Edit modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowForm(false)}>
                    <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">{editId ? 'Edit Invoice' : 'New Invoice'}</h3>
                        <form onSubmit={submit} className="space-y-4">
                            {/* Type selector */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Invoice Type *</label>
                                <div className="grid grid-cols-2 gap-2">
                                    {[{ k: 'client', l: 'MGE → Client', d: 'Bill the client (revenue, with IPC)' }, { k: 'subcon', l: 'Subcon → MGE', d: 'Invoice from subcontractor (cost)' }].map((t) => (
                                        <button type="button" key={t.k} onClick={() => setForm((p) => ({ ...p, type: t.k }))}
                                            className={`rounded-lg border p-3 text-left transition-all ${form.type === t.k ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500' : 'border-gray-200 hover:border-primary-300'}`}>
                                            <div className="text-sm font-bold text-gray-900">{t.l}</div>
                                            <div className="text-[11px] text-gray-500">{t.d}</div>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Project *</label>
                                <select value={form.project_id} onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))} required className={fieldClass('project_id')}>
                                    <option value="">Select project...</option>
                                    {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                </select>
                                {errors.project_id && <p className="mt-1 text-xs text-red-500">{errors.project_id[0]}</p>}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">{form.type === 'subcon' ? 'Subcontractor Name' : 'Client Name (optional)'}</label>
                                <input type="text" value={form.party_name} onChange={(e) => setForm((p) => ({ ...p, party_name: e.target.value }))} className={fieldClass('party_name')}
                                    placeholder={form.type === 'subcon' ? 'e.g. ABC Construction Sdn Bhd' : 'Defaults to project client'} />
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Invoice No</label>
                                    <input type="text" value={form.invoice_no} onChange={(e) => setForm((p) => ({ ...p, invoice_no: e.target.value }))} className={fieldClass('invoice_no')} placeholder="INV-001" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Amount (RM) *</label>
                                    <input type="number" step="0.01" min="0" value={form.amount} onChange={(e) => setForm((p) => ({ ...p, amount: e.target.value }))} required className={fieldClass('amount')} placeholder="0.00" />
                                    {errors.amount && <p className="mt-1 text-xs text-red-500">{errors.amount[0]}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Invoice Date *</label>
                                    <input type="date" value={form.invoice_date} onChange={(e) => setForm((p) => ({ ...p, invoice_date: e.target.value }))} required className={fieldClass('invoice_date')} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Status *</label>
                                    <select value={form.status} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))} className={fieldClass('status')}>
                                        <option value="draft">Draft</option>
                                        <option value="submitted">Submitted</option>
                                        <option value="approved">Approved</option>
                                        <option value="paid">Paid</option>
                                    </select>
                                </div>
                            </div>

                            {form.type === 'client' && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Date Approved by Client</label>
                                    <input type="date" value={form.client_approved_date} onChange={(e) => setForm((p) => ({ ...p, client_approved_date: e.target.value }))} className={fieldClass('client_approved_date')} />
                                </div>
                            )}

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <textarea rows={2} value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} className={fieldClass('notes')} />
                            </div>

                            {/* Attachments (IPC) */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">{form.type === 'client' ? 'IPC / Supporting Documents' : 'Invoice Documents'}</label>
                                <input type="file" multiple onChange={(e) => setFiles([...e.target.files])}
                                    className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100" />
                                {files.length > 0 && (
                                    <ul className="mt-2 space-y-1">
                                        {files.map((f, i) => <li key={i} className="flex items-center gap-2 text-xs text-gray-500"><HiOutlinePaperClip className="h-3.5 w-3.5" />{f.name}</li>)}
                                    </ul>
                                )}
                                {existingFiles.length > 0 && (
                                    <ul className="mt-2 space-y-1">
                                        {existingFiles.map((f) => (
                                            <li key={f.id} className="flex items-center justify-between gap-2 rounded bg-gray-50 px-2 py-1 text-xs text-gray-600">
                                                <a href={projectInvoiceService.getFileDownloadUrl(f.id)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700"><HiOutlineDownload className="h-3.5 w-3.5" />{f.file_name}</a>
                                                <button type="button" onClick={() => deleteExistingFile(f.id)} className="text-gray-400 hover:text-red-600"><HiOutlineTrash className="h-3.5 w-3.5" /></button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : editId ? 'Update Invoice' : 'Create Invoice'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
