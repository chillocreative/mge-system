import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import reportDataService from '@/services/reportDataService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { HiOutlineTrash, HiOutlinePlus } from 'react-icons/hi';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

const KINDS = [
    ['worker', 'Workers'],
    ['machinery', 'Machinery'],
];

const WORKER_GROUPS = ['Management Team', 'Tradesman'];

const emptyRow = () => ({ group: '', name: '', sort_order: 0, active: true });

const errorMessage = (err, fallback) => {
    const errors = err.response?.data?.errors;
    if (errors) {
        const first = Object.values(errors)[0];
        if (Array.isArray(first) && first[0]) return first[0];
    }
    return err.response?.data?.message || fallback;
};

export default function ResourceCategoriesPanel({ project, canEdit }) {
    const [kind, setKind] = useState('worker');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [rows, setRows] = useState([]);
    const [seeding, setSeeding] = useState(false);
    const [saving, setSaving] = useState(false);

    const load = () => {
        setLoading(true);
        setLoadError(false);
        reportDataService.getCategories(project.id, kind)
            .then((res) => {
                setRows((res.data?.rows || []).map((r) => ({
                    group: r.group || '',
                    name: r.name || '',
                    sort_order: r.sort_order ?? 0,
                    active: r.active !== false,
                })));
            })
            .catch(() => { setLoadError(true); toast.error('Failed to load categories'); })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id, kind]);

    const setCell = (i, k) => (e) => {
        const value = k === 'active' ? e.target.checked : e.target.value;
        setRows((rs) => rs.map((r, idx) => (idx === i ? { ...r, [k]: value } : r)));
    };

    const addRow = () => setRows((rs) => [...rs, emptyRow()]);
    const removeRow = (i) => setRows((rs) => rs.filter((_, idx) => idx !== i));

    const useDefaults = async () => {
        setSeeding(true);
        try {
            await reportDataService.seedCategories(project.id, kind);
            toast.success('Default categories added');
            load();
        } catch (err) {
            toast.error(errorMessage(err, 'Failed to seed defaults'));
        } finally {
            setSeeding(false);
        }
    };

    const save = async () => {
        const names = rows.map((r) => r.name.trim()).filter(Boolean);
        const namesLower = names.map((n) => n.toLowerCase());
        const dupes = names.filter((n, idx) => namesLower.indexOf(n.toLowerCase()) !== idx);
        if (dupes.length > 0) {
            toast.error(`Duplicate name(s): ${[...new Set(dupes)].join(', ')}`);
            return;
        }
        if (rows.some((r) => !r.name.trim())) {
            toast.error('Every row needs a name');
            return;
        }
        setSaving(true);
        try {
            const payload = rows.map((r) => ({
                group: kind === 'worker' ? (r.group || null) : null,
                name: r.name.trim(),
                sort_order: Number(r.sort_order) || 0,
                active: !!r.active,
            }));
            await reportDataService.replaceCategories(project.id, kind, payload);
            toast.success('Category list saved');
            load();
        } catch (err) {
            toast.error(errorMessage(err, 'Failed to save category list'));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-4">
            <div>
                <h2 className="text-lg font-semibold text-gray-900">Site-Log Categories</h2>
                <p className="mt-1 text-sm text-gray-500">These names are what the Site Log form offers and what the monthly report tabulates.</p>
            </div>

            <div className="inline-flex rounded-lg border border-gray-300 bg-white p-1">
                {KINDS.map(([v, l]) => (
                    <button
                        key={v}
                        type="button"
                        onClick={() => setKind(v)}
                        className={`rounded-md px-3 py-1.5 text-sm font-medium ${kind === v ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50'}`}
                    >
                        {l}
                    </button>
                ))}
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : loadError ? (
                <div className="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">
                    <p className="mb-3">Failed to load categories.</p>
                    <button type="button" onClick={load} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Retry</button>
                </div>
            ) : (
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    {rows.length === 0 && canEdit && (
                        <div className="mb-4 flex items-center justify-between rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                            <span>Using default list.</span>
                            <button type="button" onClick={useDefaults} disabled={seeding} className="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-sm font-medium text-amber-800 hover:bg-amber-100 disabled:opacity-50">
                                {seeding ? 'Loading…' : 'Use defaults as starting point'}
                            </button>
                        </div>
                    )}

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr className="text-left text-xs font-semibold uppercase text-gray-500">
                                    <th className="py-2 pr-3">Group</th>
                                    <th className="py-2 pr-3">Name</th>
                                    <th className="py-2 pr-3">Order</th>
                                    <th className="py-2 pr-3">Active</th>
                                    {canEdit && <th className="py-2 pr-3" />}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {rows.map((row, i) => (
                                    <tr key={i}>
                                        <td className="py-2 pr-3">
                                            {kind === 'worker' ? (
                                                <select value={row.group} onChange={setCell(i, 'group')} disabled={!canEdit} className={input}>
                                                    <option value="">-</option>
                                                    {WORKER_GROUPS.map((g) => <option key={g} value={g}>{g}</option>)}
                                                </select>
                                            ) : (
                                                <span className="text-xs text-gray-400">-</span>
                                            )}
                                        </td>
                                        <td className="py-2 pr-3"><input value={row.name} onChange={setCell(i, 'name')} disabled={!canEdit} className={input} /></td>
                                        <td className="py-2 pr-3"><input type="number" value={row.sort_order} onChange={setCell(i, 'sort_order')} disabled={!canEdit} className={input} /></td>
                                        <td className="py-2 pr-3">
                                            <input type="checkbox" checked={row.active} onChange={setCell(i, 'active')} disabled={!canEdit} className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                        </td>
                                        {canEdit && (
                                            <td className="py-2 pr-3">
                                                <button type="button" onClick={() => removeRow(i)} aria-label="Remove row" title="Remove row" className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                                    <HiOutlineTrash className="h-4 w-4" />
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {rows.length === 0 && (
                                    <tr><td colSpan={5} className="py-4 text-center text-sm text-gray-500">No categories yet.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {canEdit && (
                        <div className="mt-4 flex items-center justify-between">
                            <button type="button" onClick={addRow} className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <HiOutlinePlus className="h-4 w-4" /> Add row
                            </button>
                            <button type="button" onClick={save} disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                {saving ? 'Saving…' : 'Save list'}
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
