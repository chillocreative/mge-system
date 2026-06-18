import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import maintenanceService from '@/services/maintenanceService';
import assetService from '@/services/assetService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineCog,
    HiOutlineCalendar,
} from 'react-icons/hi';

const VEHICLE_TYPE = 'App\\Models\\Vehicle';

const statusColors = {
    completed: 'bg-green-100 text-green-700',
    planned: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
};

function cap(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : '';
}

function maintainableLabel(log) {
    const m = log.maintainable;
    if (!m) return '—';
    if (m.registration_no) return `${m.registration_no}${m.make ? ` (${m.make})` : ''}`;
    return m.name || `#${log.maintainable_id}`;
}

export default function Maintenance() {
    const { can } = useAuth();
    const [logs, setLogs] = useState([]);
    const [upcoming, setUpcoming] = useState([]);
    const [vehicles, setVehicles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [pagination, setPagination] = useState({});
    const [showForm, setShowForm] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({
        vehicle_id: '', maintenance_type: 'preventive',
        performed_date: new Date().toISOString().split('T')[0], next_due_date: '',
        description: '', cost: '', vendor: '', performed_by: '', status: 'completed',
    });

    const fetchLogs = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            const res = await maintenanceService.list(params);
            setLogs(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setLogs([]);
        } finally {
            setLoading(false);
        }
    };

    const fetchUpcoming = async () => {
        try {
            const res = await maintenanceService.upcoming(30);
            setUpcoming(res.data || []);
        } catch { /* ignore */ }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchLogs(), 400);
        return () => clearTimeout(timer);
    }, [search, statusFilter]);

    useEffect(() => {
        fetchLogs();
        fetchUpcoming();
        assetService.listVehicles({ per_page: 100 })
            .then((r) => setVehicles(r.data?.data || []))
            .catch(() => {});
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.vehicle_id) { toast.error('Select a vehicle'); return; }
        setSaving(true);
        try {
            const payload = {
                maintainable_type: VEHICLE_TYPE,
                maintainable_id: Number(form.vehicle_id),
                maintenance_type: form.maintenance_type,
                performed_date: form.performed_date,
                next_due_date: form.next_due_date,
                description: form.description,
                cost: form.cost,
                vendor: form.vendor,
                performed_by: form.performed_by,
                status: form.status,
            };
            Object.keys(payload).forEach((k) => { if (payload[k] === '') delete payload[k]; });
            await maintenanceService.create(payload);
            toast.success('Maintenance log added');
            setShowForm(false);
            setForm({ vehicle_id: '', maintenance_type: 'preventive', performed_date: new Date().toISOString().split('T')[0], next_due_date: '', description: '', cost: '', vendor: '', performed_by: '', status: 'completed' });
            fetchLogs();
            fetchUpcoming();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to add log');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Maintenance</h1>
                    <p className="text-sm text-gray-500">Service history and upcoming maintenance for assets</p>
                </div>
                {can('maintenance.manage') && (
                    <button onClick={() => setShowForm(true)} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                        <HiOutlinePlus className="h-5 w-5" /> New Log
                    </button>
                )}
            </div>

            {/* Upcoming */}
            {upcoming.length > 0 && (
                <div className="mb-6 rounded-xl bg-amber-50 p-4 ring-1 ring-amber-200">
                    <div className="mb-2 flex items-center gap-2 text-amber-800">
                        <HiOutlineCalendar className="h-5 w-5" />
                        <h2 className="text-sm font-semibold">Upcoming Maintenance (next 30 days)</h2>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {upcoming.map((log) => (
                            <span key={log.id} className="rounded-full bg-white px-3 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-200">
                                {maintainableLabel(log)} · {cap(log.maintenance_type)} · due {log.next_due_date}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input type="text" placeholder="Search description or vendor..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                </div>
                <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">All Statuses</option>
                    <option value="planned">Planned</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : logs.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineCog className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No maintenance records</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Asset</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Description</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Performed</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Next Due</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Cost</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {logs.map((log) => (
                                    <tr key={log.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">{maintainableLabel(log)}</td>
                                        <td className="px-4 py-3"><span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{cap(log.maintenance_type)}</span></td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{log.description}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{log.performed_date}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{log.next_due_date || '-'}</td>
                                        <td className="px-4 py-3 text-right text-sm font-medium text-gray-900">{log.cost ? `RM ${Number(log.cost).toLocaleString('en-MY', { minimumFractionDigits: 2 })}` : '-'}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[log.status]}`}>{cap(log.status)}</span></td>
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
                                    <button key={page} onClick={() => fetchLogs(page)} className={`rounded px-3 py-1 text-sm ${page === pagination.current_page ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}>{page}</button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* New Log Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowForm(false)}>
                    <div className="mx-4 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">New Maintenance Log</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Vehicle *</label>
                                <select value={form.vehicle_id} onChange={(e) => setForm((p) => ({ ...p, vehicle_id: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                    <option value="">Select vehicle...</option>
                                    {vehicles.map((v) => <option key={v.id} value={v.id}>{v.registration_no} — {v.make}{v.model ? ` ${v.model}` : ''}</option>)}
                                </select>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Type</label>
                                    <select value={form.maintenance_type} onChange={(e) => setForm((p) => ({ ...p, maintenance_type: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="preventive">Preventive</option>
                                        <option value="corrective">Corrective</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                    <select value={form.status} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="completed">Completed</option>
                                        <option value="planned">Planned</option>
                                        <option value="in_progress">In Progress</option>
                                    </select>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Performed Date *</label>
                                    <input type="date" value={form.performed_date} onChange={(e) => setForm((p) => ({ ...p, performed_date: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Next Due Date</label>
                                    <input type="date" value={form.next_due_date} onChange={(e) => setForm((p) => ({ ...p, next_due_date: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Description *</label>
                                <textarea rows={2} value={form.description} onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Cost (RM)</label>
                                    <input type="number" step="0.01" value={form.cost} onChange={(e) => setForm((p) => ({ ...p, cost: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Vendor</label>
                                    <input type="text" value={form.vendor} onChange={(e) => setForm((p) => ({ ...p, vendor: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Performed By</label>
                                <input type="text" value={form.performed_by} onChange={(e) => setForm((p) => ({ ...p, performed_by: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : 'Add Log'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
