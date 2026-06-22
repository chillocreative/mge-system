import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import staffService from '@/services/staffService';
import apiClient from '@/services/apiClient';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';

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
    const isEdit = !!id;

    const [loading, setLoading] = useState(isEdit);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(emptyForm);
    const [photo, setPhoto] = useState(null);
    const [errors, setErrors] = useState({});

    const [departments, setDepartments] = useState([]);
    const [designations, setDesignations] = useState([]);
    const [managers, setManagers] = useState([]);
    const [users, setUsers] = useState([]);
    const [showSuggest, setShowSuggest] = useState(false);

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
    }, []);

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
                // Skip self as manager and skip empty optional values
                if (k === 'reporting_manager_id' && String(v) === String(id)) return;
                if (v !== '' && v !== null && v !== undefined) fd.append(k, v);
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
                                    onChange={(e) => { set('full_name', e.target.value); set('user_id', ''); setShowSuggest(true); }}
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
