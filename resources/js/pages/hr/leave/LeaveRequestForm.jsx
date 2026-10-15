import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import apiClient from '@/services/apiClient';
import leaveService from '@/services/leaveService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineArrowLeft } from 'react-icons/hi';
import LeaveDayBreakdown from './LeaveDayBreakdown';
import EmployeeSelect from '@/components/EmployeeSelect';

// yyyy-mm-dd in the browser's local calendar (not UTC), as an <input type="date"> expects.
const isoDate = (d) => {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};
const todayPlus = (days) => {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return isoDate(d);
};

const inputClass = 'w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';
const fileClass = 'w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100';

export default function LeaveRequestForm() {
    const navigate = useNavigate();
    const { can } = useAuth();
    // Filing on behalf needs both leave.manage AND the ability to list staff;
    // otherwise fall back to self-service (apply for your own leave).
    const canManage = can('leave.manage') && can('staff.view');
    const backTo = canManage ? '/hr/leave' : '/leave/my'; // employees can't open the org-wide list
    const [employees, setEmployees] = useState([]);
    const [myEmployee, setMyEmployee] = useState(null);
    // Who the current user may file for, and what else the form allows them
    // (null when the call failed — then we fall back to plain self-service).
    const [fileable, setFileable] = useState(null);
    const [types, setTypes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [fieldErrors, setFieldErrors] = useState({});
    const [preview, setPreview] = useState(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState(null);
    const [form, setForm] = useState({
        employee_id: '',
        leave_type_id: '',
        start_date: isoDate(new Date()),
        end_date: isoDate(new Date()),
        half_day: false,
        reason: '',
        attachment: null,
        notice_override_reason: '',
        approved_offline: false,
        offline_approver_name: '',
        offline_approval_note: '',
        approval_proof: null,
    });

    useEffect(() => {
        const tasks = [
            leaveService.listTypes(),
            leaveService.fileableEmployees().catch(() => null),
            leaveService.myEmployee().catch(() => null),
        ];
        // HR still loads the full staff list: it is the only source of gender,
        // which hides maternity leave for staff it does not apply to.
        if (canManage) tasks.push(apiClient.get('/employees', { params: { per_page: 100 } }).catch(() => null));

        Promise.all(tasks)
            .then(([typesRes, fileableRes, meRes, employeesRes]) => {
                setTypes(typesRes.data || []);
                const me = meRes?.data || null;
                setMyEmployee(me);
                if (employeesRes) {
                    setEmployees(employeesRes.data?.data?.data || employeesRes.data?.data || []);
                }

                const info = fileableRes?.data || null;
                setFileable(info);

                // Default to the user's own record; with only one choice, take it.
                const list = info?.employees || [];
                const self = list.find((e) => e.is_self);
                const defaultId = info
                    ? (self?.id ?? (list.length === 1 ? list[0].id : null))
                    : me?.id;
                if (defaultId) setForm((p) => ({ ...p, employee_id: String(defaultId) }));
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [canManage]);

    const selectedType = types.find((t) => String(t.id) === String(form.leave_type_id));

    const fileableList = fileable?.employees || [];
    const usePicker = fileableList.length > 1;
    const selectedFileable = fileableList.find((e) => String(e.id) === String(form.employee_id));
    // EmployeeSelect wants full_name; the endpoint calls it name.
    const pickerEmployees = fileableList.map((e) => ({ id: e.id, employee_no: e.employee_no, full_name: e.name }));
    const noProfile = fileable ? fileableList.length === 0 : !canManage && !myEmployee;

    // Maternity leave is only available to female staff. Gender is known for
    // the user's own record and (for HR) the full staff list; when it is not
    // known the server makes the call.
    const applyingEmployee = employees.find((e) => String(e.id) === String(form.employee_id))
        || (String(myEmployee?.id) === String(form.employee_id) ? myEmployee : null);
    const genderKnown = !!applyingEmployee;
    const isFemale = applyingEmployee?.gender === 'female';
    const visibleTypes = types.filter((t) => !genderKnown || isFemale || !/maternity/i.test(t.name || ''));

    // Notice rule (e.g. Annual leave: 3 days ahead). Most people cannot file
    // inside the window; HR can, but must say why.
    const noticeDays = selectedType ? fileable?.notice_days?.[selectedType.code] : null;
    const canOverrideNotice = !!fileable?.can_override_notice;
    const noticeMin = noticeDays ? todayPlus(Number(noticeDays)) : null;
    const needsOverrideReason = !!noticeMin && canOverrideNotice && !!form.start_date && form.start_date < noticeMin;

    // A Site Clerk (or HR) recording an approval the head/PM gave outside the system.
    const showOffline = !!fileable?.can_record_offline_approval && !!selectedFileable && !selectedFileable.is_self;
    const offline = showOffline && form.approved_offline;

    const errorOf = (field) => fieldErrors[field]?.[0];

    const handleTypeChange = (typeId) => {
        const type = types.find((t) => String(t.id) === String(typeId));
        const days = type ? fileable?.notice_days?.[type.code] : null;
        const min = days ? todayPlus(Number(days)) : null;
        setForm((p) => ({
            ...p,
            leave_type_id: typeId,
            // Move a too-early start date to the first allowed day (end date follows via the sync effect).
            start_date: min && !canOverrideNotice && p.start_date < min ? min : p.start_date,
        }));
    };

    // Clear a chosen type that is no longer offered (e.g. after switching employee).
    useEffect(() => {
        if (form.leave_type_id && !visibleTypes.some((t) => String(t.id) === String(form.leave_type_id))) {
            setForm((p) => ({ ...p, leave_type_id: '' }));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isFemale, genderKnown, types, form.leave_type_id]);

    // Keep end_date in sync with start_date when toggling half-day mode
    useEffect(() => {
        setForm((p) => {
            let nextEndDate = p.end_date;
            if (p.half_day) {
                nextEndDate = p.start_date;
            } else if (p.end_date && p.end_date < p.start_date) {
                nextEndDate = p.start_date;
            }
            return nextEndDate !== p.end_date ? { ...p, end_date: nextEndDate } : p;
        });
    }, [form.half_day, form.start_date]);

    // Ask the server what this request would actually cost. Debounced, because
    // it fires on every date keystroke. The server is the only correct source
    // here — rest days and public holidays are policy data, so re-deriving them
    // in the browser would risk showing a number the backend disagrees with.
    useEffect(() => {
        const { employee_id, leave_type_id, start_date, end_date, half_day } = form;

        if (!employee_id || !leave_type_id || !start_date || !end_date) {
            setPreview(null);
            setPreviewError(null);
            return;
        }

        let cancelled = false;
        setPreviewLoading(true);

        const timer = setTimeout(() => {
            leaveService
                .preview({
                    employee_id,
                    leave_type_id,
                    start_date,
                    end_date: half_day ? start_date : end_date,
                    half_day,
                })
                .then((res) => {
                    if (cancelled) return;
                    setPreview(res.data);
                    setPreviewError(null);
                })
                .catch((err) => {
                    if (cancelled) return;
                    setPreview(null);
                    setPreviewError(err.response?.data?.message || null);
                })
                .finally(() => {
                    if (!cancelled) setPreviewLoading(false);
                });
        }, 350);

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [form.employee_id, form.leave_type_id, form.start_date, form.end_date, form.half_day]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setFieldErrors({});
        try {
            const formData = new FormData();
            formData.append('employee_id', form.employee_id);
            formData.append('leave_type_id', form.leave_type_id);
            formData.append('start_date', form.start_date);
            formData.append('end_date', form.half_day ? form.start_date : form.end_date);
            formData.append('half_day', form.half_day ? '1' : '0');
            if (form.reason) formData.append('reason', form.reason);
            if (form.attachment) formData.append('attachment', form.attachment);
            if (needsOverrideReason) formData.append('notice_override_reason', form.notice_override_reason);
            if (offline) {
                formData.append('approved_offline', '1');
                formData.append('offline_approver_name', form.offline_approver_name);
                if (form.offline_approval_note) formData.append('offline_approval_note', form.offline_approval_note);
                if (form.approval_proof) formData.append('approval_proof', form.approval_proof);
            }

            await leaveService.apply(formData);
            toast.success(offline ? 'Saved as approved. HR will record it.' : 'Leave request submitted');
            navigate(backTo);
        } catch (err) {
            const data = err.response?.data;
            if (err.response?.status === 422 && data?.errors) {
                // Show each message next to its field, and the first one as a toast.
                setFieldErrors(data.errors);
                toast.error(Object.values(data.errors).flat()[0] || data.message || 'Failed to submit leave request');
            } else {
                toast.error(data?.message || 'Failed to submit leave request');
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;

    return (
        <div>
            <div className="mb-6">
                <button
                    onClick={() => navigate(backTo)}
                    className="mb-3 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
                >
                    <HiOutlineArrowLeft className="h-4 w-4" /> Back to Leave Requests
                </button>
                <h1 className="text-2xl font-bold text-gray-900">Apply Leave</h1>
                <p className="text-sm text-gray-500">Submit a new leave application</p>
            </div>

            <div className="max-w-2xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <form onSubmit={handleSubmit} className="space-y-4">
                    {noProfile && (
                        <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700 ring-1 ring-amber-200">
                            No employee profile is linked to your account. Please ask HR to link your staff record before applying for leave.
                        </div>
                    )}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">{usePicker ? 'Staff *' : 'Employee *'}</label>
                            {usePicker ? (
                                // Searchable picker: HR can file for anyone, so a plain list gets long.
                                <EmployeeSelect
                                    employees={pickerEmployees}
                                    value={form.employee_id}
                                    onChange={(id) => setForm((p) => ({ ...p, employee_id: id }))}
                                    allowClear={false}
                                    required
                                />
                            ) : !fileable && canManage ? (
                                <EmployeeSelect
                                    employees={employees}
                                    value={form.employee_id}
                                    onChange={(id) => setForm((p) => ({ ...p, employee_id: id }))}
                                    required
                                />
                            ) : (
                                <input
                                    type="text"
                                    value={fileable ? (fileableList[0]?.name || '') : (myEmployee?.full_name || '')}
                                    readOnly
                                    className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-600"
                                />
                            )}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Leave Type *</label>
                            <select
                                value={form.leave_type_id}
                                onChange={(e) => handleTypeChange(e.target.value)}
                                required
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="">Select type</option>
                                {visibleTypes.map((t) => (
                                    <option key={t.id} value={t.id}>{t.name} ({t.code})</option>
                                ))}
                            </select>
                            {selectedType && (
                                <p className="mt-1 text-xs text-gray-500">
                                    Approval required: {selectedType.requires_director_approval ? 'Manager → Director' : 'Manager'}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Start Date *</label>
                            <input
                                type="date"
                                value={form.start_date}
                                onChange={(e) => setForm((p) => ({ ...p, start_date: e.target.value }))}
                                required
                                min={noticeMin && !canOverrideNotice ? noticeMin : undefined}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {noticeDays && !canOverrideNotice && (
                                <p className="mt-1 text-xs text-gray-500">
                                    Annual leave must be applied for at least {noticeDays} days before it starts
                                </p>
                            )}
                            {errorOf('start_date') && <p className="mt-1 text-xs text-red-600">{errorOf('start_date')}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">End Date *</label>
                            <input
                                type="date"
                                value={form.half_day ? form.start_date : form.end_date}
                                onChange={(e) => setForm((p) => ({ ...p, end_date: e.target.value }))}
                                required
                                disabled={form.half_day}
                                min={form.start_date}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            id="half_day"
                            type="checkbox"
                            checked={form.half_day}
                            onChange={(e) => setForm((p) => ({ ...p, half_day: e.target.checked }))}
                            className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        <label htmlFor="half_day" className="text-sm text-gray-700">Half day (0.5 day)</label>
                    </div>

                    <LeaveDayBreakdown
                        preview={preview}
                        loading={previewLoading}
                        error={previewError}
                        leaveTypeName={selectedType?.name}
                    />

                    {Number(preview?.shortfall_days) > 0 && (
                        <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700 ring-1 ring-amber-200">
                            This request goes {Number(preview.shortfall_days)} day(s) over the balance. You can still submit; HR will decide when recording whether the excess is unpaid leave.
                        </div>
                    )}

                    {needsOverrideReason && (
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Reason for short-notice exception <span className="text-red-500">*</span>
                            </label>
                            <textarea
                                rows={2}
                                required
                                value={form.notice_override_reason}
                                onChange={(e) => setForm((p) => ({ ...p, notice_override_reason: e.target.value }))}
                                className={inputClass}
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                This starts less than {noticeDays} days from today. Say why it is being filed late.
                            </p>
                            {errorOf('notice_override_reason') && <p className="mt-1 text-xs text-red-600">{errorOf('notice_override_reason')}</p>}
                        </div>
                    )}

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Reason</label>
                        <textarea
                            rows={3}
                            value={form.reason}
                            onChange={(e) => setForm((p) => ({ ...p, reason: e.target.value }))}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Attachment {selectedType?.requires_attachment && <span className="text-red-500">*</span>}
                        </label>
                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            required={!!selectedType?.requires_attachment}
                            onChange={(e) => setForm((p) => ({ ...p, attachment: e.target.files[0] || null }))}
                            className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100"
                        />
                        <p className="mt-1 text-xs text-gray-400">PDF, JPG or PNG. Max 5MB.</p>
                    </div>

                    {showOffline && (
                        <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={form.approved_offline}
                                    onChange={(e) => setForm((p) => ({ ...p, approved_offline: e.target.checked }))}
                                    className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                Approval already received (email, WhatsApp or signed form)
                            </label>

                            {form.approved_offline && (
                                <div className="mt-4 space-y-4">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">
                                            Approved by (head / PM name) <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            required
                                            maxLength={255}
                                            value={form.offline_approver_name}
                                            onChange={(e) => setForm((p) => ({ ...p, offline_approver_name: e.target.value }))}
                                            className={inputClass}
                                        />
                                        {errorOf('offline_approver_name') && <p className="mt-1 text-xs text-red-600">{errorOf('offline_approver_name')}</p>}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">How it was received / note</label>
                                        <input
                                            type="text"
                                            maxLength={255}
                                            value={form.offline_approval_note}
                                            onChange={(e) => setForm((p) => ({ ...p, offline_approval_note: e.target.value }))}
                                            placeholder="e.g. WhatsApp message on 12 Oct"
                                            className={inputClass}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">
                                            Proof (screenshot or signed form) <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            required
                                            onChange={(e) => setForm((p) => ({ ...p, approval_proof: e.target.files[0] || null }))}
                                            className={fileClass}
                                        />
                                        <p className="mt-1 text-xs text-gray-400">PDF, JPG or PNG. Max 20MB.</p>
                                        {errorOf('approval_proof') && <p className="mt-1 text-xs text-red-600">{errorOf('approval_proof')}</p>}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={() => navigate(backTo)}
                            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={saving || noProfile}
                            className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                        >
                            {saving ? 'Submitting...' : 'Submit Request'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
