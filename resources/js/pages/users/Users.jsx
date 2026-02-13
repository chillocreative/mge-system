import { useState, useEffect } from 'react';
import apiClient from '@/services/apiClient';
import LoadingSpinner from '@/components/LoadingSpinner';
import { HiOutlineUserGroup } from 'react-icons/hi';

const statusColors = {
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-100 text-gray-600',
    suspended: 'bg-red-100 text-red-700',
};

export default function Users() {
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        apiClient
            .get('/users')
            .then((res) => setUsers(res.data?.data?.data || []))
            .catch(() => setUsers([]))
            .finally(() => setLoading(false));
    }, []);

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Users</h1>
                <p className="text-sm text-gray-500">Team members and system users</p>
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
                                        {user.roles?.[0] && (
                                            <span className="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">
                                                {user.roles[0]}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[user.status]}`}>
                                            {user.status}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
