import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import assetService from '@/services/assetService';
import staffService from '@/services/staffService';
import inventoryService from '@/services/inventoryService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineTruck,
    HiOutlineExclamation,
    HiOutlineCube,
    HiOutlineChevronRight,
} from 'react-icons/hi';

const statusColors = {
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-100 text-gray-600',
    disposed: 'bg-red-100 text-red-700',
};

const types = ['car', 'van', 'truck', 'lorry', 'machinery', 'other'];

function cap(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
}

export default function Vehicles() {
    const { can } = useAuth();
    const [vehicles, setVehicles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [pagination, setPagination] = useState({});
    const [showForm, setShowForm] = useState(false);
    const [employees, setEmployees] = useState([]);
    const [saving, setSaving] = useState(false);
    const [dashboard, setDashboard] = useState(null);
    const [lowStockCount, setLowStockCount] = useState(0);
    const [form, setForm] = useState({
        registration_no: '',
        make: '',
        model: '',
        year: '',
        type: 'car',
        purchase_date: '',
        current_value: '',
        assigned_to: '',
        status: 'active',
        notes: '',
    });

    const fetchVehicles = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            if (typeFilter) params.type = typeFilter;
            const res = await assetService.listVehicles(params);
            setVehicles(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setVehicles([]);
        } finally {
            setLoading(false);
        }
    };

    const fetchSummary = async () => {
        try {
            const res = await assetService.getExpiring(60);
            setDashboard(res.data || null);
        } catch { /* ignore */ }
        try {
            const res = await inventoryService.getLowStock();
            setLowStockCount((res.data || []).length);
        } catch { /* ignore */ }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchVehicles(), 400);
        return () => clearTimeout(timer);
    }, [search, statusFilter, typeFilter]);

    useEffect(() => {
        fetchVehicles();
        fetchSummary();
        staffService.list({ per_page: 100, status: 'active' })
            .then((r) => setEmployees(r.data?.data || []))
            .catch(() => {});
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = { ...form };
            Object.keys(payload).forEach((k) => {
                if (payload[k] === '') delete payload[k];
            });
            await assetService.createVehicle(payload);
            toast.success('Vehicle added');
            setShowForm(false);
            setForm({ registration_no: '', make: '', model: '', year: '', type: 'car', purchase_date: '', current_value: '', assigned_to: '', status: 'active', notes: '' });
            fetchVehicles();
            fetchSummary();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to add vehicle');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Vehicles &amp; Assets</h1>
                    <p className="text-sm text-gray-500">Manage company vehicles, road tax, insurance and permits</p>
                </div>
                {can('assets.manage') && (
                    <button
                        onClick={() => setShowForm(true)}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        New Vehicle
                    </button>
                )}
            </div>

            {/* Summary row */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center gap-3">
                        <span className="rounded-lg bg-primary-50 p-2 text-primary-600"><HiOutlineTruck className="h-6 w-6" /></span>
                        <div>
                            <p className="text-xs text-gray-500">Total Vehicles</p>
                            <p className="text-xl font-bold text-gray-900">{dashboard?.total_vehicles ?? '–'}</p>
                        </div>
                    </div>
                </div>
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center gap-3">
                        <span className="rounded-lg bg-amber-50 p-2 text-amber-600"><HiOutlineExclamation className="h-6 w-6" /></span>
                        <div>
                            <p className="text-xs text-gray-500">Road Tax Expiring (60d)</p>
                            <p className="text-xl font-bold text-gray-900">{dashboard?.expiring_road_tax ?? '–'}</p>
                        </div>
                    </div>
                </div>
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center gap-3">
                        <span className="rounded-lg bg-red-50 p-2 text-red-600"><HiOutlineExclamation className="h-6 w-6" /></span>
                        <div>
                            <p className="text-xs text-gray-500">Insurance Expiring (60d)</p>
                            <p className="text-xl font-bold text-gray-900">{dashboard?.expiring_insurance ?? '–'}</p>
                        </div>
                    </div>
                </div>
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center gap-3">
                        <span className="rounded-lg bg-orange-50 p-2 text-orange-600"><HiOutlineCube className="h-6 w-6" /></span>
                        <div>
                            <p className="text-xs text-gray-500">Low-stock Items</p>
                            <p className="text-xl font-bold text-gray-900">{lowStockCount}</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search registration or make..."
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
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="disposed">Disposed</option>
                </select>
                <select
                    value={typeFilter}
                    onChange={(e) => setTypeFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Types</option>
                    {types.map((t) => <option key={t} value={t}>{cap(t)}</option>)}
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : vehicles.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineTruck className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No vehicles found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Registration</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Make / Model</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Assigned To</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {vehicles.map((v) => (
                                    <tr key={v.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <Link to={`/assets/vehicles/${v.id}`} className="text-sm font-medium text-primary-700 hover:underline">
                                                {v.registration_no}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700">
                                            {v.make}{v.model ? ` ${v.model}` : ''}{v.year ? ` (${v.year})` : ''}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{cap(v.type)}</span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{v.assigned_to?.full_name || '-'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[v.status]}`}>{v.status}</span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link to={`/assets/vehicles/${v.id}`} className="inline-flex items-center text-gray-400 hover:text-primary-600">
                                                <HiOutlineChevronRight className="h-5 w-5" />
                                            </Link>
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
                                    <button
                                        key={page}
                                        onClick={() => fetchVehicles(page)}
                                        className={`rounded px-3 py-1 text-sm ${page === pagination.current_page ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}
                                    >
                                        {page}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Create Vehicle Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowForm(false)}>
                    <div className="mx-4 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Add Vehicle</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Registration No *</label>
                                    <input type="text" value={form.registration_no} onChange={(e) => setForm((p) => ({ ...p, registration_no: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Type</label>
                                    <select value={form.type} onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        {types.map((t) => <option key={t} value={t}>{cap(t)}</option>)}
                                    </select>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Make *</label>
                                    <input type="text" value={form.make} onChange={(e) => setForm((p) => ({ ...p, make: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Model</label>
                                    <input type="text" value={form.model} onChange={(e) => setForm((p) => ({ ...p, model: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Year</label>
                                    <input type="number" value={form.year} onChange={(e) => setForm((p) => ({ ...p, year: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Current Value (RM)</label>
                                    <input type="number" step="0.01" value={form.current_value} onChange={(e) => setForm((p) => ({ ...p, current_value: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Purchase Date</label>
                                    <input type="date" value={form.purchase_date} onChange={(e) => setForm((p) => ({ ...p, purchase_date: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                    <select value={form.status} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="disposed">Disposed</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Assigned To</label>
                                <select value={form.assigned_to} onChange={(e) => setForm((p) => ({ ...p, assigned_to: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                    <option value="">Unassigned</option>
                                    {employees.map((emp) => <option key={emp.id} value={emp.id}>{emp.full_name || `${emp.first_name} ${emp.last_name}`}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <textarea rows={2} value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : 'Add Vehicle'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
