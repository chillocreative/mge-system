import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import reportDataService from '@/services/reportDataService';
import LoadingSpinner from '@/components/LoadingSpinner';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

const flatten = (nodes, map = {}) => {
    for (const n of nodes || []) {
        map[n.user_id] = n;
        flatten(n.children, map);
    }
    return map;
};

function TreeNode({ node }) {
    return (
        <li>
            <span className="text-sm text-gray-800">{node.name}{node.designation ? ` — ${node.designation}` : ''}</span>
            {node.children?.length > 0 && (
                <ul className="ml-5 mt-1 list-disc space-y-1 border-l border-gray-200 pl-4">
                    {node.children.map((c) => <TreeNode key={c.user_id} node={c} />)}
                </ul>
            )}
        </li>
    );
}

export default function OrgChartPanel({ project, canEdit }) {
    const [loading, setLoading] = useState(true);
    const [tree, setTree] = useState([]);
    const [rows, setRows] = useState([]);
    const [saving, setSaving] = useState(false);

    const activeMembers = (project.members || []).filter((m) => !m.pivot?.left_at);

    const load = () => {
        setLoading(true);
        reportDataService.getOrgChart(project.id)
            .then((res) => {
                const data = res.data || [];
                setTree(data);
                const map = flatten(data);
                setRows(activeMembers.map((m) => {
                    const node = map[m.id];
                    return {
                        user_id: m.id,
                        name: m.full_name,
                        designation: node?.designation ?? m.pivot?.designation ?? '',
                        reports_to_user_id: node ? node.reports_to_user_id : (m.pivot?.reports_to_user_id ?? null),
                        org_sort: node ? node.org_sort : (m.pivot?.org_sort ?? 0),
                    };
                }));
            })
            .catch(() => toast.error('Failed to load org chart'))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id]);

    const setRow = (userId, k) => (e) => {
        const value = k === 'reports_to_user_id' ? (e.target.value === '' ? null : Number(e.target.value)) : (k === 'org_sort' ? Number(e.target.value) : e.target.value);
        setRows((rs) => rs.map((r) => (r.user_id === userId ? { ...r, [k]: value } : r)));
    };

    const save = async () => {
        setSaving(true);
        try {
            const payload = rows.map(({ user_id, designation, reports_to_user_id, org_sort }) => ({ user_id, designation, reports_to_user_id, org_sort }));
            const res = await reportDataService.updateOrgChart(project.id, payload);
            setTree(res.data || []);
            toast.success('Organisation chart saved');
        } catch (err) {
            toast.error(err.response?.data?.errors?.members?.[0] || err.response?.data?.message || 'Failed to save org chart');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;

    if (activeMembers.length === 0) {
        return (
            <div className="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">
                Add members on the Overview tab first.
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h2 className="mb-4 text-lg font-semibold text-gray-900">Organisation Chart</h2>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 text-xs font-semibold uppercase text-gray-500">
                                <th className="py-2 pr-3">Name</th>
                                <th className="py-2 pr-3">Designation</th>
                                <th className="py-2 pr-3">Reports to</th>
                                <th className="py-2 pr-3">Order</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {rows.map((r) => (
                                <tr key={r.user_id}>
                                    <td className="py-2 pr-3 font-medium text-gray-900">{r.name}</td>
                                    <td className="py-2 pr-3">
                                        <input value={r.designation} onChange={setRow(r.user_id, 'designation')} disabled={!canEdit} className={input} />
                                    </td>
                                    <td className="py-2 pr-3">
                                        <select value={r.reports_to_user_id ?? ''} onChange={setRow(r.user_id, 'reports_to_user_id')} disabled={!canEdit} className={input}>
                                            <option value="">— Top level —</option>
                                            {rows.filter((o) => o.user_id !== r.user_id).map((o) => (
                                                <option key={o.user_id} value={o.user_id}>{o.name}</option>
                                            ))}
                                        </select>
                                    </td>
                                    <td className="py-2 pr-3">
                                        <input type="number" value={r.org_sort} onChange={setRow(r.user_id, 'org_sort')} disabled={!canEdit} className={input} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {canEdit && (
                    <div className="mt-4 flex justify-end">
                        <button type="button" onClick={save} disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                            {saving ? 'Saving…' : 'Save org chart'}
                        </button>
                    </div>
                )}
            </div>

            <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h3 className="mb-3 text-sm font-semibold text-gray-900">Preview</h3>
                {tree.length === 0 ? (
                    <p className="text-sm text-gray-400">No hierarchy set yet.</p>
                ) : (
                    <ul className="list-disc space-y-1 pl-4">
                        {tree.map((n) => <TreeNode key={n.user_id} node={n} />)}
                    </ul>
                )}
            </div>
        </div>
    );
}
