import { useState, useEffect, useMemo } from 'react';
import { useAuth } from '@/context/AuthContext';
import apiClient from '@/services/apiClient';
import roleService from '@/services/roleService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineKey, HiOutlineCheck, HiOutlineInformationCircle, HiOutlineUser } from 'react-icons/hi';

// Friendly module display names (key = permission prefix before the dot)
const MODULE_LABELS = {
    dashboard: 'Dashboard', users: 'Users', departments: 'Departments', designations: 'Designations',
    projects: 'Projects', tasks: 'Tasks', clients: 'Clients', finance: 'Finance',
    attendance: 'Attendance', payroll: 'Payroll', reports: 'Reports', roles: 'User Access',
    settings: 'Settings', safety: 'Safety', environmental: 'Environmental', 'activity-logs': 'Activity Logs',
    staff: 'Staff', leave: 'Leave', training: 'Training', calendar: 'Calendar', assets: 'Assets', inventory: 'Inventory',
    maintenance: 'Maintenance', meetings: 'Meetings', documents: 'Documents', drawings: 'Drawings', memos: 'Memos',
};
const moduleName = (key) => MODULE_LABELS[key] || key.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const actionLabel = (perm) => perm.split('.').slice(1).join('.').replace(/-/g, ' ') || perm;

export default function Roles() {
    const { can } = useAuth();
    const canEdit = can('roles.edit');
    const [groups, setGroups] = useState({}); // { module: [perm,...] }
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);

    const [activeUserId, setActiveUserId] = useState('');
    const [selected, setSelected] = useState(new Set()); // the user's permissions (fully editable)
    const [isManager, setIsManager] = useState(false);
    const [isDirector, setIsDirector] = useState(false);
    const [userRole, setUserRole] = useState(null);
    const [userLoading, setUserLoading] = useState(false);
    const [saving, setSaving] = useState(false);

    const activeUser = useMemo(() => users.find((u) => String(u.id) === String(activeUserId)), [users, activeUserId]);
    const moduleKeys = Object.keys(groups);

    useEffect(() => {
        (async () => {
            try {
                const [permRes, usersRes] = await Promise.all([
                    roleService.permissions(),
                    apiClient.get('/users', { params: { per_page: 200 } }),
                ]);
                setGroups(permRes.data || {});
                setUsers(usersRes.data?.data?.data || usersRes.data?.data || []);
            } catch {
                toast.error('Failed to load user access');
            } finally {
                setLoading(false);
            }
        })();
    }, []);

    const selectUser = async (id) => {
        setActiveUserId(id);
        if (!id) return;
        setUserLoading(true);
        try {
            const res = await roleService.getUserAccess(id);
            setSelected(new Set(res.data?.permissions || []));
            setIsManager(!!res.data?.is_manager);
            setIsDirector(!!res.data?.is_director);
            setUserRole(res.data?.role || null);
        } catch {
            toast.error('Failed to load user access');
        } finally {
            setUserLoading(false);
        }
    };

    const togglePerm = (perm) => {
        if (!canEdit) return;
        setSelected((prev) => {
            const next = new Set(prev);
            next.has(perm) ? next.delete(perm) : next.add(perm);
            return next;
        });
    };

    const toggleModule = (perms) => {
        if (!canEdit) return;
        setSelected((prev) => {
            const next = new Set(prev);
            const allOn = perms.every((p) => next.has(p));
            perms.forEach((p) => (allOn ? next.delete(p) : next.add(p)));
            return next;
        });
    };

    const save = async () => {
        if (!activeUser) return;
        setSaving(true);
        try {
            await roleService.updateUserAccess(activeUser.id, {
                permissions: [...selected],
                is_manager: isManager,
                is_director: isDirector,
            });
            setUsers((prev) => prev.map((u) => u.id === activeUser.id ? { ...u, is_manager: isManager, is_director: isDirector } : u));
            toast.success('User access updated');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update user access');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;

    return (
        <div>
            <div className="mb-5">
                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                    <HiOutlineKey className="h-6 w-6 text-primary-600" /> User Access
                </h1>
                <p className="text-sm text-gray-500">Pick a user, then tick exactly the permissions they should have. Their role sets the starting ticks; changes here only affect this user.</p>
            </div>

            {/* User picker */}
            <div className="mb-6 max-w-md">
                <label className="mb-1 block text-sm font-medium text-gray-700">User</label>
                <select value={activeUserId} onChange={(e) => selectUser(e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Select a user…</option>
                    {users.map((u) => (
                        <option key={u.id} value={u.id}>
                            {u.full_name} — {u.email}{u.is_manager ? ' [MGR]' : ''}{u.is_director ? ' [DIR]' : ''}
                        </option>
                    ))}
                </select>
            </div>

            {!activeUserId ? (
                <div className="rounded-xl bg-white py-16 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineUser className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">Select a user to manage their access</p>
                </div>
            ) : userLoading ? (
                <LoadingSpinner />
            ) : (
                <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-lg font-bold text-gray-900">{activeUser?.full_name}</h2>
                            <p className="text-xs text-gray-500">Role: {userRole || '—'} · {selected.size} permission{selected.size === 1 ? '' : 's'}</p>
                        </div>
                        {canEdit && (
                            <button onClick={save} disabled={saving}
                                className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                <HiOutlineCheck className="h-5 w-5" /> {saving ? 'Saving...' : 'Save Access'}
                            </button>
                        )}
                    </div>

                    {/* Approver designation */}
                    <div className="mb-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <p className="mb-3 text-sm font-semibold text-gray-800">Approver designation</p>
                        <div className="flex flex-wrap gap-6">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={isManager} onChange={(e) => setIsManager(e.target.checked)} disabled={!canEdit}
                                    className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                <span className="font-medium">Manager</span>
                                <span className="text-xs text-gray-400">— approves stage 1 of any leave</span>
                            </label>
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={isDirector} onChange={(e) => setIsDirector(e.target.checked)} disabled={!canEdit}
                                    className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                <span className="font-medium">Director</span>
                                <span className="text-xs text-gray-400">— approves the director stage</span>
                            </label>
                        </div>
                        {(isManager || isDirector) && (
                            <p className="mt-3 flex items-start gap-1.5 text-xs text-gray-500">
                                <HiOutlineInformationCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                Managers and Directors automatically get access to the Leave Approvals page.
                            </p>
                        )}
                    </div>

                    {/* Permission grid */}
                    <p className="mb-3 text-sm font-semibold text-gray-800">Permissions</p>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {moduleKeys.map((key) => {
                            const perms = groups[key] || [];
                            const allOn = perms.every((p) => selected.has(p));
                            const someOn = perms.some((p) => selected.has(p));
                            return (
                                <div key={key} className="rounded-lg border border-gray-200 p-3">
                                    <label className="mb-2 flex cursor-pointer items-center justify-between gap-2 border-b border-gray-100 pb-2">
                                        <span className="text-sm font-bold text-gray-800">{moduleName(key)}</span>
                                        <input type="checkbox" checked={allOn} ref={(el) => el && (el.indeterminate = !allOn && someOn)}
                                            onChange={() => toggleModule(perms)} disabled={!canEdit}
                                            className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    </label>
                                    <div className="space-y-1.5">
                                        {perms.map((perm) => (
                                            <label key={perm} className="flex cursor-pointer items-center gap-2 text-xs text-gray-600">
                                                <input type="checkbox" checked={selected.has(perm)} onChange={() => togglePerm(perm)} disabled={!canEdit}
                                                    className="h-3.5 w-3.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                <span className="capitalize">{actionLabel(perm)}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
