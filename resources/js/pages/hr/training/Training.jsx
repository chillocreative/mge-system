import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import apiClient from '@/services/apiClient';
import trainingService from '@/services/trainingService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlineAcademicCap,
    HiOutlineUserGroup,
    HiOutlineCurrencyDollar,
    HiOutlineBadgeCheck,
    HiOutlineCheckCircle,
    HiOutlineXCircle,
    HiOutlinePlus,
    HiOutlinePencil,
    HiOutlineTrash,
    HiOutlineSparkles,
} from 'react-icons/hi';

const CATEGORIES = ['Safety', 'Technical', 'Compliance', 'Soft Skills', 'Leadership', 'Certification', 'Other'];

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    scheduled: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
};

const money = (n) => 'RM ' + Number(n || 0).toLocaleString('en-MY', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

const emptyRecord = {
    employee_id: '', title: '', provider: '', category: '', training_date: '', end_date: '',
    duration_days: '', cost: '', hrdf_claimable: false, status: 'completed', notes: '',
};

function StatCard({ icon: Icon, label, value, gradient }) {
    return (
        <div className={`relative overflow-hidden rounded-2xl p-5 text-white shadow-sm ${gradient}`}>
            <div className="absolute -right-4 -top-4 opacity-20">
                <Icon className="h-24 w-24" />
            </div>
            <div className="relative">
                <Icon className="h-6 w-6 opacity-90" />
                <p className="mt-3 text-3xl font-bold tracking-tight">{value}</p>
                <p className="text-sm font-medium text-white/80">{label}</p>
            </div>
        </div>
    );
}

export default function Training() {
    const { can } = useAuth();
    const canManage = can('training.manage');
    const canApprove = can('training.approve');

    const [tab, setTab] = useState('overview');
    const [overview, setOverview] = useState({ stats: {}, staff: [] });
    const [records, setRecords] = useState([]);
    const [requests, setRequests] = useState([]);
    const [employees, setEmployees] = useState([]);
    const [loading, setLoading] = useState(true);

    // Record modal
    const [recordModal, setRecordModal] = useState(false);
    const [editId, setEditId] = useState(null);
    const [form, setForm] = useState(emptyRecord);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    // Request review modal
    const [review, setReview] = useState({ open: false, request: null, action: 'approve', notes: '' });

    const loadOverview = useCallback(() => {
        trainingService.overview().then((r) => setOverview(r.data || { stats: {}, staff: [] })).catch(() => {});
    }, []);
    const loadRecords = useCallback(() => {
        trainingService.records({ per_page: 100 }).then((r) => setRecords(r.data?.data || [])).catch(() => {});
    }, []);
    const loadRequests = useCallback(() => {
        trainingService.requests({ per_page: 100 }).then((r) => setRequests(r.data?.data || [])).catch(() => {});
    }, []);

    useEffect(() => {
        Promise.all([
            trainingService.overview().then((r) => setOverview(r.data || { stats: {}, staff: [] })).catch(() => {}),
            trainingService.records({ per_page: 100 }).then((r) => setRecords(r.data?.data || [])).catch(() => {}),
            trainingService.requests({ per_page: 100 }).then((r) => setRequests(r.data?.data || [])).catch(() => {}),
            apiClient.get('/employees', { params: { per_page: 100 } })
                .then((r) => setEmployees(r.data?.data?.data || r.data?.data || [])).catch(() => {}),
        ]).finally(() => setLoading(false));
    }, []);

    const openCreate = () => { setEditId(null); setForm(emptyRecord); setErrors({}); setRecordModal(true); };
    const openEdit = (rec) => {
        setEditId(rec.id);
        setForm({
            employee_id: String(rec.employee_id), title: rec.title || '', provider: rec.provider || '',
            category: rec.category || '', training_date: rec.training_date || '', end_date: rec.end_date || '',
            duration_days: rec.duration_days ?? '', cost: rec.cost ?? '', hrdf_claimable: !!rec.hrdf_claimable,
            status: rec.status || 'completed', notes: rec.notes || '',
        });
        setErrors({});
        setRecordModal(true);
    };

    const saveRecord = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        const payload = {
            ...form,
            duration_days: form.duration_days === '' ? null : Number(form.duration_days),
            cost: form.cost === '' ? 0 : Number(form.cost),
            end_date: form.end_date || null,
        };
        try {
            if (editId) {
                await trainingService.updateRecord(editId, payload);
                toast.success('Training record updated');
            } else {
                await trainingService.createRecord(payload);
                toast.success('Training record added');
            }
            setRecordModal(false);
            loadRecords();
            loadOverview();
        } catch (err) {
            if (err.response?.status === 422) setErrors(err.response.data?.errors || {});
            else toast.error(err.response?.data?.message || 'Failed to save record');
        } finally {
            setSaving(false);
        }
    };

    const deleteRecord = async (rec) => {
        if (!confirm(`Delete training "${rec.title}"?`)) return;
        try {
            await trainingService.deleteRecord(rec.id);
            toast.success('Record deleted');
            loadRecords();
            loadOverview();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete');
        }
    };

    const submitReview = async () => {
        const { request, action, notes } = review;
        try {
            if (action === 'approve') await trainingService.approveRequest(request.id, { review_notes: notes || null });
            else await trainingService.rejectRequest(request.id, { review_notes: notes || null });
            toast.success(`Request ${action === 'approve' ? 'approved' : 'rejected'}`);
            setReview({ open: false, request: null, action: 'approve', notes: '' });
            loadRequests();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to review request');
        }
    };

    const s = overview.stats || {};
    const tabs = [
        { id: 'overview', label: 'Overview' },
        { id: 'records', label: 'Records' },
        { id: 'requests', label: `Requests${s.pending_requests ? ` (${s.pending_requests})` : ''}` },
    ];

    if (loading) return <LoadingSpinner />;

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <HiOutlineAcademicCap className="h-7 w-7 text-primary-600" />
                        Training
                    </h1>
                    <p className="text-sm text-gray-500">Track staff training, costs, HRDF claims and requests</p>
                </div>
                {canManage && tab === 'records' && (
                    <button
                        onClick={openCreate}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" /> Add Training
                    </button>
                )}
            </div>

            {/* Stat cards */}
            <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard icon={HiOutlineUserGroup} label="Staff trained" value={`${s.trained_staff ?? 0}/${s.total_staff ?? 0}`} gradient="bg-gradient-to-br from-primary-600 to-primary-800" />
                <StatCard icon={HiOutlineSparkles} label="Total trainings" value={s.total_trainings ?? 0} gradient="bg-gradient-to-br from-indigo-500 to-indigo-700" />
                <StatCard icon={HiOutlineCurrencyDollar} label="Total cost" value={money(s.total_cost)} gradient="bg-gradient-to-br from-amber-500 to-orange-600" />
                <StatCard icon={HiOutlineBadgeCheck} label="HRDF claimable" value={s.hrdf_claimable ?? 0} gradient="bg-gradient-to-br from-emerald-500 to-teal-600" />
            </div>

            {/* Tabs */}
            <div className="mb-5 border-b border-gray-200">
                <nav className="-mb-px flex gap-6">
                    {tabs.map((t) => (
                        <button
                            key={t.id}
                            onClick={() => setTab(t.id)}
                            className={`whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium ${
                                tab === t.id
                                    ? 'border-primary-600 text-primary-700'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Overview tab */}
            {tab === 'overview' && (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-gray-900">Staff training coverage</h2>
                        <span className="text-xs text-gray-500">
                            {s.untrained_staff ?? 0} staff have not attended any training
                        </span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Staff</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Department</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Trainings</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Last training</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {overview.staff.length === 0 ? (
                                    <tr><td colSpan={5} className="px-4 py-10 text-center text-sm text-gray-400">No active staff</td></tr>
                                ) : overview.staff.map((emp) => (
                                    <tr key={emp.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">{emp.full_name}</p>
                                            {emp.employee_no && <p className="text-xs text-gray-500">{emp.employee_no}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{emp.department || '-'}</td>
                                        <td className="px-4 py-3 text-right text-sm font-medium text-gray-900">{emp.training_count}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{emp.last_training_date || '—'}</td>
                                        <td className="px-4 py-3">
                                            {emp.trained ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                                    <HiOutlineCheckCircle className="h-3.5 w-3.5" /> Trained
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                                                    <HiOutlineXCircle className="h-3.5 w-3.5" /> Not yet
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Records tab */}
            {tab === 'records' && (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Staff</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Training</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Days</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Cost</th>
                                    <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">HRDF</th>
                                    {canManage && <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {records.length === 0 ? (
                                    <tr><td colSpan={canManage ? 7 : 6} className="px-4 py-10 text-center text-sm text-gray-400">No training records yet</td></tr>
                                ) : records.map((rec) => (
                                    <tr key={rec.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">
                                            {rec.employee?.full_name || `${rec.employee?.first_name ?? ''} ${rec.employee?.last_name ?? ''}`}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="text-sm text-gray-900">{rec.title}</p>
                                            <p className="text-xs text-gray-500">{[rec.provider, rec.category].filter(Boolean).join(' · ')}</p>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{rec.training_date}</td>
                                        <td className="px-4 py-3 text-right text-sm text-gray-600">{rec.duration_days ?? '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600">{money(rec.cost)}</td>
                                        <td className="px-4 py-3 text-center">
                                            {rec.hrdf_claimable
                                                ? <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Yes</span>
                                                : <span className="text-xs text-gray-400">No</span>}
                                        </td>
                                        {canManage && (
                                            <td className="whitespace-nowrap px-4 py-3 text-right">
                                                <button onClick={() => openEdit(rec)} className="rounded p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600" title="Edit"><HiOutlinePencil className="h-4 w-4" /></button>
                                                <button onClick={() => deleteRecord(rec)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete"><HiOutlineTrash className="h-4 w-4" /></button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Requests tab */}
            {tab === 'requests' && (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Staff</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Requested Training</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Preferred</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    {canApprove && <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {requests.length === 0 ? (
                                    <tr><td colSpan={canApprove ? 5 : 4} className="px-4 py-10 text-center text-sm text-gray-400">No training requests</td></tr>
                                ) : requests.map((req) => (
                                    <tr key={req.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">
                                            {req.employee?.full_name || `${req.employee?.first_name ?? ''} ${req.employee?.last_name ?? ''}`}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="text-sm text-gray-900">{req.title}</p>
                                            {req.reason && <p className="max-w-md truncate text-xs text-gray-500">{req.reason}</p>}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{req.preferred_date || '—'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[req.status]}`}>{req.status}</span>
                                        </td>
                                        {canApprove && (
                                            <td className="whitespace-nowrap px-4 py-3 text-right">
                                                {req.status === 'pending' ? (
                                                    <div className="flex justify-end gap-1">
                                                        <button onClick={() => setReview({ open: true, request: req, action: 'approve', notes: '' })} className="rounded-lg bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700 hover:bg-green-100">Approve</button>
                                                        <button onClick={() => setReview({ open: true, request: req, action: 'reject', notes: '' })} className="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100">Reject</button>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-gray-400">Reviewed</span>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Record modal */}
            {recordModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setRecordModal(false)}>
                    <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">{editId ? 'Edit Training Record' : 'Add Training Record'}</h3>
                        <form onSubmit={saveRecord} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Staff *</label>
                                    <select required value={form.employee_id} onChange={(e) => setForm((p) => ({ ...p, employee_id: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="">Select staff</option>
                                        {employees.map((emp) => <option key={emp.id} value={emp.id}>{emp.full_name || `${emp.first_name} ${emp.last_name}`}</option>)}
                                    </select>
                                    {errors.employee_id && <p className="mt-1 text-xs text-red-500">{errors.employee_id[0]}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Training Title *</label>
                                    <input type="text" required value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                    {errors.title && <p className="mt-1 text-xs text-red-500">{errors.title[0]}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Provider</label>
                                    <input type="text" value={form.provider} onChange={(e) => setForm((p) => ({ ...p, provider: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Category</label>
                                    <select value={form.category} onChange={(e) => setForm((p) => ({ ...p, category: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="">—</option>
                                        {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Training Date *</label>
                                    <input type="date" required value={form.training_date} onChange={(e) => setForm((p) => ({ ...p, training_date: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                    {errors.training_date && <p className="mt-1 text-xs text-red-500">{errors.training_date[0]}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">End Date</label>
                                    <input type="date" value={form.end_date} min={form.training_date} onChange={(e) => setForm((p) => ({ ...p, end_date: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Duration (days)</label>
                                    <input type="number" min="0" step="0.5" value={form.duration_days} onChange={(e) => setForm((p) => ({ ...p, duration_days: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Cost (RM)</label>
                                    <input type="number" min="0" step="0.01" value={form.cost} onChange={(e) => setForm((p) => ({ ...p, cost: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                    <select value={form.status} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="completed">Completed</option>
                                        <option value="scheduled">Scheduled</option>
                                    </select>
                                </div>
                                <div className="flex items-end">
                                    <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                                        <input type="checkbox" checked={form.hrdf_claimable} onChange={(e) => setForm((p) => ({ ...p, hrdf_claimable: e.target.checked }))} className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                        HRDF claimable
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <textarea rows={2} value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setRecordModal(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : editId ? 'Update' : 'Add Record'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Review modal */}
            {review.open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setReview({ open: false, request: null, action: 'approve', notes: '' })}>
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-1 text-lg font-semibold text-gray-900">{review.action === 'approve' ? 'Approve' : 'Reject'} request</h3>
                        <p className="mb-4 text-sm text-gray-500">{review.request?.title} — {review.request?.employee?.full_name}</p>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Note (optional)</label>
                        <textarea rows={3} value={review.notes} onChange={(e) => setReview((p) => ({ ...p, notes: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <div className="mt-4 flex justify-end gap-2">
                            <button onClick={() => setReview({ open: false, request: null, action: 'approve', notes: '' })} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button onClick={submitReview} className={`rounded-lg px-5 py-2 text-sm font-semibold text-white ${review.action === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'}`}>
                                {review.action === 'approve' ? 'Approve' : 'Reject'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
