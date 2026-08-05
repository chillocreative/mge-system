import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import contractService from '@/services/contractService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineDocumentText,
    HiOutlinePencil,
    HiOutlineTrash,
    HiOutlineEye,
    HiOutlineX,
} from 'react-icons/hi';

const statusColors = {
    active: 'bg-green-100 text-green-700',
    completed: 'bg-blue-100 text-blue-700',
    terminated: 'bg-red-100 text-red-700',
};

const statuses = ['active', 'completed', 'terminated'];

function formatCurrency(val) {
    if (val === null || val === undefined || val === '') return '-';
    return 'RM ' + Number(val).toLocaleString('en-MY', { minimumFractionDigits: 2 });
}

const emptyPic = () => ({ name: '', email: '', phone: '', company: '', designation: '' });

function emptyForm() {
    return {
        project_id: '',
        title: '',
        contract_no: '',
        contract_value: '',
        start_date: '',
        end_date: '',
        pics: [emptyPic()],
        status: 'active',
        notes: '',
    };
}

export default function Contracts() {
    const { can } = useAuth();
    const navigate = useNavigate();
    const canEdit = can('projects.edit');

    const [contracts, setContracts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [projectFilter, setProjectFilter] = useState('');
    const [pagination, setPagination] = useState({});
    const [projects, setProjects] = useState([]);

    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(emptyForm());

    const fetchContracts = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            if (projectFilter) params.project_id = projectFilter;
            const res = await contractService.list(params);
            setContracts(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setContracts([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchContracts(), 400);
        return () => clearTimeout(timer);
    }, [search, statusFilter, projectFilter]);

    useEffect(() => {
        fetchContracts();
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
    }, []);

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm());
        setShowForm(true);
    };

    const openEdit = (c) => {
        setEditing(c);
        setForm({
            project_id: c.project_id || '',
            title: c.title || '',
            contract_no: c.contract_no || '',
            contract_value: c.contract_value ?? '',
            start_date: c.start_date ? String(c.start_date).split('T')[0] : '',
            end_date: c.end_date ? String(c.end_date).split('T')[0] : '',
            pics: c.pics?.length
                ? c.pics.map((p) => ({ name: p.name || '', email: p.email || '', phone: p.phone || '', company: p.company || '', designation: p.designation || '' }))
                : [emptyPic()],
            status: c.status || 'active',
            notes: c.notes || '',
        });
        setShowForm(true);
    };

    const addPic = () => setForm((p) => ({ ...p, pics: [...p.pics, emptyPic()] }));
    const removePic = (idx) => setForm((p) => ({ ...p, pics: p.pics.filter((_, i) => i !== idx) }));
    const updatePic = (idx, field, value) => setForm((p) => ({ ...p, pics: p.pics.map((pic, i) => i === idx ? { ...pic, [field]: value } : pic) }));

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const fd = new FormData();
            fd.append('project_id', form.project_id);
            fd.append('title', form.title);
            fd.append('status', form.status);
            if (form.contract_no) fd.append('contract_no', form.contract_no);
            if (form.contract_value !== '') fd.append('contract_value', form.contract_value);
            if (form.start_date) fd.append('start_date', form.start_date);
            if (form.end_date) fd.append('end_date', form.end_date);
            if (form.notes) fd.append('notes', form.notes);
            // Correspondence PICs (only rows with a name); flag tells the API the list is authoritative
            fd.append('pics_sync', '1');
            form.pics.filter((p) => p.name?.trim()).forEach((pic, i) => {
                fd.append(`pics[${i}][name]`, pic.name);
                if (pic.email) fd.append(`pics[${i}][email]`, pic.email);
                if (pic.phone) fd.append(`pics[${i}][phone]`, pic.phone);
                if (pic.company) fd.append(`pics[${i}][company]`, pic.company);
                if (pic.designation) fd.append(`pics[${i}][designation]`, pic.designation);
            });

            if (editing) {
                await contractService.update(editing.id, fd);
                toast.success('Contract updated');
            } else {
                await contractService.create(fd);
                toast.success('Contract created');
            }
            setShowForm(false);
            fetchContracts();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save contract');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this contract?')) return;
        try {
            await contractService.delete(id);
            toast.success('Contract deleted');
            fetchContracts();
        } catch {
            toast.error('Failed to delete contract');
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Contracts</h1>
                    <p className="text-sm text-gray-500">Project contracts, dates and correspondence PIC</p>
                </div>
                {canEdit && (
                    <button
                        onClick={openCreate}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        New Contract
                    </button>
                )}
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search title, contract no, PIC..."
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
            ) : contracts.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineDocumentText className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No contracts found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Title / Contract No</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Value</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Start</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">End</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">PIC</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {contracts.map((c) => (
                                    <tr key={c.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm text-gray-600">{c.project?.name || '-'}</td>
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">{c.title}</p>
                                            {c.contract_no && <p className="text-xs text-gray-500">{c.contract_no}</p>}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900">
                                            {formatCurrency(c.contract_value)}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{c.start_date ? String(c.start_date).split('T')[0] : '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{c.end_date ? String(c.end_date).split('T')[0] : '-'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">
                                            {c.pics?.length
                                                ? <>{c.pics[0].name}{c.pics.length > 1 && <span className="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500">+{c.pics.length - 1}</span>}</>
                                                : (c.pic_name || '-')}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[c.status] || 'bg-gray-100 text-gray-600'}`}>
                                                {c.status}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    onClick={() => navigate(`/projects/contracts/${c.id}`)}
                                                    className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                    title="View"
                                                >
                                                    <HiOutlineEye className="h-4 w-4" />
                                                </button>
                                                {canEdit && (
                                                    <>
                                                        <button
                                                            onClick={() => openEdit(c)}
                                                            className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600"
                                                            title="Edit"
                                                        >
                                                            <HiOutlinePencil className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            onClick={() => handleDelete(c.id)}
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
                                        onClick={() => fetchContracts(page)}
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
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowForm(false)}>
                    <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">{editing ? 'Edit Contract' : 'New Contract'}</h3>
                            <button onClick={() => setShowForm(false)} className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                <HiOutlineX className="h-5 w-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Project *</label>
                                    <select
                                        value={form.project_id}
                                        onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))}
                                        required
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        <option value="">Select Project</option>
                                        {projects.map((p) => (
                                            <option key={p.id} value={p.id}>{p.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                    <select
                                        value={form.status}
                                        onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        {statuses.map((s) => (
                                            <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
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
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Contract No</label>
                                    <input
                                        type="text"
                                        value={form.contract_no}
                                        onChange={(e) => setForm((p) => ({ ...p, contract_no: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Contract Value (RM)</label>
                                    <input
                                        type="number"
                                        value={form.contract_value}
                                        onChange={(e) => setForm((p) => ({ ...p, contract_value: e.target.value }))}
                                        min="0"
                                        step="0.01"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Start Date</label>
                                    <input
                                        type="date"
                                        value={form.start_date}
                                        onChange={(e) => setForm((p) => ({ ...p, start_date: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">End Date</label>
                                    <input
                                        type="date"
                                        value={form.end_date}
                                        onChange={(e) => setForm((p) => ({ ...p, end_date: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                            </div>

                            <div className="rounded-lg border border-gray-100 bg-gray-50 p-4">
                                <div className="mb-3 flex items-center justify-between">
                                    <p className="text-xs font-semibold uppercase text-gray-500">Correspondence PICs</p>
                                    <button type="button" onClick={addPic} className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                                        <HiOutlinePlus className="h-4 w-4" /> Add PIC
                                    </button>
                                </div>
                                <div className="space-y-3">
                                    {form.pics.map((pic, i) => (
                                        <div key={i} className="rounded-lg border border-gray-200 bg-white p-3">
                                            <div className="mb-2 flex items-center justify-between">
                                                <span className="text-xs font-semibold text-gray-400">PIC #{i + 1}</span>
                                                {form.pics.length > 1 && (
                                                    <button type="button" onClick={() => removePic(i)} className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove PIC">
                                                        <HiOutlineX className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-medium text-gray-600">Name</label>
                                                    <input type="text" value={pic.name} onChange={(e) => updatePic(i, 'name', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-medium text-gray-600">Company</label>
                                                    <input type="text" value={pic.company} onChange={(e) => updatePic(i, 'company', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-medium text-gray-600">Designation</label>
                                                    <input type="text" value={pic.designation} onChange={(e) => updatePic(i, 'designation', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-medium text-gray-600">Email</label>
                                                    <input type="email" value={pic.email} onChange={(e) => updatePic(i, 'email', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-medium text-gray-600">Phone</label>
                                                    <input type="text" value={pic.phone} onChange={(e) => updatePic(i, 'phone', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
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
                                    {saving ? 'Saving...' : editing ? 'Update Contract' : 'Create Contract'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

        </div>
    );
}
