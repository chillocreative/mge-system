import { useState, useEffect } from 'react';
import trainingService from '@/services/trainingService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import useDragScroll from '@/hooks/useDragScroll';
import toast from 'react-hot-toast';
import { HiOutlineAcademicCap, HiOutlinePlus, HiOutlineX } from 'react-icons/hi';

const CATEGORIES = ['Safety', 'Technical', 'Compliance', 'Soft Skills', 'Leadership', 'Certification', 'Other'];

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    scheduled: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
};

const money = (n) => 'RM ' + Number(n || 0).toLocaleString('en-MY', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
const emptyForm = { title: '', category: '', reason: '', preferred_date: '', estimated_cost: '' };

export default function MyTraining() {
    const [employee, setEmployee] = useState(null);
    const [records, setRecords] = useState([]);
    const dragScrollRef1 = useDragScroll();
    const dragScrollRef2 = useDragScroll();
    const [requests, setRequests] = useState([]);
    const [notice, setNotice] = useState(null);
    const [loading, setLoading] = useState(true);
    const [modal, setModal] = useState(false);
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    const load = () => {
        trainingService.my()
            .then((r) => {
                setEmployee(r.data?.employee || null);
                setRecords(r.data?.records || []);
                setRequests(r.data?.requests || []);
                setNotice(r.data?.notice || null);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    };

    useEffect(() => { load(); }, []);

    const submit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            await trainingService.createRequest({
                title: form.title,
                category: form.category || null,
                reason: form.reason || null,
                preferred_date: form.preferred_date,
                estimated_cost: form.estimated_cost === '' ? null : Number(form.estimated_cost),
            });
            toast.success('Training request submitted');
            setModal(false);
            setForm(emptyForm);
            load();
        } catch (err) {
            if (err.response?.status === 422) setErrors(err.response.data?.errors || {});
            else toast.error(err.response?.data?.message || 'Failed to submit request');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <HiOutlineAcademicCap className="h-7 w-7 text-primary-600" />
                        My Training
                    </h1>
                    <p className="text-sm text-gray-500">Your training history and requests</p>
                </div>
                {employee && (
                    <button onClick={() => { setForm(emptyForm); setErrors({}); setModal(true); }} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                        <HiOutlinePlus className="h-5 w-5" /> Request Training
                    </button>
                )}
            </div>

            {!employee ? (
                <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700 ring-1 ring-amber-200">
                    No employee profile is linked to your account. Please ask HR to link your staff record before requesting training.
                </div>
            ) : (
                <div className="space-y-8">
                    {/* History */}
                    <div>
                        <h2 className="mb-3 text-lg font-semibold text-gray-900">Training History</h2>
                        {records.length === 0 ? (
                            <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                                <HiOutlineAcademicCap className="mx-auto h-12 w-12 text-gray-300" />
                                <p className="mt-2 text-sm text-gray-500">No training attended yet</p>
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                                <div ref={dragScrollRef1} className="overflow-x-auto cursor-grab active:cursor-grabbing">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Training</th>
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Days</th>
                                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">HRDF</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {records.map((rec) => (
                                                <tr key={rec.id} className="hover:bg-gray-50">
                                                    <td className="px-4 py-3">
                                                        <p className="text-sm font-medium text-gray-900">{rec.title}</p>
                                                        <p className="text-xs text-gray-500">{[rec.provider, rec.category].filter(Boolean).join(' · ')}</p>
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{formatDate(rec.training_date)}</td>
                                                    <td className="px-4 py-3 text-right text-sm text-gray-600">{rec.duration_days ?? '-'}</td>
                                                    <td className="px-4 py-3 text-center">
                                                        {rec.hrdf_claimable
                                                            ? <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Yes</span>
                                                            : <span className="text-xs text-gray-400">No</span>}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Requests */}
                    <div>
                        <h2 className="mb-3 text-lg font-semibold text-gray-900">My Requests</h2>
                        {requests.length === 0 ? (
                            <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                                <p className="text-sm text-gray-500">No requests yet. Request a training above.</p>
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                                <div ref={dragScrollRef2} className="overflow-x-auto cursor-grab active:cursor-grabbing">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Training</th>
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Preferred</th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Est. Cost</th>
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {requests.map((req) => (
                                                <tr key={req.id} className="hover:bg-gray-50">
                                                    <td className="px-4 py-3">
                                                        <p className="text-sm font-medium text-gray-900">{req.title}</p>
                                                        {req.review_notes && <p className="text-xs text-gray-500">HR: {req.review_notes}</p>}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{req.preferred_date || '—'}</td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600">{req.estimated_cost ? money(req.estimated_cost) : '—'}</td>
                                                    <td className="px-4 py-3">
                                                        <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[req.status]}`}>{req.status}</span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Request modal */}
            {modal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setModal(false)}>
                    <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">Request Training</h3>
                            <button onClick={() => setModal(false)} className="rounded p-1 text-gray-400 hover:bg-gray-100"><HiOutlineX className="h-5 w-5" /></button>
                        </div>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Training Title *</label>
                                <input type="text" required value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} placeholder="e.g. Confined Space Safety" className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                {errors.title && <p className="mt-1 text-xs text-red-500">{errors.title[0]}</p>}
                            </div>
                            {notice && (
                                <div className="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                                    Apply at least <strong>{notice.min_days} days</strong> before the training date — ideally <strong>3 weeks ({notice.recommended_days} days)</strong> ahead. HRDF needs {notice.hrdf_days} days' notice, so earlier requests leave HR time to apply.
                                </div>
                            )}
                            {notice && form.preferred_date >= notice.earliest_date && form.preferred_date < notice.recommended_date && (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    This is within the minimum notice but under 3 weeks away, so HR will have little time to lodge the HRDF application. Pick a later date if you can.
                                </div>
                            )}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Category</label>
                                    <select value={form.category} onChange={(e) => setForm((p) => ({ ...p, category: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="">—</option>
                                        {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Training Date *</label>
                                    <input type="date" required min={notice?.earliest_date} value={form.preferred_date} onChange={(e) => { setForm((p) => ({ ...p, preferred_date: e.target.value })); setErrors((p) => ({ ...p, preferred_date: undefined })); }} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                    {errors.preferred_date && <p className="mt-1 text-xs text-red-500">{errors.preferred_date[0]}</p>}
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Estimated Cost (RM)</label>
                                <input type="number" min="0" step="0.01" value={form.estimated_cost} onChange={(e) => setForm((p) => ({ ...p, estimated_cost: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Reason / Justification</label>
                                <textarea rows={3} value={form.reason} onChange={(e) => setForm((p) => ({ ...p, reason: e.target.value }))} placeholder="Why do you need this training?" className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setModal(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Submitting...' : 'Submit Request'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
