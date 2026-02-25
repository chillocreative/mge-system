import { useState, useEffect, useCallback } from 'react';
import apiClient from '@/services/apiClient';
import LoadingSpinner from '@/components/LoadingSpinner';
import { HiOutlineUserGroup } from 'react-icons/hi';
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

export default function Users() {
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('');
    const [roles, setRoles] = useState([]);
    const [approveModal, setApproveModal] = useState({ open: false, user: null, role: '' });
    const [actionLoading, setActionLoading] = useState(null);

    const fetchUsers = useCallback(() => {
        setLoading(true);
        const params = statusFilter ? { status: statusFilter } : {};
        apiClient
            .get('/users', { params })
            .then((res) => setUsers(res.data?.data?.data || []))
            .catch(() => setUsers([]))
            .finally(() => setLoading(false));
    }, [statusFilter]);

    useEffect(() => {
        fetchUsers();
    }, [fetchUsers]);

    useEffect(() => {
        apiClient
            .get('/roles')
            .then((res) => setRoles(res.data?.data || []))
            .catch(() => setRoles([]));
    }, []);

    const handleApprove = async () => {
        if (!approveModal.role) {
            toast.error('Please select a role');
            return;
        }
        setActionLoading(approveModal.user.id);
        try {
            await apiClient.patch(`/users/${approveModal.user.id}/approve`, { role: approveModal.role });
            toast.success(`${approveModal.user.full_name} approved successfully`);
            setApproveModal({ open: false, user: null, role: '' });
            fetchUsers();
        } catch (error) {
            toast.error(error.response?.data?.message || 'Failed to approve user');
        } finally {
            setActionLoading(null);
        }
    };

    const handleReject = async (user) => {
        if (!window.confirm(`Are you sure you want to reject ${user.full_name}?`)) return;
        setActionLoading(user.id);
        try {
            await apiClient.patch(`/users/${user.id}/reject`);
            toast.success(`${user.full_name} rejected`);
            fetchUsers();
        } catch (error) {
            toast.error(error.response?.data?.message || 'Failed to reject user');
        } finally {
            setActionLoading(null);
        }
    };

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Users</h1>
                <p className="text-sm text-gray-500">Team members and system users</p>
            </div>

            {/* Status filter tabs */}
            <div className="mb-4 flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1">
                {statusTabs.map((tab) => (
                    <button
                        key={tab.key}
                        onClick={() => setStatusFilter(tab.key)}
                        className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                            statusFilter === tab.key
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'
                        }`}
                    >
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
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">User</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Department</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Role</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {users.map((user) => (
                                <tr key={user.id} className="hover:bg-gray-50">
                                    <td className="px-5 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-medium text-primary-700">
                                                {user.first_name?.[0]}
                                                {user.last_name?.[0]}
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">
                                                    {user.full_name}
                                                </p>
                                                <p className="text-xs text-gray-500">{user.email}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-gray-600">
                                        {user.department?.name || '-'}
                                    </td>
                                    <td className="px-5 py-4">
                                        {user.roles?.[0] ? (
                                            <span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">
                                                {user.roles[0]}
                                            </span>
                                        ) : (
                                            <span className="text-xs text-gray-400">-</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[user.status] || 'bg-gray-100 text-gray-600'}`}>
                                            {user.status}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">
                                        {user.status === 'pending' && (
                                            <div className="flex items-center gap-2">
                                                <button
                                                    onClick={() => setApproveModal({ open: true, user, role: '' })}
                                                    disabled={actionLoading === user.id}
                                                    className="rounded-md bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-100 disabled:opacity-50"
                                                >
                                                    Approve
                                                </button>
                                                <button
                                                    onClick={() => handleReject(user)}
                                                    disabled={actionLoading === user.id}
                                                    className="rounded-md bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100 disabled:opacity-50"
                                                >
                                                    Reject
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Approve Modal */}
            {approveModal.open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                        <h3 className="mb-1 text-lg font-semibold text-gray-900">Approve User</h3>
                        <p className="mb-4 text-sm text-gray-500">
                            Approve <span className="font-medium text-gray-700">{approveModal.user?.full_name}</span> and assign a role.
                        </p>

                        <label className="mb-1.5 block text-sm font-medium text-gray-700">
                            Role
                        </label>
                        <select
                            value={approveModal.role}
                            onChange={(e) => setApproveModal({ ...approveModal, role: e.target.value })}
                            className="mb-4 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400"
                        >
                            <option value="">Select a role...</option>
                            {roles.map((role) => (
                                <option key={role.id} value={role.name}>
                                    {role.name}
                                </option>
                            ))}
                        </select>

                        <div className="flex justify-end gap-2">
                            <button
                                onClick={() => setApproveModal({ open: false, user: null, role: '' })}
                                className="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleApprove}
                                disabled={!approveModal.role || actionLoading}
                                className="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                            >
                                {actionLoading ? 'Approving...' : 'Approve'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
