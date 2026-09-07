import { useState, useEffect, useCallback } from 'react';
import leaveService from '@/services/leaveService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlinePlus, HiOutlinePencil, HiOutlineTrash, HiOutlineCalendar, HiOutlineExclamation } from 'react-icons/hi';

/**
 * Public Holidays admin.
 *
 * The one screen the leave module could not go live without and did not have:
 * the calculation engine excludes public holidays from a leave deduction, but
 * only the ones it knows about. The seeder ships fixed-date holidays and refuses
 * to guess the lunar and gazetted ones (Deepavali, Hari Raya, Maulidur Rasul,
 * Penang state days), because a wrong date silently mis-deducts leave for
 * everyone whose leave spans it. This is where HR enters the real dates.
 */

const emptyForm = { name: '', date: '', scope: 'national', state: 'Penang', notes: '' };

const currentYear = new Date().getFullYear();
const YEARS = Array.from({ length: 4 }, (_, i) => currentYear - 1 + i);

// The API serializes `date` as a full ISO datetime (e.g. 2026-01-01T00:00:00Z);
// take the date part only so parsing is stable regardless of timezone.
function asDate(iso) {
    return new Date(`${String(iso).slice(0, 10)}T00:00:00`);
}

function weekday(iso) {
    return asDate(iso).toLocaleDateString('en-GB', { weekday: 'short' });
}

function formatDate(iso) {
    return asDate(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function PublicHolidays() {
    const [year, setYear] = useState(currentYear);
    const [holidays, setHolidays] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editId, setEditId] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    const fetchHolidays = useCallback(async () => {
        setLoading(true);
        try {
            const res = await leaveService.holidays({ year });
            // Sort by date so the calendar reads top to bottom.
            const rows = (res.data || []).slice().sort((a, b) => a.date.localeCompare(b.date));
            setHolidays(rows);
        } catch {
            setHolidays([]);
        } finally {
            setLoading(false);
        }
    }, [year]);

    useEffect(() => { fetchHolidays(); }, [fetchHolidays]);

    const openCreate = () => {
        // Default the new holiday's date into the year currently being viewed,
        // so adding to 2026 does not silently create a 2025 entry.
        setEditId(null);
        setForm({ ...emptyForm, date: `${year}-01-01` });
        setErrors({});
        setShowForm(true);
    };

    const openEdit = (h) => {
        setEditId(h.id);
        setForm({
            name: h.name || '',
            date: h.date ? h.date.slice(0, 10) : '',
            scope: h.scope || 'national',
            state: h.state || 'Penang',
            notes: h.notes || '',
        });
        setErrors({});
        setShowForm(true);
    };

    const submit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        const payload = {
            name: form.name,
            date: form.date,
            scope: form.scope,
            state: form.scope === 'state' ? form.state : null,
            notes: form.notes || null,
        };

        try {
            if (editId) {
                await leaveService.updateHoliday(editId, payload);
                toast.success('Holiday updated');
            } else {
                await leaveService.createHoliday(payload);
                toast.success('Holiday added');
            }
            setShowForm(false);
            // The saved date may land in a different year than the one on screen;
            // jump the view to it so the new row is actually visible.
            const savedYear = Number(form.date.slice(0, 4));
            if (savedYear !== year) setYear(savedYear);
            else fetchHolidays();
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors || {});
            } else {
                toast.error(err.response?.data?.message || 'Failed to save holiday');
            }
        } finally {
            setSaving(false);
        }
    };

    const deactivate = async (h) => {
        if (!window.confirm(`Deactivate "${h.name}"? It will no longer be excluded from leave, but existing leave already calculated is not changed.`)) {
            return;
        }
        try {
            await leaveService.deactivateHoliday(h.id);
            toast.success('Holiday deactivated');
            fetchHolidays();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to deactivate');
        }
    };

    const fieldClass = (name) =>
        `w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1 ${
            errors[name] ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500'
        }`;

    const activeCount = holidays.filter((h) => h.is_active).length;

    return (
        <div>
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Public Holidays</h1>
                    <p className="text-sm text-gray-500">
                        Days excluded from leave deductions. Add the gazetted dates for each year.
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <select
                        value={year}
                        onChange={(e) => setYear(Number(e.target.value))}
                        className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    >
                        {YEARS.map((y) => <option key={y} value={y}>{y}</option>)}
                    </select>
                    <button
                        onClick={openCreate}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-4 w-4" /> Add Holiday
                    </button>
                </div>
            </div>

            {/* The reason this screen matters, stated where HR will see it. */}
            <div className="mb-6 flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                <HiOutlineExclamation className="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    A leave request that spans a public holiday does not deduct that day. If a holiday is
                    missing here, staff lose a day of leave for it — so enter every gazetted date for {year},
                    including the ones whose dates change each year (Hari Raya, Deepavali, Maulidur Rasul, and Penang state days).
                </span>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : (
                <div className="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center justify-between border-b border-gray-100 px-6 py-3">
                        <h2 className="text-sm font-semibold text-gray-700">
                            {year} — {activeCount} active holiday{activeCount === 1 ? '' : 's'}
                        </h2>
                    </div>

                    {holidays.length === 0 ? (
                        <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
                            <HiOutlineCalendar className="mb-3 h-10 w-10 text-gray-300" />
                            <p className="text-sm text-gray-500">No holidays recorded for {year}.</p>
                            <button onClick={openCreate} className="mt-3 text-sm font-medium text-primary-600 hover:text-primary-700">
                                Add the first one
                            </button>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                                        <th className="px-6 py-3 text-left font-medium">Date</th>
                                        <th className="px-6 py-3 text-left font-medium">Holiday</th>
                                        <th className="px-6 py-3 text-left font-medium">Scope</th>
                                        <th className="px-6 py-3 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {holidays.map((h) => (
                                        <tr
                                            key={h.id}
                                            className={`border-b border-gray-100 last:border-0 ${h.is_active ? '' : 'bg-gray-50 text-gray-400'}`}
                                        >
                                            <td className="whitespace-nowrap px-6 py-3">
                                                <span className="font-medium text-gray-900">{formatDate(h.date)}</span>
                                                <span className="ml-2 text-xs text-gray-400">{weekday(h.date)}</span>
                                            </td>
                                            <td className="px-6 py-3">
                                                {h.name}
                                                {!h.is_active && <span className="ml-2 text-xs italic">(inactive)</span>}
                                            </td>
                                            <td className="px-6 py-3">
                                                {h.scope === 'state' ? (
                                                    <span className="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                        {h.state || 'State'}
                                                    </span>
                                                ) : (
                                                    <span className="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                                        National
                                                    </span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-3 text-right">
                                                <button
                                                    onClick={() => openEdit(h)}
                                                    className="mr-1 rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                    title="Edit"
                                                >
                                                    <HiOutlinePencil className="h-4 w-4" />
                                                </button>
                                                {h.is_active && (
                                                    <button
                                                        onClick={() => deactivate(h)}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                        title="Deactivate"
                                                    >
                                                        <HiOutlineTrash className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}

            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowForm(false)}>
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">{editId ? 'Edit Holiday' : 'Add Holiday'}</h3>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Name *</label>
                                <input
                                    type="text"
                                    value={form.name}
                                    onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                                    required
                                    placeholder="e.g. Deepavali"
                                    className={fieldClass('name')}
                                />
                                {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Date *</label>
                                <input
                                    type="date"
                                    value={form.date}
                                    onChange={(e) => setForm((p) => ({ ...p, date: e.target.value }))}
                                    required
                                    className={fieldClass('date')}
                                />
                                {errors.date && <p className="mt-1 text-xs text-red-500">{errors.date[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Scope</label>
                                <select
                                    value={form.scope}
                                    onChange={(e) => setForm((p) => ({ ...p, scope: e.target.value }))}
                                    className={fieldClass('scope')}
                                >
                                    <option value="national">National</option>
                                    <option value="state">State only</option>
                                </select>
                            </div>
                            {form.scope === 'state' && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">State</label>
                                    <input
                                        type="text"
                                        value={form.state}
                                        onChange={(e) => setForm((p) => ({ ...p, state: e.target.value }))}
                                        placeholder="Penang"
                                        className={fieldClass('state')}
                                    />
                                    <p className="mt-1 text-xs text-gray-400">
                                        Only staff in this state get the day off. MGE operates in Penang.
                                    </p>
                                </div>
                            )}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <input
                                    type="text"
                                    value={form.notes}
                                    onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))}
                                    className={fieldClass('notes')}
                                />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowForm(false)}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={saving}
                                    className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                                >
                                    {saving ? 'Saving…' : editId ? 'Update' : 'Add'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
