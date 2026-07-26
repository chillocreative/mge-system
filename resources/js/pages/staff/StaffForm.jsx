import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import staffService from '@/services/staffService';
import apiClient from '@/services/apiClient';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineLink, HiOutlineX, HiOutlinePlus, HiOutlineUserAdd } from 'react-icons/hi';

const SUPER_ADMIN_ROLE = 'Admin & HR';
const emptyLogin = { full_name: '', email: '', password: '', role: '' };

const emptyForm = {
    employee_no: '',
    user_id: '',
    full_name: '',
    ic_passport_no: '',
    email: '',
    phone: '',
    gender: '',
    dob: '',
    address: '',
    department_id: '',
    designation_id: '',
    employment_type: 'full_time',
    category: 'office',
    hire_date: '',
    resign_date: '',
    reporting_manager_id: '',
    bank_name: '',
    bank_account_no: '',
    epf_no: '',
    socso_no: '',
    tax_no: '',
    base_salary: '',
    status: 'active',
};

const inputClass =
    'w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

function Field({ label, name, errors, children }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {children}
            {errors?.[name] && <p className="mt-1 text-xs text-red-500">{errors[name][0]}</p>}
        </div>
    );
}

export default function StaffForm() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { can, user: authUser } = useAuth();
    const isEdit = !!id;
    const canCreateLogin = can('users.create');

    const [loading, setLoading] = useState(isEdit);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(emptyForm);
    const [photo, setPhoto] = useState(null);
    const [errors, setErrors] = useState({});

    const [departments, setDepartments] = useState([]);
    const [designations, setDesignations] = useState([]);
    const [managers, setManagers] = useState([]);
    const [users, setUsers] = useState([]);
    const [roles, setRoles] = useState([]);
    const [showSuggest, setShowSuggest] = useState(false);

    // Login account linking
    const [linkSearch, setLinkSearch] = useState('');
    const [createLoginOpen, setCreateLoginOpen] = useState(false);
    const [loginForm, setLoginForm] = useState(emptyLogin);
    const [loginErrors, setLoginErrors] = useState({});
    const [creatingLogin, setCreatingLogin] = useState(false);

    const set = (field, value) => {
        setForm((p) => ({ ...p, [field]: value }));
        setErrors((p) => ({ ...p, [field]: undefined }));
    };

    useEffect(() => {
        const unwrap = (r) => r.data?.data?.data || r.data?.data || [];
        Promise.all([
            apiClient.get('/departments', { params: { per_page: 100 } }),
            apiClient.get('/designations', { params: { per_page: 100 } }),
            apiClient.get('/employees', { params: { per_page: 100, status: 'active' } }),
            apiClient.get('/users', { params: { per_page: 200 } }),
        ])
            .then(([dRes, gRes, eRes, uRes]) => {
                setDepartments(unwrap(dRes));
                setDesignations(unwrap(gRes));
                setManagers(unwrap(eRes));
                setUsers(unwrap(uRes));
            })
            .catch(() => {});
        if (canCreateLogin) {
            apiClient.get('/roles').then((r) => setRoles(r.data?.data || [])).catch(() => {});
        }
    }, [canCreateLogin]);

    const linkedUser = users.find((u) => String(u.id) === String(form.user_id));
    const linkQuery = linkSearch.trim().toLowerCase();
    const linkMatches = linkQuery.length >= 2
        ? users.filter((u) => `${u.full_name} ${u.email}`.toLowerCase().includes(linkQuery)).slice(0, 8)
        : [];

    const linkExisting = (u) => {
        setUsers((prev) => (prev.some((x) => x.id === u.id) ? prev : [...prev, u]));
        set('user_id', u.id);
        setLinkSearch('');
    };

    const createLogin = async (e) => {
        e.preventDefault();
        setCreatingLogin(true);
        setLoginErrors({});
        try {
            const res = await apiClient.post('/users', {
                full_name: loginForm.full_name,
                email: loginForm.email,
                password: loginForm.password,
                role: loginForm.role,
                phone: form.phone || null,
                department_id: form.department_id || null,
                designation_id: form.designation_id || null,
            });
            const created = res.data?.data || res.data;
            setUsers((prev) => [...prev, created]);
            set('user_id', created.id);
            toast.success('Login account created and linked');
            setCreateLoginOpen(false);
            setLoginForm(emptyLogin);
        } catch (err) {
            if (err.response?.status === 422) setLoginErrors(err.response.data?.errors || {});
            else toast.error(err.response?.data?.message || 'Failed to create login');
        } finally {
            setCreatingLogin(false);
        }
    };

    // Prefill the staff form from an existing user account.
    const pickUser = (u) => {
        setForm((p) => ({
            ...p,
            user_id: u.id,
            full_name: u.full_name || '',
            email: u.email || p.email,
            phone: u.phone || p.phone,
            ic_passport_no: u.ic_number || p.ic_passport_no,
            department_id: u.department?.id ? String(u.department.id) : p.department_id,
            designation_id: u.designation?.id ? String(u.designation.id) : p.designation_id,
        }));
        setShowSuggest(false);
        setErrors({});
    };

    const nameQuery = form.full_name.trim().toLowerCase();
    const suggestions = nameQuery.length >= 3
        ? users.filter((u) => (u.full_name || '').toLowerCase().includes(nameQuery)).slice(0, 8)
        : [];

    useEffect(() => {
        if (!isEdit) return;
        (async () => {
            setLoading(true);
            try {
                const res = await staffService.get(id);
                const e = res.data;
                setForm({
                    ...emptyForm,
                    ...Object.fromEntries(Object.keys(emptyForm).map((k) => [k, e[k] ?? ''])),
                });
            } catch {
                toast.error('Failed to load staff member');
                navigate('/staff');
            } finally {
                setLoading(false);
            }
        })();
    }, [id]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const fd = new FormData();
            Object.entries(form).forEach(([k, v]) => {
                // Skip self as manager
                if (k === 'reporting_manager_id' && String(v) === String(id)) return;
                if (v === null || v === undefined) return;
                // Send empty strings too (not just truthy values) — the backend's
                // nullable validation rules + ConvertEmptyStringsToNull middleware
                // turn '' into null, which is what actually clears a field on update.
                // Previously empty values were skipped entirely, so a field (including
                // unlinking the login account via user_id) could be set but never cleared.
                fd.append(k, v);
            });
            if (photo) fd.append('photo', photo);

            if (isEdit) {
                await staffService.update(id, fd);
                toast.success('Staff member updated');
            } else {
                await staffService.create(fd);
                toast.success('Staff member created');
            }
            navigate('/staff');
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors || {});
                toast.error('Please fix the validation errors');
            } else {
                toast.error(err.response?.data?.message || 'Failed to save staff member');
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;

    return (
        <div className="mx-auto max-w-4xl">
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">{isEdit ? 'Edit Staff' : 'New Staff'}</h1>
                <p className="text-sm text-gray-500">{isEdit ? 'Update employee record' : 'Add a new employee to the registry'}</p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Personal Particulars */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Personal Particulars</h2>

                    <div className="mb-4">
                        <label className="mb-1 block text-sm font-medium text-gray-700">Photo</label>
                        <div className="flex items-center gap-4">
                            {isEdit && form.employee_no && (
                                <img
                                    src={photo ? URL.createObjectURL(photo) : staffService.getPhotoUrl(id)}
                                    alt="Staff"
                                    className="h-16 w-16 rounded-full object-cover ring-1 ring-gray-200"
                                    onError={(ev) => { ev.target.style.display = 'none'; }}
                                />
                            )}
                            <input
                                type="file"
                                accept="image/*"
                                onChange={(e) => setPhoto(e.target.files[0] || null)}
                                className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100"
                            />
                        </div>
                        {errors.photo && <p className="mt-1 text-xs text-red-500">{errors.photo[0]}</p>}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Employee No *" name="employee_no" errors={errors}>
                            <input value={form.employee_no} onChange={(e) => set('employee_no', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="IC / Passport No" name="ic_passport_no" errors={errors}>
                            <input value={form.ic_passport_no} onChange={(e) => set('ic_passport_no', e.target.value)} className={inputClass} />
                        </Field>
                        <div className="relative sm:col-span-2">
                            <Field label="Full Name *" name="full_name" errors={errors}>
                                <input
                                    value={form.full_name}
                                    onChange={(e) => { set('full_name', e.target.value); setShowSuggest(true); }}
                                    onFocus={() => setShowSuggest(true)}
                                    onBlur={() => setTimeout(() => setShowSuggest(false), 150)}
                                    autoComplete="off"
                                    placeholder="Type 3+ letters to search existing users…"
                                    className={inputClass}
                                />
                            </Field>
                            {form.user_id && <p className="mt-1 text-xs text-primary-600">Linked to user account · fields prefilled</p>}
                            {showSuggest && suggestions.length > 0 && (
                                <ul className="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                    {suggestions.map((u) => (
                                        <li key={u.id}>
                                            <button type="button" onMouseDown={(e) => e.preventDefault()} onClick={() => pickUser(u)}
                                                className="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-gray-50">
                                                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">
                                                    {(u.full_name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase()}
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="block truncate text-sm font-medium text-gray-900">{u.full_name}</span>
                                                    <span className="block truncate text-xs text-gray-400">{u.email}</span>
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        <Field label="Email" name="email" errors={errors}>
                            <input type="email" value={form.email} onChange={(e) => set('email', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Phone" name="phone" errors={errors}>
                            <input value={form.phone} onChange={(e) => set('phone', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Gender" name="gender" errors={errors}>
                            <select value={form.gender} onChange={(e) => set('gender', e.target.value)} className={inputClass}>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </Field>
                        <Field label="Date of Birth" name="dob" errors={errors}>
                            <input type="date" value={form.dob} onChange={(e) => set('dob', e.target.value)} className={inputClass} />
                        </Field>
                        <div className="sm:col-span-2">
                            <Field label="Address" name="address" errors={errors}>
                                <textarea rows={2} value={form.address} onChange={(e) => set('address', e.target.value)} className={inputClass} />
                            </Field>
                        </div>
                    </div>
                </div>

                {/* Login Account */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-1 text-sm font-semibold uppercase text-gray-500">Login Account</h2>
                    <p className="mb-4 text-xs text-gray-500">Link a login so this staff can use Leave &amp; Training self-service.</p>
                    {errors.user_id && <p className="mb-3 text-xs text-red-500">{errors.user_id[0]}</p>}

                    {linkedUser ? (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3">
                            <div className="flex items-center gap-3">
                                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">
                                    {(linkedUser.full_name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase()}
                                </span>
                                <div>
                                    <p className="text-sm font-semibold text-gray-900">{linkedUser.full_name}</p>
                                    <p className="text-xs text-gray-500">{linkedUser.email}{linkedUser.roles?.[0] ? ` · ${linkedUser.roles[0]}` : ''}</p>
                                </div>
                            </div>
                            <button type="button" onClick={() => set('user_id', '')}
                                className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                <HiOutlineX className="h-4 w-4" /> Unlink
                            </button>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {/* Link existing */}
                            <div className="relative">
                                <label className="mb-1 block text-sm font-medium text-gray-700">Link an existing user</label>
                                <div className="flex items-center gap-2">
                                    <HiOutlineLink className="h-4 w-4 shrink-0 text-gray-400" />
                                    <input value={linkSearch} onChange={(e) => setLinkSearch(e.target.value)} autoComplete="off"
                                        placeholder="Search by name or email…" className={inputClass} />
                                </div>
                                {linkMatches.length > 0 && (
                                    <ul className="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                        {linkMatches.map((u) => (
                                            <li key={u.id}>
                                                <button type="button" onClick={() => linkExisting(u)}
                                                    className="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-gray-50">
                                                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-medium text-primary-700">
                                                        {(u.full_name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase()}
                                                    </span>
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-sm font-medium text-gray-900">{u.full_name}</span>
                                                        <span className="block truncate text-xs text-gray-400">{u.email}</span>
                                                    </span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>

                            {/* Create new login */}
                            {canCreateLogin && !createLoginOpen && (
                                <button type="button"
                                    onClick={() => { setCreateLoginOpen(true); setLoginErrors({}); setLoginForm({ ...emptyLogin, full_name: form.full_name, email: form.email }); }}
                                    className="inline-flex items-center gap-2 rounded-lg border border-dashed border-primary-300 px-4 py-2.5 text-sm font-semibold text-primary-600 hover:bg-primary-50">
                                    <HiOutlineUserAdd className="h-4 w-4" /> Create a new login
                                </button>
                            )}

                            {canCreateLogin && createLoginOpen && (
                                <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <p className="text-sm font-semibold text-gray-800">New login account</p>
                                        <button type="button" onClick={() => setCreateLoginOpen(false)} className="rounded p-1 text-gray-400 hover:bg-gray-100"><HiOutlineX className="h-4 w-4" /></button>
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-700">Full Name *</label>
                                            <input value={loginForm.full_name} onChange={(e) => setLoginForm((p) => ({ ...p, full_name: e.target.value }))} className={inputClass} />
                                            {loginErrors.full_name && <p className="mt-1 text-xs text-red-500">{loginErrors.full_name[0]}</p>}
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-700">Email *</label>
                                            <input type="email" value={loginForm.email} onChange={(e) => setLoginForm((p) => ({ ...p, email: e.target.value }))} className={inputClass} />
                                            {loginErrors.email && <p className="mt-1 text-xs text-red-500">{loginErrors.email[0]}</p>}
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-700">Password *</label>
                                            <input type="password" minLength={8} value={loginForm.password} onChange={(e) => setLoginForm((p) => ({ ...p, password: e.target.value }))} placeholder="Min. 8 characters" className={inputClass} />
                                            {loginErrors.password && <p className="mt-1 text-xs text-red-500">{loginErrors.password[0]}</p>}
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-700">Role *</label>
                                            <select value={loginForm.role} onChange={(e) => setLoginForm((p) => ({ ...p, role: e.target.value }))} className={inputClass}>
                                                <option value="">Select role…</option>
                                                {roles.filter((r) => r.name !== SUPER_ADMIN_ROLE || authUser?.is_protected).map((r) => <option key={r.id} value={r.name}>{r.name}</option>)}
                                            </select>
                                            {loginErrors.role && <p className="mt-1 text-xs text-red-500">{loginErrors.role[0]}</p>}
                                        </div>
                                    </div>
                                    <div className="mt-3 flex justify-end">
                                        <button type="button" onClick={createLogin} disabled={creatingLogin}
                                            className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                            <HiOutlinePlus className="h-4 w-4" /> {creatingLogin ? 'Creating…' : 'Create & Link'}
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Employment */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Employment</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Department" name="department_id" errors={errors}>
                            <select value={form.department_id} onChange={(e) => set('department_id', e.target.value)} className={inputClass}>
                                <option value="">None</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>{d.name}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Designation" name="designation_id" errors={errors}>
                            <select value={form.designation_id} onChange={(e) => set('designation_id', e.target.value)} className={inputClass}>
                                <option value="">None</option>
                                {designations.map((d) => (
                                    <option key={d.id} value={d.id}>{d.name}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Employment Type" name="employment_type" errors={errors}>
                            <select value={form.employment_type} onChange={(e) => set('employment_type', e.target.value)} className={inputClass}>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="site_worker">Site Worker</option>
                            </select>
                        </Field>
                        <Field label="Category" name="category" errors={errors}>
                            <select value={form.category} onChange={(e) => set('category', e.target.value)} className={inputClass}>
                                <option value="office">Office</option>
                                <option value="site">Site</option>
                            </select>
                        </Field>
                        <Field label="Reporting Manager" name="reporting_manager_id" errors={errors}>
                            <select value={form.reporting_manager_id} onChange={(e) => set('reporting_manager_id', e.target.value)} className={inputClass}>
                                <option value="">None</option>
                                {managers.filter((m) => String(m.id) !== String(id)).map((m) => (
                                    <option key={m.id} value={m.id}>{m.full_name} ({m.employee_no})</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Status" name="status" errors={errors}>
                            <select value={form.status} onChange={(e) => set('status', e.target.value)} className={inputClass}>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="resigned">Resigned</option>
                            </select>
                        </Field>
                        <Field label="Hire Date" name="hire_date" errors={errors}>
                            <input type="date" value={form.hire_date} onChange={(e) => set('hire_date', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Resign Date" name="resign_date" errors={errors}>
                            <input type="date" value={form.resign_date} onChange={(e) => set('resign_date', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Base Salary (RM)" name="base_salary" errors={errors}>
                            <input type="number" min="0" step="0.01" value={form.base_salary} onChange={(e) => set('base_salary', e.target.value)} className={inputClass} />
                        </Field>
                    </div>
                </div>

                {/* Bank & Statutory */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Bank & Statutory</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Bank Name" name="bank_name" errors={errors}>
                            <input value={form.bank_name} onChange={(e) => set('bank_name', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Bank Account No" name="bank_account_no" errors={errors}>
                            <input value={form.bank_account_no} onChange={(e) => set('bank_account_no', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="EPF No" name="epf_no" errors={errors}>
                            <input value={form.epf_no} onChange={(e) => set('epf_no', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="SOCSO No" name="socso_no" errors={errors}>
                            <input value={form.socso_no} onChange={(e) => set('socso_no', e.target.value)} className={inputClass} />
                        </Field>
                        <Field label="Tax No" name="tax_no" errors={errors}>
                            <input value={form.tax_no} onChange={(e) => set('tax_no', e.target.value)} className={inputClass} />
                        </Field>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={() => navigate('/staff')}
                        className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={saving}
                        className="rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50"
                    >
                        {saving ? 'Saving...' : isEdit ? 'Update Staff' : 'Create Staff'}
                    </button>
                </div>
            </form>
        </div>
    );
}
