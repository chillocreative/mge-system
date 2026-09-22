import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { useConfirm } from '@/context/ConfirmContext';
import assetService from '@/services/assetService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import useDragScroll from '@/hooks/useDragScroll';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineTruck,
    HiOutlineExclamation,
    HiOutlinePencilAlt,
    HiOutlineTrash,
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

function typeLabel(v) {
    return v.type === 'other' && v.custom_type ? v.custom_type : cap(v.type);
}

export default function Vehicles({ category = 'vehicle' }) {
    const label = category === 'machine' ? 'Machine' : 'Vehicle';
    const labelPlural = category === 'machine' ? 'Machines' : 'Vehicles';
    const labelLower = label.toLowerCase();
    const labelPluralLower = labelPlural.toLowerCase();
    const { can } = useAuth();
    const confirm = useConfirm();
    const [vehicles, setVehicles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const dragScrollRef = useDragScroll();
    const [statusFilter, setStatusFilter] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [pagination, setPagination] = useState({});
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [projects, setProjects] = useState([]);
    const [saving, setSaving] = useState(false);
    const [dashboard, setDashboard] = useState(null);
    const [form, setForm] = useState({
        registration_no: '',
        chassis_no: '',
        engine_no: '',
        serial_no: '',
        make: '',
        model: '',
        year: '',
        type: 'car',
        purchase_date: '',
        current_value: '',
        project_id: '',
        status: 'active',
        notes: '',
        custom_type: '',
    });

    const fetchVehicles = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            if (typeFilter) params.type = typeFilter;
            params.category = category;
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
            const res = await assetService.getExpiring(60, category);
            setDashboard(res.data || null);
        } catch { /* ignore */ }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchVehicles(), 400);
        return () => clearTimeout(timer);
    }, [search, statusFilter, typeFilter, category]);

    useEffect(() => {
        fetchVehicles();
        fetchSummary();
        projectService.list({ per_page: 100 })
            .then((r) => setProjects(r.data?.data || []))
            .catch(() => {});
    }, [category]);

    const openEdit = (vehicle) => {
        setForm({
            registration_no: vehicle.registration_no || '',
            chassis_no: vehicle.chassis_no || '',
            engine_no: vehicle.engine_no || '',
            serial_no: vehicle.serial_no || '',
            make: vehicle.make || '',
            model: vehicle.model || '',
            year: vehicle.year || '',
            type: vehicle.type || 'car',
            purchase_date: vehicle.purchase_date || '',
            current_value: vehicle.current_value || '',
            project_id: vehicle.current_project_assignment?.project_id || '',
            status: vehicle.status || 'active',
            notes: vehicle.notes || '',
            custom_type: vehicle.custom_type || '',
        });
        setEditingId(vehicle.id);
        setShowForm(true);
    };

    const openCreate = () => {
        setForm({
            registration_no: '',
            chassis_no: '',
            engine_no: '',
            serial_no: '',
            make: '',
            model: '',
            year: '',
            type: 'car',
            purchase_date: '',
            current_value: '',
            project_id: '',
            status: 'active',
            notes: '',
            custom_type: '',
        });
        setEditingId(null);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditingId(null);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = { ...form, category };
            if (payload.status === '' || payload.status === null) delete payload.status;

            if (editingId) {
                await assetService.updateVehicle(editingId, payload);
                toast.success(`${label} updated successfully`);
            } else {
                await assetService.createVehicle(payload);
                toast.success(`${label} created successfully`);
            }

            closeForm();
            fetchVehicles();
            fetchSummary();
        } catch (err) {
            toast.error(err.response?.data?.message || `Failed to save ${labelLower}`);
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id) => {
        if (!(await confirm({ title: `Delete ${labelLower}?`, message: 'This action cannot be undone.' }))) return;
        try {
            await assetService.deleteVehicle(id);
            toast.success(`${label} deleted successfully`);
            fetchVehicles();
            fetchSummary();
        } catch (err) {
            toast.error(err.response?.data?.message || `Failed to delete ${labelLower}`);
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">{labelPlural} &amp; Assets</h1>
                    <p className="text-sm text-gray-500">Manage company {labelPluralLower}, road tax, insurance and permits</p>
                </div>
                {can('assets.manage') && (
                    <button
                        onClick={openCreate}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        New {label}
                    </button>
                )}
            </div>

            {/* Summary row */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center gap-3">
                        <span className="rounded-lg bg-primary-50 p-2 text-primary-600"><HiOutlineTruck className="h-6 w-6" /></span>
                        <div>
                            <p className="text-xs text-gray-500">Total {labelPlural}</p>
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
                    <p className="mt-2 text-sm text-gray-500">No {labelPluralLower} found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div ref={dragScrollRef} className="overflow-x-auto cursor-grab active:cursor-grabbing">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Registration</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Serial No</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Make / Model</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Assigned To Project</th>
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
                                        <td className="px-4 py-3 text-sm text-gray-700">{v.serial_no || '-'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-700">
                                            {v.make}{v.model ? ` ${v.model}` : ''}{v.year ? ` (${v.year})` : ''}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{typeLabel(v)}</span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{v.current_project_assignment?.project?.name || '-'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[v.status]}`}>{v.status}</span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {can('assets.manage') && (
                                                <div className="flex justify-end gap-3">
                                                    <button
                                                        onClick={() => openEdit(v)}
                                                        className="text-gray-400 hover:text-primary-600 transition-colors"
                                                        title="Edit"
                                                    >
                                                        <HiOutlinePencilAlt className="h-5 w-5" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(v.id)}
                                                        className="text-gray-400 hover:text-red-600 transition-colors"
                                                        title="Delete"
                                                    >
                                                        <HiOutlineTrash className="h-5 w-5" />
                                                    </button>
                                                </div>
                                            )}
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

            {/* Create/Edit Vehicle Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={closeForm}>
                    <div className="mx-4 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">{editingId ? `Edit ${label}` : `Add ${label}`}</h3>
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
                            {form.type === 'other' && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Specify Type *</label>
                                    <input
                                        type="text"
                                        value={form.custom_type}
                                        onChange={(e) => setForm((p) => ({ ...p, custom_type: e.target.value }))}
                                        required
                                        placeholder="e.g. Concrete Pump, Piling Rig"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                            )}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Make *</label>
                                    <input type="text" value={form.make} onChange={(e) => setForm((p) => ({ ...p, make: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Model</label>
                                    <input type="text" value={form.model} onChange={(e) => setForm((p) => ({ ...p, model: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Chassis No</label>
                                    <input type="text" value={form.chassis_no} onChange={(e) => setForm((p) => ({ ...p, chassis_no: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Engine No</label>
                                    <input type="text" value={form.engine_no} onChange={(e) => setForm((p) => ({ ...p, engine_no: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Serial No</label>
                                    <input type="text" value={form.serial_no} onChange={(e) => setForm((p) => ({ ...p, serial_no: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
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
                                <label className="mb-1 block text-sm font-medium text-gray-700">Assigned To Project</label>
                                <select value={form.project_id} onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                    <option value="">Unassigned</option>
                                    {projects.map((proj) => <option key={proj.id} value={proj.id}>{proj.code ? `${proj.code} — ${proj.name}` : proj.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <textarea rows={2} value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={closeForm} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : editingId ? `Update ${label}` : `Add ${label}`}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
