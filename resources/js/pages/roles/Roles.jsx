import { useState, useEffect, useMemo } from 'react';
import { useAuth } from '@/context/AuthContext';
import roleService from '@/services/roleService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineKey, HiOutlineUserGroup, HiOutlineCheck, HiOutlineInformationCircle } from 'react-icons/hi';

// Friendly module display names (key = permission prefix before the dot)
const MODULE_LABELS = {
    dashboard: 'Dashboard', users: 'Users', departments: 'Departments', designations: 'Designations',
    projects: 'Projects', tasks: 'Tasks', clients: 'Clients', finance: 'Finance',
    attendance: 'Attendance', payroll: 'Payroll', reports: 'Reports', roles: 'Roles & Permissions',
    settings: 'Settings', safety: 'Safety', environmental: 'Environmental', 'activity-logs': 'Activity Logs',
    staff: 'Staff', leave: 'Leave', calendar: 'Calendar', assets: 'Assets', inventory: 'Inventory',
    maintenance: 'Maintenance', meetings: 'Meetings', documents: 'Documents', drawings: 'Drawings',
};
const moduleName = (key) => MODULE_LABELS[key] || key.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const actionLabel = (perm) => perm.split('.').slice(1).join('.').replace(/-/g, ' ') || perm;

export default function Roles() {
    const { can } = useAuth();
    const canEdit = can('roles.edit');
    const [roles, setRoles] = useState([]);
    const [groups, setGroups] = useState({}); // { module: [perm,...] }
    const [loading, setLoading] = useState(true);
    const [activeId, setActiveId] = useState(null);
    const [selected, setSelected] = useState(new Set());
    const [saving, setSaving] = useState(false);

    const activeRole = useMemo(() => roles.find((r) => r.id === activeId), [roles, activeId]);

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
            } catch {
                toast.error('Failed to load roles');
            } finally {
                setLoading(false);
            }
        })();
    }, []);

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

    const save = async () => {
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

    if (loading) return <LoadingSpinner />;

    const moduleKeys = Object.keys(groups);
    const isSuperAdmin = activeRole?.name === 'Admin & HR';

    return (
        <div>
            <div className="mb-6">
                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                    <HiOutlineKey className="h-6 w-6 text-primary-600" /> Roles &amp; Permissions
                </h1>
                <p className="text-sm text-gray-500">Tick the modules and actions each role can access.</p>
            </div>

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
                                    <button onClick={save} disabled={saving}
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
        </div>
    );
}
