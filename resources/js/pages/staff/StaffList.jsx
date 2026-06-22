import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import staffService from '@/services/staffService';
import apiClient from '@/services/apiClient';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineUserGroup,
    HiOutlineEye,
    HiOutlinePencil,
    HiOutlineTrash,
} from 'react-icons/hi';

const statusColors = {
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-100 text-gray-600',
    resigned: 'bg-red-100 text-red-700',
};

export default function StaffList() {
    const { can } = useAuth();
    const navigate = useNavigate();
    const [staff, setStaff] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [departments, setDepartments] = useState([]);
    const [pagination, setPagination] = useState({});

    const fetchStaff = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (departmentFilter) params.department_id = departmentFilter;
            if (categoryFilter) params.category = categoryFilter;
            if (statusFilter) params.status = statusFilter;
            const res = await staffService.list(params);
            setStaff(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setStaff([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchStaff(), 400);
        return () => clearTimeout(timer);
    }, [search, departmentFilter, categoryFilter, statusFilter]);

    useEffect(() => {
        apiClient
            .get('/departments', { params: { per_page: 100 } })
            .then((r) => setDepartments(r.data?.data?.data || r.data?.data || []))
            .catch(() => {});
    }, []);

    const handleDelete = async (id) => {
        if (!confirm('Delete this staff member?')) return;
        try {
            await staffService.delete(id);
            toast.success('Staff member deleted');
            fetchStaff(pagination.current_page || 1);
        } catch {
            toast.error('Failed to delete staff member');
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Staff</h1>
                    <p className="text-sm text-gray-500">Employee registry and records</p>
                </div>
                {can('staff.create') && (
                    <Link
                        to="/staff/create"
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        New Staff
                    </Link>
                )}
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search staff..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                </div>
                <select
                    value={departmentFilter}
                    onChange={(e) => setDepartmentFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Departments</option>
                    {departments.map((d) => (
                        <option key={d.id} value={d.id}>{d.name}</option>
                    ))}
                </select>
                <select
                    value={categoryFilter}
                    onChange={(e) => setCategoryFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Categories</option>
                    <option value="office">Office</option>
                    <option value="site">Site</option>
                </select>
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="resigned">Resigned</option>
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : staff.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineUserGroup className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No staff found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Employee</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Emp. No</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Department</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Designation</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Category</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {staff.map((emp) => (
                                    <tr key={emp.id} className="cursor-pointer hover:bg-gray-50" onClick={() => navigate(`/staff/${emp.id}`)}>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                {emp.photo_path ? (
                                                    <img
                                                        src={staffService.getPhotoUrl(emp.id)}
                                                        alt={emp.full_name}
                                                        className="h-9 w-9 rounded-full object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">
                                                        {(emp.first_name?.[0] || '').toUpperCase()}
                                                    </div>
                                                )}
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900">{emp.full_name}</p>
                                                    <div className="flex items-center gap-2">
                                                        {emp.email && <p className="text-xs text-gray-500">{emp.email}</p>}
                                                        {emp.user_id
                                                            ? <span className="rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold text-green-700">Login ✓</span>
                                                            : <span className="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500">No login</span>}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{emp.employee_no}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{emp.department?.name || '-'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{emp.designation?.name || '-'}</td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-600">
                                                {emp.category || '-'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusColors[emp.status] || 'bg-gray-100 text-gray-600'}`}>
                                                {emp.status}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right" onClick={(e) => e.stopPropagation()}>
                                            <div className="flex items-center justify-end gap-1">
                                                <Link
                                                    to={`/staff/${emp.id}`}
                                                    className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                    title="View"
                                                >
                                                    <HiOutlineEye className="h-4 w-4" />
                                                </Link>
                                                {can('staff.edit') && (
                                                    <Link
                                                        to={`/staff/${emp.id}/edit`}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600"
                                                        title="Edit"
                                                    >
                                                        <HiOutlinePencil className="h-4 w-4" />
                                                    </Link>
                                                )}
                                                {can('staff.delete') && (
                                                    <button
                                                        onClick={() => handleDelete(emp.id)}
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
                                        onClick={() => fetchStaff(page)}
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
        </div>
    );
}
