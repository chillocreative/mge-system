import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import reportDataService from '@/services/reportDataService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useConfirm } from '@/context/ConfirmContext';
import { HiOutlinePencil, HiOutlineTrash, HiOutlinePlus, HiOutlineX } from 'react-icons/hi';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

const emptyBaselineRow = (month) => ({
    month,
    scheduled_physical_pct: '',
    scheduled_financial_amount: '',
    scheduled_financial_pct: '',
});

const nextMonth = (ym) => {
    const [y, m] = ym.split('-').map(Number);
    const d = new Date(y, m, 1); // m is 1-based month index -> next month
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

const emptyPeriodForm = () => ({
    id: null,
    period_no: '',
    period_start: '',
    period_end: '',
    planning_days_completion: '',
    physical_scheduled_pct: '',
    physical_actual_pct: '',
    financial_scheduled_pct: '',
    financial_actual_pct: '',
    financial_actual_amount: '',
    ahead_delay_days: '',
    physical_status: '',
    financial_status: '',
    notes: '',
});

const num = (v) => (v === '' || v === null || v === undefined ? null : Number(v));

export default function ProgressPanel({ project, canEdit }) {
    const confirm = useConfirm();
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [baseline, setBaseline] = useState([]);
    const [periods, setPeriods] = useState([]);
    const [savingBaseline, setSavingBaseline] = useState(false);
    const [form, setForm] = useState(null);
    const [touched, setTouched] = useState({ ahead_delay_days: false, physical_status: false });
    const [saving, setSaving] = useState(false);
    const [suggesting, setSuggesting] = useState(false);

    const load = () => {
        setLoading(true);
        setLoadError(false);
        Promise.all([
            reportDataService.getBaseline(project.id),
            reportDataService.listPeriods(project.id),
        ])
            .then(([baselineRes, periodsRes]) => {
                const rows = (baselineRes.data || []).map((r) => ({
                    month: r.month ? r.month.slice(0, 7) : '',
                    scheduled_physical_pct: r.scheduled_physical_pct ?? '',
                    scheduled_financial_amount: r.scheduled_financial_amount ?? '',
                    scheduled_financial_pct: r.scheduled_financial_pct ?? '',
                }));
                setBaseline(rows);
                setPeriods(periodsRes.data || []);
            })
            .catch(() => { setLoadError(true); toast.error('Failed to load progress data'); })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id]);

    // --- Baseline grid ---
    const setBaselineCell = (i, k) => (e) => setBaseline((rows) => rows.map((r, idx) => (idx === i ? { ...r, [k]: e.target.value } : r)));

    const addMonth = () => {
        setBaseline((rows) => {
            const last = rows[rows.length - 1];
            const month = last?.month ? nextMonth(last.month) : new Date().toISOString().slice(0, 7);
            return [...rows, emptyBaselineRow(month)];
        });
    };

    const removeBaselineRow = (i) => setBaseline((rows) => rows.filter((_, idx) => idx !== i));

    const generateMonths = () => {
        const start = window.prompt('Start month (YYYY-MM)');
        if (!start) return;
        const end = window.prompt('End month (YYYY-MM)');
        if (!end) return;
        if (!/^\d{4}-\d{2}$/.test(start) || !/^\d{4}-\d{2}$/.test(end)) {
            toast.error('Enter months as YYYY-MM');
            return;
        }
        if (start > end) {
            toast.error('Start month must be before end month');
            return;
        }
        const months = [];
        let cur = start;
        let guard = 0;
        while (cur <= end && guard < 240) {
            months.push(cur);
            cur = nextMonth(cur);
            guard += 1;
        }
        setBaseline((rows) => {
            const existing = new Map(rows.map((r) => [r.month, r]));
            months.forEach((m) => {
                if (!existing.has(m)) existing.set(m, emptyBaselineRow(m));
            });
            return Array.from(existing.values()).sort((a, b) => a.month.localeCompare(b.month));
        });
    };

    const saveBaseline = async () => {
        const months = baseline.map((r) => r.month).filter(Boolean);
        const dupes = months.filter((m, idx) => months.indexOf(m) !== idx);
        if (dupes.length > 0) {
            toast.error(`Duplicate month(s): ${[...new Set(dupes)].join(', ')}`);
            return;
        }
        setSavingBaseline(true);
        try {
            const rows = baseline.filter((r) => r.month).map((r) => ({
                month: r.month,
                scheduled_physical_pct: num(r.scheduled_physical_pct),
                scheduled_financial_amount: num(r.scheduled_financial_amount),
                scheduled_financial_pct: num(r.scheduled_financial_pct),
            }));
            await reportDataService.replaceBaseline(project.id, rows);
            toast.success('Baseline saved');
            load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save baseline');
        } finally {
            setSavingBaseline(false);
        }
    };

    // --- Period form ---
    const openAdd = () => {
        setTouched({ ahead_delay_days: false, physical_status: false });
        setForm(emptyPeriodForm());
    };
    const openEdit = (period) => {
        setTouched({ ahead_delay_days: false, physical_status: false });
        setForm({
            id: period.id,
            period_no: period.period_no ?? '',
            period_start: period.period_start ? period.period_start.slice(0, 10) : '',
            period_end: period.period_end ? period.period_end.slice(0, 10) : '',
            planning_days_completion: period.planning_days_completion ?? '',
            physical_scheduled_pct: period.physical_scheduled_pct ?? '',
            physical_actual_pct: period.physical_actual_pct ?? '',
            financial_scheduled_pct: period.financial_scheduled_pct ?? '',
            financial_actual_pct: period.financial_actual_pct ?? '',
            financial_actual_amount: period.financial_actual_amount ?? '',
            ahead_delay_days: period.ahead_delay_days ?? '',
            physical_status: period.physical_status ?? '',
            financial_status: period.financial_status ?? '',
            notes: period.notes ?? '',
        });
    };
    const closeForm = () => setForm(null);

    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));
    const setOverride = (k) => (e) => {
        setTouched((t) => ({ ...t, [k]: true }));
        setForm((f) => ({ ...f, [k]: e.target.value }));
    };

    const onPeriodEndChange = async (e) => {
        const value = e.target.value;
        setForm((f) => ({ ...f, period_end: value }));
        if (!value || form.id) return;
        setSuggesting(true);
        try {
            const res = await reportDataService.suggestPeriod(project.id, value);
            const s = res.data;
            setForm((f) => ({
                ...f,
                period_no: s.period_no ?? f.period_no,
                period_start: s.period_start ?? f.period_start,
                period_end: s.period_end ?? f.period_end,
                physical_scheduled_pct: s.physical_scheduled_pct ?? '',
                financial_scheduled_pct: s.financial_scheduled_pct ?? '',
                financial_actual_amount: s.financial_actual_amount ?? '',
                financial_actual_pct: s.financial_actual_pct ?? '',
                planning_days_completion: s.planning_days_completion ?? '',
            }));
        } catch {
            toast.error('Failed to suggest period values');
        } finally {
            setSuggesting(false);
        }
    };

    const save = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = {
                period_no: num(form.period_no),
                period_start: form.period_start,
                period_end: form.period_end,
                planning_days_completion: num(form.planning_days_completion),
                physical_scheduled_pct: num(form.physical_scheduled_pct),
                physical_actual_pct: num(form.physical_actual_pct),
                financial_scheduled_pct: num(form.financial_scheduled_pct),
                financial_actual_pct: num(form.financial_actual_pct),
                financial_actual_amount: num(form.financial_actual_amount),
                ahead_delay_days: touched.ahead_delay_days ? num(form.ahead_delay_days) : null,
                physical_status: touched.physical_status ? (form.physical_status || null) : null,
                financial_status: form.financial_status || null,
                notes: form.notes || null,
            };
            if (form.id) {
                await reportDataService.updatePeriod(project.id, form.id, payload);
                toast.success('Progress period updated');
            } else {
                await reportDataService.createPeriod(project.id, payload);
                toast.success('Progress period saved');
            }
            closeForm();
            load();
        } catch (err) {
            const msg = err.response?.data?.errors?.period_no?.[0] || err.response?.data?.message || 'Failed to save period';
            toast.error(msg);
        } finally {
            setSaving(false);
        }
    };

    const onDelete = async (period) => {
        if (!(await confirm({ title: 'Delete period?', message: `Delete period #${period.period_no}?` }))) return;
        try {
            await reportDataService.deletePeriod(project.id, period.id);
            toast.success('Progress period deleted');
            setPeriods((rows) => rows.filter((x) => x.id !== period.id));
        } catch {
            toast.error('Failed to delete period');
        }
    };

    if (loading) return <LoadingSpinner />;
    if (loadError) {
        return (
            <div className="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">
                <p className="mb-3">Failed to load progress data.</p>
                <button type="button" onClick={load} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Retry</button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-gray-900">Schedule baseline (from CPM)</h2>
                    {canEdit && (
                        <div className="flex items-center gap-2">
                            <button type="button" onClick={generateMonths} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Generate months</button>
                            <button type="button" onClick={addMonth} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Add month</button>
                        </div>
                    )}
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase text-gray-500">
                                <th className="py-2 pr-3">Month</th>
                                <th className="py-2 pr-3">Scheduled physical %</th>
                                <th className="py-2 pr-3">Scheduled financial (RM)</th>
                                <th className="py-2 pr-3">Scheduled financial %</th>
                                {canEdit && <th className="py-2 pr-3" />}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {baseline.map((row, i) => (
                                <tr key={i}>
                                    <td className="py-2 pr-3"><input type="month" value={row.month} onChange={setBaselineCell(i, 'month')} disabled={!canEdit} className={input} /></td>
                                    <td className="py-2 pr-3"><input type="number" step="0.01" value={row.scheduled_physical_pct} onChange={setBaselineCell(i, 'scheduled_physical_pct')} disabled={!canEdit} className={input} /></td>
                                    <td className="py-2 pr-3"><input type="number" step="0.01" value={row.scheduled_financial_amount} onChange={setBaselineCell(i, 'scheduled_financial_amount')} disabled={!canEdit} className={input} /></td>
                                    <td className="py-2 pr-3"><input type="number" step="0.01" value={row.scheduled_financial_pct} onChange={setBaselineCell(i, 'scheduled_financial_pct')} disabled={!canEdit} className={input} /></td>
                                    {canEdit && (
                                        <td className="py-2 pr-3">
                                            <button type="button" onClick={() => removeBaselineRow(i)} aria-label="Remove month" title="Remove month" className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                                <HiOutlineTrash className="h-4 w-4" />
                                            </button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {baseline.length === 0 && (
                                <tr><td colSpan={5} className="py-4 text-center text-sm text-gray-500">No baseline months yet.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {canEdit && (
                    <div className="mt-4 flex justify-end">
                        <button type="button" onClick={saveBaseline} disabled={savingBaseline} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                            {savingBaseline ? 'Saving…' : 'Save baseline'}
                        </button>
                    </div>
                )}
            </div>

            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-gray-900">Progress periods</h2>
                    {canEdit && !form && (
                        <button type="button" onClick={openAdd} className="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700">
                            <HiOutlinePlus className="h-4 w-4" /> New period
                        </button>
                    )}
                </div>

                {form && (
                    <form onSubmit={save} className="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div className="flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-gray-900">{form.id ? 'Edit period' : 'New period'}</h3>
                            <button type="button" onClick={closeForm} className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                <HiOutlineX className="h-4 w-4" />
                            </button>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Period end</label>
                                <input type="date" value={form.period_end} onChange={onPeriodEndChange} className={input} required />
                                {suggesting && <p className="mt-1 text-xs text-gray-500">Suggesting…</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Period start</label>
                                <input type="date" value={form.period_start} onChange={set('period_start')} className={input} required />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Period no.</label>
                                <input type="number" value={form.period_no} onChange={set('period_no')} className={input} required />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Planning days completion</label>
                                <input type="number" value={form.planning_days_completion} onChange={set('planning_days_completion')} className={input} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Physical scheduled %</label>
                                <input type="number" step="0.01" value={form.physical_scheduled_pct} onChange={set('physical_scheduled_pct')} className={input} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Physical actual %</label>
                                <input type="number" step="0.01" value={form.physical_actual_pct} onChange={set('physical_actual_pct')} className={input} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Financial scheduled %</label>
                                <input type="number" step="0.01" value={form.financial_scheduled_pct} onChange={set('financial_scheduled_pct')} className={input} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Financial actual %</label>
                                <input type="number" step="0.01" value={form.financial_actual_pct} onChange={set('financial_actual_pct')} className={input} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Financial actual amount (RM)</label>
                                <input type="number" step="0.01" value={form.financial_actual_amount} onChange={set('financial_actual_amount')} className={input} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Ahead/Delay days</label>
                                <input type="number" value={form.ahead_delay_days} onChange={setOverride('ahead_delay_days')} placeholder="blank = auto (computed value shown)" className={input} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Physical status</label>
                                <select value={form.physical_status} onChange={setOverride('physical_status')} className={input}>
                                    <option value="">Auto</option>
                                    <option value="ON TRACK">ON TRACK</option>
                                    <option value="AHEAD">AHEAD</option>
                                    <option value="DELAY">DELAY</option>
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Financial status</label>
                                <input value={form.financial_status} onChange={set('financial_status')} className={input} />
                            </div>
                            <div className="sm:col-span-3">
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <textarea value={form.notes} onChange={set('notes')} className={input} rows={2} />
                            </div>
                        </div>
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={closeForm} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving…' : 'Save period'}</button>
                        </div>
                    </form>
                )}

                {periods.length === 0 && !form ? (
                    <div className="rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">No progress periods yet.</div>
                ) : (
                    <div className="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr className="text-left text-xs font-semibold uppercase text-gray-500">
                                    <th className="px-4 py-2">No.</th>
                                    <th className="px-4 py-2">Period</th>
                                    <th className="px-4 py-2">Physical sched/actual/var</th>
                                    <th className="px-4 py-2">Financial sched/actual/var</th>
                                    <th className="px-4 py-2">Ahead/Delay</th>
                                    <th className="px-4 py-2">Status</th>
                                    {canEdit && <th className="px-4 py-2" />}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {periods.map((period) => (
                                    <tr key={period.id}>
                                        <td className="px-4 py-2">{period.period_no}</td>
                                        <td className="px-4 py-2">{period.period_start?.slice(0, 10)} – {period.period_end?.slice(0, 10)}</td>
                                        <td className="px-4 py-2">{period.physical_scheduled_pct ?? '-'} / {period.physical_actual_pct ?? '-'} / {period.physical_variance ?? '-'}</td>
                                        <td className="px-4 py-2">{period.financial_scheduled_pct ?? '-'} / {period.financial_actual_pct ?? '-'} / {period.financial_variance ?? '-'}</td>
                                        <td className="px-4 py-2">{period.ahead_delay_days ?? '-'}</td>
                                        <td className="px-4 py-2">{period.physical_status ?? '-'}</td>
                                        {canEdit && (
                                            <td className="px-4 py-2">
                                                <div className="flex items-center gap-1">
                                                    <button type="button" onClick={() => openEdit(period)} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Edit" aria-label="Edit">
                                                        <HiOutlinePencil className="h-4 w-4" />
                                                    </button>
                                                    <button type="button" onClick={() => onDelete(period)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete" aria-label="Delete">
                                                        <HiOutlineTrash className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}
