import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import financeService from '@/services/financeService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineReceiptTax,
    HiOutlineCheck,
    HiOutlineX,
    HiOutlineTrash,
} from 'react-icons/hi';

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
};

const categories = ['materials', 'labor', 'equipment', 'subcontractor', 'transport', 'permits', 'utilities', 'office', 'other'];

function formatCurrency(val) {
    return 'PKR ' + Number(val || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 });
}

export default function Expenses() {
    const { can } = useAuth();
    const [expenses, setExpenses] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [pagination, setPagination] = useState({});
    const [showForm, setShowForm] = useState(false);
    const [projects, setProjects] = useState([]);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({
        project_id: '',
        title: '',
        description: '',
        category: 'materials',
        amount: '',
        expense_date: new Date().toISOString().split('T')[0],
        vendor: '',
        receipt: null,
    });

    const fetchExpenses = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            if (categoryFilter) params.category = categoryFilter;
            const res = await financeService.listExpenses(params);
            setExpenses(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setExpenses([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchExpenses(), 400);
        return () => clearTimeout(timer);
    }, [search, statusFilter, categoryFilter]);

    useEffect(() => {
        fetchExpenses();
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const formData = new FormData();
            formData.append('title', form.title);
            formData.append('amount', form.amount);
            formData.append('expense_date', form.expense_date);
            formData.append('category', form.category);
            if (form.project_id) formData.append('project_id', form.project_id);
            if (form.description) formData.append('description', form.description);
            if (form.vendor) formData.append('vendor', form.vendor);
            if (form.receipt) formData.append('receipt', form.receipt);

            await financeService.createExpense(formData);
            toast.success('Expense recorded');
            setShowForm(false);
            setForm({ project_id: '', title: '', description: '', category: 'materials', amount: '', expense_date: new Date().toISOString().split('T')[0], vendor: '', receipt: null });
            fetchExpenses();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to create expense');
        } finally {
            setSaving(false);
        }
    };

    const handleApprove = async (id) => {
        try {
            await financeService.approveExpense(id);
            toast.success('Expense approved');
            fetchExpenses();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed');
        }
    };

    const handleReject = async (id) => {
        try {
            await financeService.rejectExpense(id);
            toast.success('Expense rejected');
            fetchExpenses();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed');
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this expense?')) return;
        try {
            await financeService.deleteExpense(id);
            toast.success('Expense deleted');
            fetchExpenses();
        } catch {
            toast.error('Failed to delete expense');
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Expenses</h1>
                    <p className="text-sm text-gray-500">Track and manage project expenses</p>
                </div>
                {can('finance.manage-budgets') && (
                    <button
                        onClick={() => setShowForm(true)}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        New Expense
                    </button>
                )}
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search expenses..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                </div>
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <select
                    value={categoryFilter}
                    onChange={(e) => setCategoryFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Categories</option>
                    {categories.map((c) => (
                        <option key={c} value={c}>{c.charAt(0).toUpperCase() + c.slice(1)}</option>
                    ))}
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : expenses.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineReceiptTax className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No expenses found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Category</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Amount</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {expenses.map((exp) => (
                                    <tr key={exp.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">{exp.title}</p>
                                            {exp.vendor && <p className="text-xs text-gray-500">{exp.vendor}</p>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                {exp.category}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{exp.project?.name || '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900">
                                            {formatCurrency(exp.amount)}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{exp.expense_date}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[exp.status]}`}>
                                                {exp.status}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {can('finance.approve-expenses') && exp.status === 'pending' && (
                                                    <>
                                                        <button
                                                            onClick={() => handleApprove(exp.id)}
                                                            className="rounded p-1.5 text-gray-400 hover:bg-green-50 hover:text-green-600"
                                                            title="Approve"
                                                        >
                                                            <HiOutlineCheck className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            onClick={() => handleReject(exp.id)}
                                                            className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                            title="Reject"
                                                        >
                                                            <HiOutlineX className="h-4 w-4" />
                                                        </button>
                                                    </>
                                                )}
                                                {can('finance.manage-budgets') && (
                                                    <button
                                                        onClick={() => handleDelete(exp.id)}
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
                                        onClick={() => fetchExpenses(page)}
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

            {/* Create Expense Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowForm(false)}>
                    <div className="mx-4 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Record Expense</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
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
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Amount *</label>
                                    <input
                                        type="number"
                                        value={form.amount}
                                        onChange={(e) => setForm((p) => ({ ...p, amount: e.target.value }))}
                                        min="0.01"
                                        step="0.01"
                                        required
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Date *</label>
                                    <input
                                        type="date"
                                        value={form.expense_date}
                                        onChange={(e) => setForm((p) => ({ ...p, expense_date: e.target.value }))}
                                        required
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Category</label>
                                    <select
                                        value={form.category}
                                        onChange={(e) => setForm((p) => ({ ...p, category: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        {categories.map((c) => (
                                            <option key={c} value={c}>{c.charAt(0).toUpperCase() + c.slice(1)}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Project</label>
                                    <select
                                        value={form.project_id}
                                        onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        <option value="">No Project</option>
                                        {projects.map((p) => (
                                            <option key={p.id} value={p.id}>{p.name}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Vendor</label>
                                <input
                                    type="text"
                                    value={form.vendor}
                                    onChange={(e) => setForm((p) => ({ ...p, vendor: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                <textarea
                                    rows={2}
                                    value={form.description}
                                    onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Receipt</label>
                                <input
                                    type="file"
                                    onChange={(e) => setForm((p) => ({ ...p, receipt: e.target.files[0] || null }))}
                                    className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100"
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
                                    {saving ? 'Saving...' : 'Record Expense'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
