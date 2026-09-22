import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import { useConfirm } from '@/context/ConfirmContext';
import departmentService from '@/services/departmentService';
import designationService from '@/services/designationService';
import mailSettingService from '@/services/mailSettingService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineCog, HiOutlinePlus, HiOutlinePencil, HiOutlineTrash } from 'react-icons/hi';

const emptyDept = { name: '', code: '', description: '', is_active: true };
const emptyDesig = { name: '', department_id: '', description: '', is_active: true };

export default function Settings() {
    const { can } = useAuth();
    const canManageDept = can('departments.create') || can('departments.edit');
    const canManageDesig = can('designations.create') || can('designations.edit');
    const canManageMail = can('settings.manage');
    const canViewMail = can('settings.view') || canManageMail;
    const confirm = useConfirm();

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

    const [mailForm, setMailForm] = useState({
        host: '', port: 587, username: '', password: '', encryption: 'tls',
        from_address: '', from_name: '', enabled: false,
    });
    const [mailHasPassword, setMailHasPassword] = useState(false);
    const [mailLoaded, setMailLoaded] = useState(false);
    const [mailSaving, setMailSaving] = useState(false);
    const [mailErrors, setMailErrors] = useState({});
    const [testTo, setTestTo] = useState('');
    const [testSending, setTestSending] = useState(false);
    const [testWarnings, setTestWarnings] = useState([]);
    const [mailStatus, setMailStatus] = useState(null);
    const [mailStatusLoading, setMailStatusLoading] = useState(false);

    const loadDepartments = useCallback(() => {
        return departmentService.list({ all: true }).then((r) => setDepartments(r.data || [])).catch(() => {});
    }, []);
    const loadDesignations = useCallback(() => {
        return designationService.list({ all: true }).then((r) => setDesignations(r.data || [])).catch(() => {});
    }, []);

    useEffect(() => {
        Promise.all([loadDepartments(), loadDesignations()]).finally(() => setLoading(false));
    }, [loadDepartments, loadDesignations]);

    const loadMailSettings = useCallback(() => {
        if (!canViewMail) return;
        mailSettingService.get().then((r) => {
            if (r.data) {
                setMailForm({
                    host: r.data.host || '', port: r.data.port || 587, username: r.data.username || '',
                    password: '', encryption: r.data.encryption || 'tls',
                    from_address: r.data.from_address || '', from_name: r.data.from_name || '',
                    enabled: !!r.data.enabled,
                });
                setMailHasPassword(!!r.data.has_password);
            }
            setMailLoaded(true);
        }).catch(() => setMailLoaded(true));
    }, [canViewMail]);

    useEffect(() => {
        if (tab === 'mail' && !mailLoaded) loadMailSettings();
    }, [tab, mailLoaded, loadMailSettings]);

    const loadMailStatus = useCallback(() => {
        if (!canViewMail) return;
        setMailStatusLoading(true);
        mailSettingService.status()
            .then((r) => setMailStatus(r.data))
            .catch(() => {})
            .finally(() => setMailStatusLoading(false));
    }, [canViewMail]);

    useEffect(() => {
        if (tab === 'mail' && canViewMail) loadMailStatus();
    }, [tab, canViewMail, loadMailStatus]);

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
        if (!(await confirm({ title: 'Delete department?', message: `Delete department "${d.name}"?` }))) return;
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
        if (!(await confirm({ title: 'Delete designation?', message: `Delete designation "${d.name}"?` }))) return;
        try {
            await designationService.remove(d.id);
            toast.success('Designation deleted');
            loadDesignations();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete');
        }
    };

    const saveMailSettings = async (e) => {
        e.preventDefault();
        setMailSaving(true);
        setMailErrors({});
        try {
            const payload = { ...mailForm };
            if (!payload.password) delete payload.password;
            const res = await mailSettingService.update(payload);
            setMailHasPassword(!!res.data.has_password);
            setMailForm((p) => ({ ...p, password: '' }));
            toast.success('Mail settings saved');
            loadMailStatus();
        } catch (err) {
            if (err.response?.status === 422) setMailErrors(err.response.data.errors || {});
            toast.error(err.response?.data?.message || 'Failed to save mail settings');
        } finally {
            setMailSaving(false);
        }
    };

    const sendTestEmail = async () => {
        if (!testTo.trim()) return toast.error('Enter an email address to send the test to');
        setTestSending(true);
        setTestWarnings([]);
        try {
            const payload = { to: testTo, ...mailForm };
            if (!payload.password) delete payload.password;
            const res = await mailSettingService.test(payload);
            toast.success(res.message || 'Test email sent');
            const warnings = res.data?.warnings || [];
            setTestWarnings(warnings);
            warnings.forEach((w) => toast.error(w, { duration: 8000 }));
            loadMailStatus();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to send test email');
        } finally {
            setTestSending(false);
        }
    };

    if (loading) return <LoadingSpinner />;

    const tabs = [
        { id: 'departments', label: 'Departments' },
        { id: 'designations', label: 'Designations' },
        { id: 'mail', label: 'Email (SMTP)' },
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

            {tab === 'departments' && (
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
            )}

            {tab === 'designations' && (
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

            {tab === 'mail' && (
                <div className="max-w-2xl space-y-6">
                    <form onSubmit={saveMailSettings} className="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 className="text-sm font-semibold text-gray-900">SMTP Configuration</h3>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">SMTP Host *</label>
                                <input type="text" required disabled={!canManageMail} value={mailForm.host} onChange={(e) => setMailForm((p) => ({ ...p, host: e.target.value }))} placeholder="mail.mge-eng.com" className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50" />
                                {mailErrors.host && <p className="mt-1 text-xs text-red-500">{mailErrors.host[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Port *</label>
                                <input type="number" required disabled={!canManageMail} value={mailForm.port} onChange={(e) => setMailForm((p) => ({ ...p, port: Number(e.target.value) }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50" />
                                {mailErrors.port && <p className="mt-1 text-xs text-red-500">{mailErrors.port[0]}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Username (mailbox) *</label>
                                <input type="text" required disabled={!canManageMail} value={mailForm.username} onChange={(e) => setMailForm((p) => ({ ...p, username: e.target.value }))} placeholder="noreply@mge-eng.com" className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50" />
                                {mailErrors.username && <p className="mt-1 text-xs text-red-500">{mailErrors.username[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Password {mailHasPassword ? '' : '*'}</label>
                                <input type="password" disabled={!canManageMail} value={mailForm.password} onChange={(e) => setMailForm((p) => ({ ...p, password: e.target.value }))} placeholder={mailHasPassword ? '•••••••• (leave blank to keep)' : 'Mailbox password'} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50" />
                                {mailErrors.password && <p className="mt-1 text-xs text-red-500">{mailErrors.password[0]}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Encryption</label>
                                <select disabled={!canManageMail} value={mailForm.encryption} onChange={(e) => setMailForm((p) => ({ ...p, encryption: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                </select>
                            </div>
                            <div className="flex items-end pb-2.5">
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" disabled={!canManageMail} checked={mailForm.enabled} onChange={(e) => setMailForm((p) => ({ ...p, enabled: e.target.checked }))} className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                    Enable email sending (system-wide)
                                </label>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">From Address *</label>
                                <input type="email" required disabled={!canManageMail} value={mailForm.from_address} onChange={(e) => setMailForm((p) => ({ ...p, from_address: e.target.value }))} placeholder="noreply@mge-eng.com" className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50" />
                                {mailErrors.from_address && <p className="mt-1 text-xs text-red-500">{mailErrors.from_address[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">From Name *</label>
                                <input type="text" required disabled={!canManageMail} value={mailForm.from_name} onChange={(e) => setMailForm((p) => ({ ...p, from_name: e.target.value }))} placeholder="MGE-PMS" className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50" />
                                {mailErrors.from_name && <p className="mt-1 text-xs text-red-500">{mailErrors.from_name[0]}</p>}
                            </div>
                        </div>
                        {canManageMail && (
                            <div className="flex justify-end pt-2">
                                <button type="submit" disabled={mailSaving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{mailSaving ? 'Saving...' : 'Save SMTP Settings'}</button>
                            </div>
                        )}
                    </form>

                    {canManageMail && (
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 className="mb-3 text-sm font-semibold text-gray-900">Send Test Email</h3>
                            <p className="mb-3 text-xs text-gray-500">Sends a real test email using the settings above (even if not saved yet), to confirm delivery works before enabling.</p>
                            <div className="flex gap-2">
                                <input type="email" value={testTo} onChange={(e) => setTestTo(e.target.value)} placeholder="you@example.com" className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                <button onClick={sendTestEmail} disabled={testSending} className="shrink-0 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">{testSending ? 'Sending...' : 'Send Test'}</button>
                            </div>
                            {testWarnings.length > 0 && (
                                <div className="mt-3 space-y-1 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                    {testWarnings.map((w, i) => <p key={i}>{w}</p>)}
                                </div>
                            )}
                        </div>
                    )}

                    {canViewMail && (
                        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div className="mb-3 flex items-center justify-between">
                                <h3 className="text-sm font-semibold text-gray-900">Delivery Status</h3>
                                <button onClick={loadMailStatus} disabled={mailStatusLoading} className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                                    {mailStatusLoading ? 'Refreshing...' : 'Refresh'}
                                </button>
                            </div>

                            {!mailStatus ? (
                                <p className="text-sm text-gray-400">{mailStatusLoading ? 'Loading...' : 'No data yet.'}</p>
                            ) : (
                                <>
                                    <div className="mb-4 flex flex-wrap gap-2">
                                        <span className={`rounded-full px-3 py-1 text-xs font-medium ${mailStatus.smtp_enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                            SMTP {mailStatus.smtp_enabled ? 'enabled' : 'disabled'}
                                        </span>
                                        <span className={`rounded-full px-3 py-1 text-xs font-medium ${mailStatus.effective_mailer === 'smtp' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}`}>
                                            Effective mailer: {mailStatus.effective_mailer}
                                        </span>
                                        <span className={`rounded-full px-3 py-1 text-xs font-medium ${mailStatus.notifications_email_enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                            Email notifications {mailStatus.notifications_email_enabled ? 'on' : 'off'}
                                        </span>
                                    </div>

                                    <div className="mb-4 flex gap-4 text-xs text-gray-600">
                                        <span>Last 7 days:</span>
                                        <span className="font-medium text-green-700">{mailStatus.last_7_days.sent} sent</span>
                                        <span className="font-medium text-gray-500">{mailStatus.last_7_days.skipped} skipped</span>
                                        <span className="font-medium text-red-600">{mailStatus.last_7_days.failed} failed</span>
                                    </div>

                                    {mailStatus.problems.length === 0 ? (
                                        <div className="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-xs text-green-700">
                                            All good — emails are being handed to SMTP.
                                        </div>
                                    ) : (
                                        <div className="mb-4 space-y-1 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                            {mailStatus.problems.map((p, i) => <p key={i}>{p}</p>)}
                                        </div>
                                    )}

                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-xs">
                                            <thead>
                                                <tr className="border-b border-gray-100 text-gray-400">
                                                    <th className="py-1.5 pr-3 font-medium">Time</th>
                                                    <th className="py-1.5 pr-3 font-medium">User</th>
                                                    <th className="py-1.5 pr-3 font-medium">Type</th>
                                                    <th className="py-1.5 pr-3 font-medium">Channel</th>
                                                    <th className="py-1.5 pr-3 font-medium">Status</th>
                                                    <th className="py-1.5 font-medium">Error</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {mailStatus.recent.length === 0 ? (
                                                    <tr><td colSpan={6} className="py-3 text-center text-gray-400">No log entries yet.</td></tr>
                                                ) : mailStatus.recent.map((r) => (
                                                    <tr key={r.id} className="border-b border-gray-50">
                                                        <td className="py-1.5 pr-3 whitespace-nowrap text-gray-500">{new Date(r.created_at).toLocaleString()}</td>
                                                        <td className="py-1.5 pr-3 text-gray-700">{r.user?.name || '—'}</td>
                                                        <td className="py-1.5 pr-3 text-gray-500">{r.type}</td>
                                                        <td className="py-1.5 pr-3 text-gray-500">{r.channel}</td>
                                                        <td className="py-1.5 pr-3">
                                                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${
                                                                r.status === 'sent' ? 'bg-green-100 text-green-700'
                                                                    : r.status === 'skipped' ? 'bg-gray-100 text-gray-600'
                                                                        : 'bg-red-100 text-red-700'
                                                            }`}>
                                                                {r.status}
                                                            </span>
                                                        </td>
                                                        <td className="max-w-[12rem] truncate py-1.5 text-gray-500" title={r.error || ''}>{r.error || '—'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </>
                            )}
                        </div>
                    )}
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
