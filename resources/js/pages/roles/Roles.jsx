import { useState, useEffect, useMemo } from 'react';
import { useAuth } from '@/context/AuthContext';
import apiClient from '@/services/apiClient';
import roleService from '@/services/roleService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineKey, HiOutlineUserGroup, HiOutlineCheck, HiOutlineInformationCircle, HiOutlineUser } from 'react-icons/hi';

// Friendly module display names (key = permission prefix before the dot)
const MODULE_LABELS = {
    dashboard: 'Dashboard', users: 'Users', departments: 'Departments', designations: 'Designations',
    projects: 'Projects', tasks: 'Tasks', clients: 'Clients', finance: 'Finance',
    attendance: 'Attendance', payroll: 'Payroll', reports: 'Reports', roles: 'Roles & Permissions',
    settings: 'Settings', safety: 'Safety', environmental: 'Environmental', 'activity-logs': 'Activity Logs',
    staff: 'Staff', leave: 'Leave', training: 'Training', calendar: 'Calendar', assets: 'Assets', inventory: 'Inventory',
    maintenance: 'Maintenance', meetings: 'Meetings', documents: 'Documents', drawings: 'Drawings',
};
const moduleName = (key) => MODULE_LABELS[key] || key.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const actionLabel = (perm) => perm.split('.').slice(1).join('.').replace(/-/g, ' ') || perm;

export default function Roles() {
    const { can } = useAuth();
    const canEdit = can('roles.edit');
    const [mode, setMode] = useState('roles'); // 'roles' | 'users'
    const [groups, setGroups] = useState({}); // { module: [perm,...] }
    const [loading, setLoading] = useState(true);

    // Roles mode
    const [roles, setRoles] = useState([]);
    const [activeId, setActiveId] = useState(null);
    const [selected, setSelected] = useState(new Set());
    const [saving, setSaving] = useState(false);

    // Users mode
    const [users, setUsers] = useState([]);
    const [activeUserId, setActiveUserId] = useState(null);
    const [userModules, setUserModules] = useState(new Set());
    const [isManager, setIsManager] = useState(false);
    const [isDirector, setIsDirector] = useState(false);
    const [userRole, setUserRole] = useState(null);
    const [userLoading, setUserLoading] = useState(false);
    const [userSaving, setUserSaving] = useState(false);

    const activeRole = useMemo(() => roles.find((r) => r.id === activeId), [roles, activeId]);
    const activeUser = useMemo(() => users.find((u) => u.id === activeUserId), [users, activeUserId]);
    const moduleKeys = Object.keys(groups);

    useEffect(() => {
        (async () => {
            try {
                const [rolesRes, permRes] = await Promise.all([roleService.list(), roleService.permissions()]);
                const list = rolesRes.data || [];
                setRoles(list);
                setGroups(permRes.data || {});
                if (list.length) {
                    setActiveId(list[0].id);
                    setSelected(new Set(list[0].permissions || []));
                }
                const usersRes = await apiClient.get('/users', { params: { per_page: 200 } });
                setUsers(usersRes.data?.data?.data || usersRes.data?.data || []);
            } catch {
                toast.error('Failed to load roles');
            } finally {
                setLoading(false);
            }
        })();
    }, []);

    // ── Roles mode ──
    const selectRole = (role) => {
        setActiveId(role.id);
        setSelected(new Set(role.permissions || []));
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
    const saveRole = async () => {
        if (!activeRole) return;
        if (selected.size === 0) { toast.error('Select at least one permission.'); return; }
        setSaving(true);
        try {
            await roleService.update(activeRole.id, { permissions: [...selected] });
            setRoles((prev) => prev.map((r) => r.id === activeRole.id ? { ...r, permissions: [...selected] } : r));
            toast.success('Role access updated');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update role');
        } finally {
            setSaving(false);
        }
    };

    // ── Users mode ──
    const selectUser = async (user) => {
        setActiveUserId(user.id);
        setUserLoading(true);
        try {
            const res = await roleService.getUserAccess(user.id);
            setUserModules(new Set(res.data?.modules || []));
            setIsManager(!!res.data?.is_manager);
            setIsDirector(!!res.data?.is_director);
            setUserRole(res.data?.role || null);
        } catch {
            toast.error('Failed to load user access');
        } finally {
            setUserLoading(false);
        }
    };
    const toggleUserModule = (key) => {
        if (!canEdit) return;
        setUserModules((prev) => {
            const next = new Set(prev);
            next.has(key) ? next.delete(key) : next.add(key);
            return next;
        });
    };
    const saveUser = async () => {
        if (!activeUser) return;
        setUserSaving(true);
        try {
            await roleService.updateUserAccess(activeUser.id, {
                modules: [...userModules],
                is_manager: isManager,
                is_director: isDirector,
            });
            setUsers((prev) => prev.map((u) => u.id === activeUser.id ? { ...u, is_manager: isManager, is_director: isDirector } : u));
            toast.success('User access updated');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update user access');
        } finally {
            setUserSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;

    const isSuperAdmin = activeRole?.name === 'Admin & HR';

    return (
        <div>
            <div className="mb-5">
                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                    <HiOutlineKey className="h-6 w-6 text-primary-600" /> Roles &amp; Permissions
                </h1>
                <p className="text-sm text-gray-500">Manage role-wide access, or tailor access and approver rights per user.</p>
            </div>

            {/* Mode toggle */}
            <div className="mb-6 inline-flex rounded-lg border border-gray-200 bg-white p-1">
                <button
                    onClick={() => setMode('roles')}
                    className={`rounded-md px-4 py-1.5 text-sm font-medium ${mode === 'roles' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50'}`}
                >
                    Roles
                </button>
                <button
                    onClick={() => setMode('users')}
                    className={`rounded-md px-4 py-1.5 text-sm font-medium ${mode === 'users' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50'}`}
                >
                    User Access
                </button>
            </div>

            {mode === 'roles' ? (
                <div className="grid gap-6 lg:grid-cols-[260px_1fr]">
                    {/* Role list */}
                    <div className="space-y-2">
                        {roles.map((role) => (
                            <button key={role.id} onClick={() => selectRole(role)}
                                className={`flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left transition-all ${
                                    role.id === activeId ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500' : 'border-gray-200 bg-white hover:border-primary-300'
                                }`}>
                                <div>
                                    <div className="text-sm font-bold text-gray-900">{role.name}</div>
                                    <div className="flex items-center gap-1 text-xs text-gray-400">
                                        <HiOutlineUserGroup className="h-3.5 w-3.5" /> {role.users_count} user{role.users_count === 1 ? '' : 's'}
                                    </div>
                                </div>
                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500">{role.permissions?.length || 0}</span>
                            </button>
                        ))}
                    </div>

                    {/* Permission matrix */}
                    <div>
                        {activeRole && (
                            <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                                <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 className="text-lg font-bold text-gray-900">{activeRole.name}</h2>
                                        <p className="text-xs text-gray-500">{selected.size} permission{selected.size === 1 ? '' : 's'} granted</p>
                                    </div>
                                    {canEdit && (
                                        <button onClick={saveRole} disabled={saving}
                                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                            <HiOutlineCheck className="h-5 w-5" /> {saving ? 'Saving...' : 'Save Access'}
                                        </button>
                                    )}
                                </div>

                                {isSuperAdmin && (
                                    <div className="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                                        <HiOutlineInformationCircle className="mt-0.5 h-4 w-4 shrink-0" />
                                        <span><strong>Admin &amp; HR</strong> is the super-admin role and always has full access regardless of these tick boxes.</span>
                                    </div>
                                )}

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
                </div>
            ) : (
                <div className="grid gap-6 lg:grid-cols-[280px_1fr]">
                    {/* User list */}
                    <div className="max-h-[70vh] space-y-2 overflow-y-auto pr-1">
                        {users.map((u) => (
                            <button key={u.id} onClick={() => selectUser(u)}
                                className={`flex w-full items-center justify-between gap-2 rounded-xl border px-4 py-3 text-left transition-all ${
                                    u.id === activeUserId ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500' : 'border-gray-200 bg-white hover:border-primary-300'
                                }`}>
                                <div className="min-w-0">
                                    <div className="truncate text-sm font-bold text-gray-900">{u.full_name}</div>
                                    <div className="truncate text-xs text-gray-400">{u.email}</div>
                                </div>
                                <div className="flex shrink-0 gap-1">
                                    {u.is_manager && <span className="rounded-full bg-blue-100 px-1.5 py-0.5 text-[9px] font-semibold text-blue-700">MGR</span>}
                                    {u.is_director && <span className="rounded-full bg-purple-100 px-1.5 py-0.5 text-[9px] font-semibold text-purple-700">DIR</span>}
                                </div>
                            </button>
                        ))}
                    </div>

                    {/* User access editor */}
                    <div>
                        {!activeUser ? (
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
                                        <h2 className="text-lg font-bold text-gray-900">{activeUser.full_name}</h2>
                                        <p className="text-xs text-gray-500">Role: {userRole || '—'} · extra module access &amp; approver rights</p>
                                    </div>
                                    {canEdit && (
                                        <button onClick={saveUser} disabled={userSaving}
                                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                            <HiOutlineCheck className="h-5 w-5" /> {userSaving ? 'Saving...' : 'Save Access'}
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

                                {/* Module access */}
                                <p className="mb-2 text-sm font-semibold text-gray-800">Module access</p>
                                <p className="mb-3 text-xs text-gray-500">Tick a module to grant this user all of its actions (on top of their role).</p>
                                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                    {moduleKeys.map((key) => (
                                        <label key={key}
                                            className={`flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm transition-colors ${
                                                userModules.has(key) ? 'border-primary-500 bg-primary-50 text-primary-800' : 'border-gray-200 hover:bg-gray-50 text-gray-700'
                                            }`}>
                                            <input type="checkbox" checked={userModules.has(key)} onChange={() => toggleUserModule(key)} disabled={!canEdit}
                                                className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                            <span className="font-medium">{moduleName(key)}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
