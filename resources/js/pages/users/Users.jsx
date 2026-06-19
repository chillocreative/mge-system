import { useState, useEffect, useCallback } from 'react';
import apiClient from '@/services/apiClient';
import { useAuth } from '@/context/AuthContext';
import LoadingSpinner from '@/components/LoadingSpinner';
import { HiOutlineUserGroup, HiOutlinePlus, HiOutlinePencil, HiOutlineTrash, HiOutlineLockClosed } from 'react-icons/hi';
import toast from 'react-hot-toast';

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700',
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-100 text-gray-600',
    suspended: 'bg-red-100 text-red-700',
    rejected: 'bg-rose-100 text-rose-700',
};

const statusTabs = [
    { key: '', label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'active', label: 'Active' },
    { key: 'inactive', label: 'Inactive' },
    { key: 'suspended', label: 'Suspended' },
    { key: 'rejected', label: 'Rejected' },
];

const SUPER_ADMIN_ROLE = 'Admin & HR';
const initials = (name = '') => name.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const emptyForm = { full_name: '', ic_number: '', email: '', password: '', phone: '', department_id: '', designation_id: '', role: '', status: 'active' };
const unwrap = (r) => r.data?.data?.data || r.data?.data || [];

export default function Users() {
    const { can, user } = useAuth();
    const canGrantSuper = !!user?.is_protected; // only the System Administrator
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('');
    const [roles, setRoles] = useState([]);
    const [departments, setDepartments] = useState([]);
    const [designations, setDesignations] = useState([]);
    const [approveModal, setApproveModal] = useState({ open: false, user: null, role: '' });
    const [actionLoading, setActionLoading] = useState(null);

    // Create / Edit form modal
    const [formOpen, setFormOpen] = useState(false);
    const [editId, setEditId] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    const fetchUsers = useCallback(() => {
        setLoading(true);
        const params = statusFilter ? { status: statusFilter } : {};
        apiClient.get('/users', { params })
            .then((res) => setUsers(res.data?.data?.data || []))
            .catch(() => setUsers([]))
            .finally(() => setLoading(false));
    }, [statusFilter]);

    useEffect(() => { fetchUsers(); }, [fetchUsers]);

    useEffect(() => {
        apiClient.get('/roles').then((r) => setRoles(r.data?.data || [])).catch(() => setRoles([]));
        apiClient.get('/departments', { params: { per_page: 100 } }).then((r) => setDepartments(unwrap(r))).catch(() => {});
        apiClient.get('/designations', { params: { per_page: 100 } }).then((r) => setDesignations(unwrap(r))).catch(() => {});
    }, []);

    const openCreate = () => { setEditId(null); setForm(emptyForm); setErrors({}); setFormOpen(true); };
    const openEdit = (user) => {
        setEditId(user.id);
        setForm({
            full_name: user.full_name || '', ic_number: user.ic_number || '', email: user.email || '',
            password: '', phone: user.phone || '', department_id: user.department?.id || '',
            designation_id: user.designation?.id || '', role: user.roles?.[0] || '', status: user.status || 'active',
        });
        setErrors({});
        setFormOpen(true);
    };

    const submitForm = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            if (editId) {
                const payload = {
                    full_name: form.full_name, ic_number: form.ic_number || null, phone: form.phone || null,
                    department_id: form.department_id || null, designation_id: form.designation_id || null,
                    status: form.status, role: form.role || null,
                };
                if (form.password) payload.password = form.password; // blank = keep current
                await apiClient.put(`/users/${editId}`, payload);
                toast.success('User updated');
            } else {
                await apiClient.post('/users', {
                    full_name: form.full_name, ic_number: form.ic_number || null, email: form.email,
                    password: form.password, phone: form.phone || null,
                    department_id: form.department_id || null, designation_id: form.designation_id || null, role: form.role,
                });
                toast.success('User created');
            }
            setFormOpen(false);
            fetchUsers();
        } catch (err) {
            if (err.response?.status === 422) setErrors(err.response.data.errors || {});
            else toast.error(err.response?.data?.message || 'Failed to save user');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (user) => {
        if (!window.confirm(`Delete ${user.full_name}? This cannot be undone.`)) return;
        setActionLoading(user.id);
        try {
            await apiClient.delete(`/users/${user.id}`);
            toast.success(`${user.full_name} deleted`);
            fetchUsers();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete user');
        } finally {
            setActionLoading(null);
        }
    };

    const handleApprove = async () => {
        if (!approveModal.role) { toast.error('Please select a role'); return; }
        setActionLoading(approveModal.user.id);
        try {
            await apiClient.patch(`/users/${approveModal.user.id}/approve`, { role: approveModal.role });
            toast.success(`${approveModal.user.full_name} approved`);
            setApproveModal({ open: false, user: null, role: '' });
            fetchUsers();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to approve user');
        } finally {
            setActionLoading(null);
        }
    };

    const handleReject = async (user) => {
        if (!window.confirm(`Reject ${user.full_name}?`)) return;
        setActionLoading(user.id);
        try {
            await apiClient.patch(`/users/${user.id}/reject`);
            toast.success(`${user.full_name} rejected`);
            fetchUsers();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to reject user');
        } finally {
            setActionLoading(null);
        }
    };

    const fieldClass = (name) =>
        `w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1 ${errors[name] ? 'border-red-300 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-primary-400 focus:ring-primary-400'}`;

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Users</h1>
                    <p className="text-sm text-gray-500">Team members and system users</p>
                </div>
                {can('users.create') && (
                    <button onClick={openCreate} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                        <HiOutlinePlus className="h-5 w-5" /> New User
                    </button>
                )}
            </div>

            <div className="mb-4 flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1">
                {statusTabs.map((tab) => (
                    <button key={tab.key} onClick={() => setStatusFilter(tab.key)}
                        className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${statusFilter === tab.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}>
                        {tab.label}
                    </button>
                ))}
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : users.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineUserGroup className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No users found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">User</th>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Department</th>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Role</th>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                    <th className="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {users.map((user) => (
                                    <tr key={user.id} className="hover:bg-gray-50">
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-medium text-primary-700">
                                                    {initials(user.full_name)}
                                                </div>
                                                <div>
                                                    <p className="flex items-center gap-1.5 text-sm font-medium text-gray-900">
                                                        {user.full_name}
                                                        {user.is_protected && <HiOutlineLockClosed className="h-3.5 w-3.5 text-gray-400" title="Protected account" />}
                                                    </p>
                                                    <p className="text-xs text-gray-500">{user.email}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-gray-600">{user.department?.name || '-'}</td>
                                        <td className="px-5 py-4">
                                            {user.roles?.[0] ? (
                                                <span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">{user.roles[0]}</span>
                                            ) : <span className="text-xs text-gray-400">-</span>}
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[user.status] || 'bg-gray-100 text-gray-600'}`}>{user.status}</span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center justify-end gap-1">
                                                {user.status === 'pending' && can('users.approve') && (
                                                    <>
                                                        <button onClick={() => setApproveModal({ open: true, user, role: '' })} disabled={actionLoading === user.id}
                                                            className="rounded-md bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-100 disabled:opacity-50">Approve</button>
                                                        <button onClick={() => handleReject(user)} disabled={actionLoading === user.id}
                                                            className="rounded-md bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100 disabled:opacity-50">Reject</button>
                                                    </>
                                                )}
                                                {user.is_protected ? (
                                                    <span className="inline-flex items-center gap-1 rounded-md bg-gray-50 px-2 py-1 text-[11px] font-medium text-gray-400">
                                                        <HiOutlineLockClosed className="h-3.5 w-3.5" /> Protected
                                                    </span>
                                                ) : (
                                                    <>
                                                        {can('users.edit') && (
                                                            <button onClick={() => openEdit(user)} title="Edit"
                                                                className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><HiOutlinePencil className="h-4 w-4" /></button>
                                                        )}
                                                        {can('users.delete') && (
                                                            <button onClick={() => handleDelete(user)} disabled={actionLoading === user.id} title="Delete"
                                                                className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"><HiOutlineTrash className="h-4 w-4" /></button>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Create / Edit modal */}
            {formOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setFormOpen(false)}>
                    <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">{editId ? 'Edit User' : 'New User'}</h3>
                        <form onSubmit={submitForm} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Full Name *</label>
                                <input type="text" value={form.full_name} onChange={(e) => setForm((p) => ({ ...p, full_name: e.target.value }))} required className={fieldClass('full_name')} placeholder="Ahmad Razif" />
                                {errors.full_name && <p className="mt-1 text-xs text-red-500">{errors.full_name[0]}</p>}
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">IC Number</label>
                                    <input type="text" value={form.ic_number} onChange={(e) => setForm((p) => ({ ...p, ic_number: e.target.value }))} className={fieldClass('ic_number')} placeholder="901231-14-5678" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Phone</label>
                                    <input type="tel" value={form.phone} onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))} className={fieldClass('phone')} placeholder="+60 1x-xxx xxxx" />
                                </div>
                            </div>
                            {!editId && (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Email *</label>
                                        <input type="email" value={form.email} onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))} required className={fieldClass('email')} placeholder="name@mge-eng.com" />
                                        {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email[0]}</p>}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Password *</label>
                                        <input type="password" value={form.password} onChange={(e) => setForm((p) => ({ ...p, password: e.target.value }))} required minLength={8} className={fieldClass('password')} placeholder="Min. 8 characters" />
                                        {errors.password && <p className="mt-1 text-xs text-red-500">{errors.password[0]}</p>}
                                    </div>
                                </div>
                            )}
                            {editId && (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Email</label>
                                        <input type="email" value={form.email} disabled className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-500" />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">New Password</label>
                                        <input type="password" value={form.password} onChange={(e) => setForm((p) => ({ ...p, password: e.target.value }))} minLength={8} autoComplete="new-password" className={fieldClass('password')} placeholder="Leave blank to keep current" />
                                        {errors.password && <p className="mt-1 text-xs text-red-500">{errors.password[0]}</p>}
                                    </div>
                                </div>
                            )}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Department</label>
                                    <select value={form.department_id} onChange={(e) => setForm((p) => ({ ...p, department_id: e.target.value }))} className={fieldClass('department_id')}>
                                        <option value="">None</option>
                                        {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Designation</label>
                                    <select value={form.designation_id} onChange={(e) => setForm((p) => ({ ...p, designation_id: e.target.value }))} className={fieldClass('designation_id')}>
                                        <option value="">None</option>
                                        {designations.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                                    </select>
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Role {editId ? '' : '*'}</label>
                                    <select value={form.role} onChange={(e) => setForm((p) => ({ ...p, role: e.target.value }))} required={!editId} className={fieldClass('role')}>
                                        <option value="">Select role...</option>
                                        {roles
                                            .filter((r) => r.name !== SUPER_ADMIN_ROLE || canGrantSuper || r.name === form.role)
                                            .map((r) => <option key={r.id} value={r.name}>{r.name}</option>)}
                                    </select>
                                    {!canGrantSuper && (
                                        <p className="mt-1 text-[11px] text-gray-400">Only the System Administrator can assign the Super Admin role.</p>
                                    )}
                                </div>
                                {editId && (
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                        <select value={form.status} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))} className={fieldClass('status')}>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="suspended">Suspended</option>
                                            <option value="pending">Pending</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                    </div>
                                )}
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setFormOpen(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                    {saving ? 'Saving...' : editId ? 'Update User' : 'Create User'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Approve modal */}
            {approveModal.open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setApproveModal({ open: false, user: null, role: '' })}>
                    <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-1 text-lg font-semibold text-gray-900">Approve User</h3>
                        <p className="mb-4 text-sm text-gray-500">Approve <span className="font-medium text-gray-700">{approveModal.user?.full_name}</span> and assign a role.</p>
                        <label className="mb-1.5 block text-sm font-medium text-gray-700">Role</label>
                        <select value={approveModal.role} onChange={(e) => setApproveModal({ ...approveModal, role: e.target.value })}
                            className="mb-4 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400">
                            <option value="">Select a role...</option>
                            {roles
                                .filter((role) => role.name !== SUPER_ADMIN_ROLE || canGrantSuper)
                                .map((role) => <option key={role.id} value={role.name}>{role.name}</option>)}
                        </select>
                        <div className="flex justify-end gap-2">
                            <button onClick={() => setApproveModal({ open: false, user: null, role: '' })} className="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cancel</button>
                            <button onClick={handleApprove} disabled={!approveModal.role || actionLoading} className="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                                {actionLoading ? 'Approving...' : 'Approve'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
