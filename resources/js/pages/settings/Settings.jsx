import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import departmentService from '@/services/departmentService';
import designationService from '@/services/designationService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineCog, HiOutlinePlus, HiOutlinePencil, HiOutlineTrash } from 'react-icons/hi';

const emptyDept = { name: '', code: '', description: '', is_active: true };
const emptyDesig = { name: '', department_id: '', description: '', is_active: true };

export default function Settings() {
    const { can } = useAuth();
    const canManageDept = can('departments.create') || can('departments.edit');
    const canManageDesig = can('designations.create') || can('designations.edit');

    const [tab, setTab] = useState('departments');
    const [loading, setLoading] = useState(true);
    const [departments, setDepartments] = useState([]);
    const [designations, setDesignations] = useState([]);

    const [deptModal, setDeptModal] = useState(false);
    const [deptForm, setDeptForm] = useState(emptyDept);
    const [deptEditId, setDeptEditId] = useState(null);
    const [deptErrors, setDeptErrors] = useState({});

    const [desigModal, setDesigModal] = useState(false);
    const [desigForm, setDesigForm] = useState(emptyDesig);
    const [desigEditId, setDesigEditId] = useState(null);
    const [desigErrors, setDesigErrors] = useState({});

    const [saving, setSaving] = useState(false);

    const loadDepartments = useCallback(() => {
        return departmentService.list({ all: true }).then((r) => setDepartments(r.data || [])).catch(() => {});
    }, []);
    const loadDesignations = useCallback(() => {
        return designationService.list({ all: true }).then((r) => setDesignations(r.data || [])).catch(() => {});
    }, []);

    useEffect(() => {
        Promise.all([loadDepartments(), loadDesignations()]).finally(() => setLoading(false));
    }, [loadDepartments, loadDesignations]);

    // ── Departments ──
    const openDeptCreate = () => { setDeptEditId(null); setDeptForm(emptyDept); setDeptErrors({}); setDeptModal(true); };
    const openDeptEdit = (d) => {
        setDeptEditId(d.id);
        setDeptForm({ name: d.name, code: d.code, description: d.description || '', is_active: !!d.is_active });
        setDeptErrors({});
        setDeptModal(true);
    };
    const saveDept = async (e) => {
        e.preventDefault();
        setSaving(true);
        setDeptErrors({});
        try {
            if (deptEditId) {
                await departmentService.update(deptEditId, deptForm);
                toast.success('Department updated');
            } else {
                await departmentService.create(deptForm);
                toast.success('Department added');
            }
            setDeptModal(false);
            loadDepartments();
        } catch (err) {
            if (err.response?.status === 422) setDeptErrors(err.response.data?.errors || {});
            else toast.error(err.response?.data?.message || 'Failed to save department');
        } finally {
            setSaving(false);
        }
    };
    const deleteDept = async (d) => {
        if (!confirm(`Delete department "${d.name}"?`)) return;
        try {
            await departmentService.remove(d.id);
            toast.success('Department deleted');
            loadDepartments();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete');
        }
    };

    // ── Designations ──
    const openDesigCreate = () => { setDesigEditId(null); setDesigForm(emptyDesig); setDesigErrors({}); setDesigModal(true); };
    const openDesigEdit = (d) => {
        setDesigEditId(d.id);
        setDesigForm({ name: d.name, department_id: String(d.department_id || d.department?.id || ''), description: d.description || '', is_active: !!d.is_active });
        setDesigErrors({});
        setDesigModal(true);
    };
    const saveDesig = async (e) => {
        e.preventDefault();
        setSaving(true);
        setDesigErrors({});
        try {
            if (desigEditId) {
                await designationService.update(desigEditId, desigForm);
                toast.success('Designation updated');
            } else {
                await designationService.create(desigForm);
                toast.success('Designation added');
            }
            setDesigModal(false);
            loadDesignations();
        } catch (err) {
            if (err.response?.status === 422) setDesigErrors(err.response.data?.errors || {});
            else toast.error(err.response?.data?.message || 'Failed to save designation');
        } finally {
            setSaving(false);
        }
    };
    const deleteDesig = async (d) => {
        if (!confirm(`Delete designation "${d.name}"?`)) return;
        try {
            await designationService.remove(d.id);
            toast.success('Designation deleted');
            loadDesignations();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete');
        }
    };

    if (loading) return <LoadingSpinner />;

    const tabs = [
        { id: 'departments', label: 'Departments' },
        { id: 'designations', label: 'Designations' },
    ];

    return (
        <div>
            <div className="mb-5">
                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                    <HiOutlineCog className="h-6 w-6 text-primary-600" /> Settings
                </h1>
                <p className="text-sm text-gray-500">Manage departments and designations used across the system.</p>
            </div>

            <div className="mb-5 border-b border-gray-200">
                <nav className="-mb-px flex gap-6">
                    {tabs.map((t) => (
                        <button key={t.id} onClick={() => setTab(t.id)}
                            className={`whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium ${tab === t.id ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {tab === 'departments' ? (
                <div>
                    {canManageDept && (
                        <div className="mb-4 flex justify-end">
                            <button onClick={openDeptCreate} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                                <HiOutlinePlus className="h-5 w-5" /> Add Department
                            </button>
                        </div>
                    )}
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {departments.length === 0 && <p className="text-sm text-gray-400">No departments yet.</p>}
                        {departments.map((d) => (
                            <div key={d.id} className="flex items-start justify-between gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                <div className="min-w-0">
                                    <p className="text-sm font-bold text-gray-900">{d.name} <span className="text-xs font-normal text-gray-400">({d.code})</span></p>
                                    {d.description && <p className="truncate text-xs text-gray-500">{d.description}</p>}
                                    <p className="mt-1 text-xs text-gray-400">{d.designations_count ?? 0} designations · {d.users_count ?? 0} users</p>
                                    {!d.is_active && <span className="mt-1 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">inactive</span>}
                                </div>
                                {canManageDept && (
                                    <div className="flex shrink-0 gap-1">
                                        <button onClick={() => openDeptEdit(d)} className="rounded p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600" title="Edit"><HiOutlinePencil className="h-4 w-4" /></button>
                                        <button onClick={() => deleteDept(d)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete"><HiOutlineTrash className="h-4 w-4" /></button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            ) : (
                <div>
                    {canManageDesig && (
                        <div className="mb-4 flex justify-end">
                            <button onClick={openDesigCreate} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                                <HiOutlinePlus className="h-5 w-5" /> Add Designation
                            </button>
                        </div>
                    )}
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {designations.length === 0 && <p className="text-sm text-gray-400">No designations yet.</p>}
                        {designations.map((d) => (
                            <div key={d.id} className="flex items-start justify-between gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                <div className="min-w-0">
                                    <p className="text-sm font-bold text-gray-900">{d.name}</p>
                                    <p className="truncate text-xs text-gray-500">{d.department?.name || '—'}</p>
                                    <p className="mt-1 text-xs text-gray-400">{d.users_count ?? 0} users</p>
                                    {!d.is_active && <span className="mt-1 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">inactive</span>}
                                </div>
                                {canManageDesig && (
                                    <div className="flex shrink-0 gap-1">
                                        <button onClick={() => openDesigEdit(d)} className="rounded p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600" title="Edit"><HiOutlinePencil className="h-4 w-4" /></button>
                                        <button onClick={() => deleteDesig(d)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete"><HiOutlineTrash className="h-4 w-4" /></button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Department modal */}
            {deptModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setDeptModal(false)}>
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">{deptEditId ? 'Edit Department' : 'New Department'}</h3>
                        <form onSubmit={saveDept} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Name *</label>
                                    <input type="text" required value={deptForm.name} onChange={(e) => setDeptForm((p) => ({ ...p, name: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                    {deptErrors.name && <p className="mt-1 text-xs text-red-500">{deptErrors.name[0]}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Code *</label>
                                    <input type="text" required value={deptForm.code} onChange={(e) => setDeptForm((p) => ({ ...p, code: e.target.value }))} placeholder="e.g. ENG" className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                    {deptErrors.code && <p className="mt-1 text-xs text-red-500">{deptErrors.code[0]}</p>}
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                <textarea rows={2} value={deptForm.description} onChange={(e) => setDeptForm((p) => ({ ...p, description: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={deptForm.is_active} onChange={(e) => setDeptForm((p) => ({ ...p, is_active: e.target.checked }))} className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                Active
                            </label>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setDeptModal(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : deptEditId ? 'Update' : 'Add'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Designation modal */}
            {desigModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setDesigModal(false)}>
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">{desigEditId ? 'Edit Designation' : 'New Designation'}</h3>
                        <form onSubmit={saveDesig} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Name *</label>
                                <input type="text" required value={desigForm.name} onChange={(e) => setDesigForm((p) => ({ ...p, name: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                {desigErrors.name && <p className="mt-1 text-xs text-red-500">{desigErrors.name[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Department *</label>
                                <select required value={desigForm.department_id} onChange={(e) => setDesigForm((p) => ({ ...p, department_id: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                    <option value="">Select department</option>
                                    {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                                </select>
                                {desigErrors.department_id && <p className="mt-1 text-xs text-red-500">{desigErrors.department_id[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                <textarea rows={2} value={desigForm.description} onChange={(e) => setDesigForm((p) => ({ ...p, description: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={desigForm.is_active} onChange={(e) => setDesigForm((p) => ({ ...p, is_active: e.target.checked }))} className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                Active
                            </label>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setDesigModal(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : desigEditId ? 'Update' : 'Add'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
