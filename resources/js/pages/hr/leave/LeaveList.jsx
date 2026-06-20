import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import apiClient from '@/services/apiClient';
import leaveService from '@/services/leaveService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineCalendar,
    HiOutlineBan,
    HiOutlineCog,
    HiOutlineX,
    HiOutlinePencil,
    HiOutlineTrash,
} from 'react-icons/hi';

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-600',
};

const emptyTypeForm = { name: '', code: '', default_days_per_year: 0, is_paid: true, requires_attachment: false, is_active: true };

export default function LeaveList() {
    const { can } = useAuth();
    const [requests, setRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('');
    const [employeeFilter, setEmployeeFilter] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [pagination, setPagination] = useState({});
    const [employees, setEmployees] = useState([]);
    const [types, setTypes] = useState([]);
    const [showTypes, setShowTypes] = useState(false);
    const [typeForm, setTypeForm] = useState(emptyTypeForm);
    const [typeEditId, setTypeEditId] = useState(null);
    const [typeSaving, setTypeSaving] = useState(false);

    const fetchTypes = async () => {
        try {
            const res = await leaveService.listTypes();
            setTypes(res.data || []);
        } catch { /* ignore */ }
    };

    const fetchRequests = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (statusFilter) params.status = statusFilter;
            if (employeeFilter) params.employee_id = employeeFilter;
            if (typeFilter) params.leave_type_id = typeFilter;
            const res = await leaveService.list(params);
            setRequests(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setRequests([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchRequests();
    }, [statusFilter, employeeFilter, typeFilter]);

    useEffect(() => {
        apiClient.get('/employees', { params: { per_page: 100 } })
            .then((r) => setEmployees(r.data?.data?.data || r.data?.data || []))
            .catch(() => {});
        fetchTypes();
    }, []);

    const handleCancel = async (id) => {
        if (!confirm('Cancel this leave request?')) return;
        try {
            await leaveService.cancel(id);
            toast.success('Leave request cancelled');
            fetchRequests();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to cancel');
        }
    };

    // ── Manage leave types ──
    const openTypeCreate = () => { setTypeEditId(null); setTypeForm(emptyTypeForm); };
    const openTypeEdit = (t) => {
        setTypeEditId(t.id);
        setTypeForm({
            name: t.name, code: t.code, default_days_per_year: t.default_days_per_year,
            is_paid: t.is_paid, requires_attachment: t.requires_attachment, is_active: t.is_active,
        });
    };

    const saveType = async (e) => {
        e.preventDefault();
        setTypeSaving(true);
        const payload = {
            name: typeForm.name,
            code: typeForm.code,
            default_days_per_year: Number(typeForm.default_days_per_year) || 0,
            is_paid: typeForm.is_paid,
            requires_attachment: typeForm.requires_attachment,
            is_active: typeForm.is_active,
        };
        try {
            if (typeEditId) {
                await leaveService.updateType(typeEditId, payload);
                toast.success('Leave type updated');
            } else {
                await leaveService.createType(payload);
                toast.success('Leave type added');
            }
            setTypeForm(emptyTypeForm); setTypeEditId(null);
            await fetchTypes();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save leave type');
        } finally {
            setTypeSaving(false);
        }
    };

    const removeType = async (t) => {
        if (!confirm(`Delete leave type "${t.name}"?`)) return;
        try {
            await leaveService.deleteType(t.id);
            toast.success('Leave type deleted');
            fetchTypes();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete leave type');
        }
    };

    const toggleTypeActive = async (t) => {
        try {
            await leaveService.updateType(t.id, { is_active: !t.is_active });
            fetchTypes();
        } catch {
            toast.error('Failed to update leave type');
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Leave Requests</h1>
                    <p className="text-sm text-gray-500">View and manage employee leave applications</p>
                </div>
                <div className="flex gap-2">
                    {can('leave.manage') && (
                        <button
                            onClick={() => { setShowTypes(true); openTypeCreate(); }}
                            className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            <HiOutlineCog className="h-5 w-5" />
                            Manage Types
                        </button>
                    )}
                    {can('leave.request') && (
                        <Link
                            to="/hr/leave/apply"
                            className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                        >
                            <HiOutlinePlus className="h-5 w-5" />
                            Apply Leave
                        </Link>
                    )}
                </div>
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <select
                    value={employeeFilter}
                    onChange={(e) => setEmployeeFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Employees</option>
                    {employees.map((emp) => (
                        <option key={emp.id} value={emp.id}>{emp.full_name || `${emp.first_name} ${emp.last_name}`}</option>
                    ))}
                </select>
                <select
                    value={typeFilter}
                    onChange={(e) => setTypeFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Types</option>
                    {types.map((t) => (
                        <option key={t.id} value={t.id}>{t.name}</option>
                    ))}
                </select>
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : requests.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineCalendar className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No leave requests found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Employee</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Dates</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Days</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {requests.map((req) => (
                                    <tr key={req.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">{req.employee?.full_name || `${req.employee?.first_name ?? ''} ${req.employee?.last_name ?? ''}`}</p>
                                            {req.employee?.employee_no && <p className="text-xs text-gray-500">{req.employee.employee_no}</p>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                {req.leave_type?.name || '-'}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                            {req.start_date} &rarr; {req.end_date}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900">{req.days_count}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[req.status]}`}>
                                                {req.status}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            {(req.status === 'pending' || req.status === 'approved') && can('leave.request') && (
                                                <button
                                                    onClick={() => handleCancel(req.id)}
                                                    className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                    title="Cancel"
                                                >
                                                    <HiOutlineBan className="h-4 w-4" />
                                                </button>
                                            )}
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
                                        onClick={() => fetchRequests(page)}
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

            {showTypes && (
                <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 py-8" onClick={() => setShowTypes(false)}>
                    <div className="mx-4 w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">Manage Leave Types</h3>
                            <button onClick={() => setShowTypes(false)} className="rounded p-1 text-gray-400 hover:bg-gray-100"><HiOutlineX className="h-5 w-5" /></button>
                        </div>

                        {/* Add / edit form */}
                        <form onSubmit={saveType} className="mb-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <p className="mb-3 text-xs font-bold uppercase text-gray-500">{typeEditId ? 'Edit Type' : 'Add New Type'}</p>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Name *</label>
                                    <input type="text" value={typeForm.name} onChange={(e) => setTypeForm((p) => ({ ...p, name: e.target.value }))} required maxLength={255} placeholder="e.g. Annual" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Code *</label>
                                    <input type="text" value={typeForm.code} onChange={(e) => setTypeForm((p) => ({ ...p, code: e.target.value }))} required maxLength={20} placeholder="e.g. AL" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-700">Days / Year *</label>
                                    <input type="number" min="0" value={typeForm.default_days_per_year} onChange={(e) => setTypeForm((p) => ({ ...p, default_days_per_year: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                            </div>
                            <div className="mt-3 flex flex-wrap gap-4">
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" checked={typeForm.is_paid} onChange={(e) => setTypeForm((p) => ({ ...p, is_paid: e.target.checked }))} className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    Paid leave
                                </label>
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" checked={typeForm.requires_attachment} onChange={(e) => setTypeForm((p) => ({ ...p, requires_attachment: e.target.checked }))} className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    Requires attachment
                                </label>
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" checked={typeForm.is_active} onChange={(e) => setTypeForm((p) => ({ ...p, is_active: e.target.checked }))} className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    Active (selectable)
                                </label>
                            </div>
                            <div className="mt-3 flex justify-end gap-2">
                                {typeEditId && <button type="button" onClick={openTypeCreate} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">New</button>}
                                <button type="submit" disabled={typeSaving} className="rounded-lg bg-primary-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{typeSaving ? 'Saving...' : typeEditId ? 'Update Type' : 'Add Type'}</button>
                            </div>
                        </form>

                        {/* Existing types list */}
                        <div className="space-y-1.5">
                            {types.length === 0 && <p className="py-4 text-center text-sm text-gray-400">No leave types yet. Add one above.</p>}
                            {types.map((t) => (
                                <div key={t.id} className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-2">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold text-gray-900">
                                            {t.name} <span className="text-gray-400">({t.code})</span>
                                            {!t.is_active && <span className="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">inactive</span>}
                                        </p>
                                        <p className="truncate text-xs text-gray-400">
                                            {t.default_days_per_year} days/year · {t.is_paid ? 'Paid' : 'Unpaid'}{t.requires_attachment ? ' · Attachment required' : ''}
                                        </p>
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
