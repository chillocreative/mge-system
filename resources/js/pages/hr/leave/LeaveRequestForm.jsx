import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import apiClient from '@/services/apiClient';
import leaveService from '@/services/leaveService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineArrowLeft } from 'react-icons/hi';

export default function LeaveRequestForm() {
    const navigate = useNavigate();
    const [employees, setEmployees] = useState([]);
    const [types, setTypes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({
        employee_id: '',
        leave_type_id: '',
        start_date: new Date().toISOString().split('T')[0],
        end_date: new Date().toISOString().split('T')[0],
        half_day: false,
        reason: '',
        attachment: null,
    });

    useEffect(() => {
        Promise.all([
            apiClient.get('/employees', { params: { per_page: 100 } }),
            leaveService.listTypes(),
        ])
            .then(([empRes, typeRes]) => {
                setEmployees(empRes.data?.data?.data || empRes.data?.data || []);
                setTypes(typeRes.data || []);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const selectedType = types.find((t) => String(t.id) === String(form.leave_type_id));

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const formData = new FormData();
            formData.append('employee_id', form.employee_id);
            formData.append('leave_type_id', form.leave_type_id);
            formData.append('start_date', form.start_date);
            formData.append('end_date', form.half_day ? form.start_date : form.end_date);
            formData.append('half_day', form.half_day ? '1' : '0');
            if (form.reason) formData.append('reason', form.reason);
            if (form.attachment) formData.append('attachment', form.attachment);

            await leaveService.apply(formData);
            toast.success('Leave request submitted');
            navigate('/hr/leave');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to submit leave request');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;

    return (
        <div>
            <div className="mb-6">
                <button
                    onClick={() => navigate('/hr/leave')}
                    className="mb-3 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
                >
                    <HiOutlineArrowLeft className="h-4 w-4" /> Back to Leave Requests
                </button>
                <h1 className="text-2xl font-bold text-gray-900">Apply Leave</h1>
                <p className="text-sm text-gray-500">Submit a new leave application</p>
            </div>

            <div className="max-w-2xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Employee *</label>
                            <select
                                value={form.employee_id}
                                onChange={(e) => setForm((p) => ({ ...p, employee_id: e.target.value }))}
                                required
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="">Select employee</option>
                                {employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>{emp.full_name || `${emp.first_name} ${emp.last_name}`}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Leave Type *</label>
                            <select
                                value={form.leave_type_id}
                                onChange={(e) => setForm((p) => ({ ...p, leave_type_id: e.target.value }))}
                                required
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="">Select type</option>
                                {types.map((t) => (
                                    <option key={t.id} value={t.id}>{t.name} ({t.code})</option>
                                ))}
                            </select>
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
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
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

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={() => navigate('/hr/leave')}
                            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
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
