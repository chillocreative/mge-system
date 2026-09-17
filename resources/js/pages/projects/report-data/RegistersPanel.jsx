import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import reportDataService from '@/services/reportDataService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useConfirm } from '@/context/ConfirmContext';
import { HiOutlinePencil, HiOutlineTrash, HiOutlinePlus, HiOutlineX } from 'react-icons/hi';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

const emptyNoticeForm = () => ({
    id: null,
    title: '',
    issue: '',
    reg_number: '',
    submitted_date: '',
    submitted_via: '',
    reply_date: '',
    status: 'open',
    impact: '',
    sort_order: 0,
});

const emptyTestForm = () => ({
    id: null,
    ref_no: '',
    name: '',
    test_date: '',
    result: '',
    remarks: '',
    sort_order: 0,
});

function DelayNoticeSection({ project, canEdit }) {
    const confirm = useConfirm();
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [notices, setNotices] = useState([]);
    const [form, setForm] = useState(null);
    const [saving, setSaving] = useState(false);

    const load = () => {
        setLoading(true);
        setLoadError(false);
        reportDataService.listDelayNotices(project.id)
            .then((res) => setNotices(res.data || []))
            .catch(() => { setLoadError(true); toast.error('Failed to load delay notices'); })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id]);

    const openAdd = () => setForm(emptyNoticeForm());
    const openEdit = (n) => setForm({
        id: n.id,
        title: n.title || '',
        issue: n.issue || '',
        reg_number: n.reg_number || '',
        submitted_date: n.submitted_date ? n.submitted_date.slice(0, 10) : '',
        submitted_via: n.submitted_via || '',
        reply_date: n.reply_date ? n.reply_date.slice(0, 10) : '',
        status: n.status || 'open',
        impact: n.impact || '',
        sort_order: n.sort_order ?? 0,
    });
    const closeForm = () => setForm(null);
    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

    const save = async (e) => {
        e.preventDefault();
        if (form.reply_date && form.submitted_date && form.reply_date < form.submitted_date) {
            toast.error('Reply date must be on or after submitted date');
            return;
        }
        setSaving(true);
        try {
            const payload = {
                title: form.title,
                issue: form.issue || null,
                reg_number: form.reg_number || null,
                submitted_date: form.submitted_date,
                submitted_via: form.submitted_via || null,
                reply_date: form.reply_date || null,
                status: form.status,
                impact: form.impact || null,
                sort_order: Number(form.sort_order) || 0,
            };
            if (form.id) {
                await reportDataService.updateDelayNotice(project.id, form.id, payload);
                toast.success('Delay notice updated');
            } else {
                await reportDataService.createDelayNotice(project.id, payload);
                toast.success('Delay notice added');
            }
            closeForm();
            load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save delay notice');
        } finally {
            setSaving(false);
        }
    };

    const onDelete = async (n) => {
        if (!(await confirm({ title: 'Delete delay notice?', message: `Delete "${n.title}"?` }))) return;
        try {
            await reportDataService.deleteDelayNotice(project.id, n.id);
            toast.success('Delay notice deleted');
            setNotices((rows) => rows.filter((x) => x.id !== n.id));
        } catch {
            toast.error('Failed to delete delay notice');
        }
    };

    if (loading) return <LoadingSpinner />;
    if (loadError) {
        return (
            <div className="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">
                <p className="mb-3">Failed to load delay notices.</p>
                <button type="button" onClick={load} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Retry</button>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900">Notice of Delay</h2>
                {canEdit && !form && (
                    <button type="button" onClick={openAdd} className="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700">
                        <HiOutlinePlus className="h-4 w-4" /> Add notice
                    </button>
                )}
            </div>

            {form && (
                <form onSubmit={save} className="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-gray-900">{form.id ? 'Edit notice' : 'New notice'}</h3>
                        <button type="button" onClick={closeForm} className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <HiOutlineX className="h-4 w-4" />
                        </button>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <input value={form.title} onChange={set('title')} className={input} required />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Reg. number</label>
                            <input value={form.reg_number} onChange={set('reg_number')} className={input} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Submitted date</label>
                            <input type="date" value={form.submitted_date} onChange={set('submitted_date')} className={input} required />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Submitted via</label>
                            <input value={form.submitted_via} onChange={set('submitted_via')} className={input} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Reply date</label>
                            <input type="date" value={form.reply_date} onChange={set('reply_date')} className={input} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                            <select value={form.status} onChange={set('status')} className={input}>
                                <option value="open">Open</option>
                                <option value="close">Close</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Order</label>
                            <input type="number" value={form.sort_order} onChange={set('sort_order')} className={input} />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Issue</label>
                            <textarea value={form.issue} onChange={set('issue')} className={input} rows={2} />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Impact</label>
                            <textarea value={form.impact} onChange={set('impact')} className={input} rows={2} />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={closeForm} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving…' : 'Save notice'}</button>
                    </div>
                </form>
            )}

            {notices.length === 0 && !form ? (
                <div className="rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">No delay notices yet.</div>
            ) : (
                <div className="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase text-gray-500">
                                <th className="px-4 py-2">No</th>
                                <th className="px-4 py-2">Title</th>
                                <th className="px-4 py-2">Issue</th>
                                <th className="px-4 py-2">Reg. Number</th>
                                <th className="px-4 py-2">Submitted</th>
                                <th className="px-4 py-2">Reply</th>
                                <th className="px-4 py-2">Duration (days)</th>
                                <th className="px-4 py-2">Status</th>
                                <th className="px-4 py-2">Impact</th>
                                {canEdit && <th className="px-4 py-2" />}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {notices.map((n, idx) => (
                                <tr key={n.id}>
                                    <td className="px-4 py-2">{idx + 1}</td>
                                    <td className="px-4 py-2">{n.title}</td>
                                    <td className="px-4 py-2 max-w-xs truncate" title={n.issue}>{n.issue || '-'}</td>
                                    <td className="px-4 py-2">{n.reg_number || '-'}</td>
                                    <td className="px-4 py-2">{n.submitted_date?.slice(0, 10) || '-'}</td>
                                    <td className="px-4 py-2">{n.reply_date?.slice(0, 10) || '-'}</td>
                                    <td className="px-4 py-2">{n.duration_days ?? '-'}</td>
                                    <td className="px-4 py-2">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${n.status === 'close' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-700'}`}>
                                            {n.status === 'close' ? 'Close' : 'Open'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2 max-w-xs truncate" title={n.impact}>{n.impact || '-'}</td>
                                    {canEdit && (
                                        <td className="px-4 py-2">
                                            <div className="flex items-center gap-1">
                                                <button type="button" onClick={() => openEdit(n)} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Edit" aria-label="Edit">
                                                    <HiOutlinePencil className="h-4 w-4" />
                                                </button>
                                                <button type="button" onClick={() => onDelete(n)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete" aria-label="Delete">
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
    );
}

function TestSection({ project, canEdit }) {
    const confirm = useConfirm();
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [tests, setTests] = useState([]);
    const [form, setForm] = useState(null);
    const [saving, setSaving] = useState(false);

    const load = () => {
        setLoading(true);
        setLoadError(false);
        reportDataService.listTests(project.id)
            .then((res) => setTests(res.data || []))
            .catch(() => { setLoadError(true); toast.error('Failed to load tests'); })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id]);

    const openAdd = () => setForm(emptyTestForm());
    const openEdit = (t) => setForm({
        id: t.id,
        ref_no: t.ref_no || '',
        name: t.name || '',
        test_date: t.test_date ? t.test_date.slice(0, 10) : '',
        result: t.result || '',
        remarks: t.remarks || '',
        sort_order: t.sort_order ?? 0,
    });
    const closeForm = () => setForm(null);
    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

    const save = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = {
                ref_no: form.ref_no || null,
                name: form.name,
                test_date: form.test_date || null,
                result: form.result || null,
                remarks: form.remarks || null,
                sort_order: Number(form.sort_order) || 0,
            };
            if (form.id) {
                await reportDataService.updateTest(project.id, form.id, payload);
                toast.success('Test updated');
            } else {
                await reportDataService.createTest(project.id, payload);
                toast.success('Test added');
            }
            closeForm();
            load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save test');
        } finally {
            setSaving(false);
        }
    };

    const onDelete = async (t) => {
        if (!(await confirm({ title: 'Delete test?', message: `Delete "${t.name}"?` }))) return;
        try {
            await reportDataService.deleteTest(project.id, t.id);
            toast.success('Test deleted');
            setTests((rows) => rows.filter((x) => x.id !== t.id));
        } catch {
            toast.error('Failed to delete test');
        }
    };

    if (loading) return <LoadingSpinner />;
    if (loadError) {
        return (
            <div className="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">
                <p className="mb-3">Failed to load tests.</p>
                <button type="button" onClick={load} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Retry</button>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900">Testing &amp; Commissioning</h2>
                {canEdit && !form && (
                    <button type="button" onClick={openAdd} className="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700">
                        <HiOutlinePlus className="h-4 w-4" /> Add test
                    </button>
                )}
            </div>

            {form && (
                <form onSubmit={save} className="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-gray-900">{form.id ? 'Edit test' : 'New test'}</h3>
                        <button type="button" onClick={closeForm} className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <HiOutlineX className="h-4 w-4" />
                        </button>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Ref. No.</label>
                            <input value={form.ref_no} onChange={set('ref_no')} className={input} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Name of test</label>
                            <input value={form.name} onChange={set('name')} className={input} required />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Date</label>
                            <input type="date" value={form.test_date} onChange={set('test_date')} className={input} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Result</label>
                            <input value={form.result} onChange={set('result')} className={input} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Order</label>
                            <input type="number" value={form.sort_order} onChange={set('sort_order')} className={input} />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Remarks</label>
                            <textarea value={form.remarks} onChange={set('remarks')} className={input} rows={2} />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={closeForm} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving…' : 'Save test'}</button>
                    </div>
                </form>
            )}

            {tests.length === 0 && !form ? (
                <div className="rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">No tests recorded yet.</div>
            ) : (
                <div className="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase text-gray-500">
                                <th className="px-4 py-2">Ref. No</th>
                                <th className="px-4 py-2">Name of Test</th>
                                <th className="px-4 py-2">Date</th>
                                <th className="px-4 py-2">Result</th>
                                <th className="px-4 py-2">Remarks</th>
                                {canEdit && <th className="px-4 py-2" />}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {tests.map((t) => (
                                <tr key={t.id}>
                                    <td className="px-4 py-2">{t.ref_no || '-'}</td>
                                    <td className="px-4 py-2">{t.name}</td>
                                    <td className="px-4 py-2">{t.test_date?.slice(0, 10) || '-'}</td>
                                    <td className="px-4 py-2">{t.result || '-'}</td>
                                    <td className="px-4 py-2 max-w-xs truncate" title={t.remarks}>{t.remarks || '-'}</td>
                                    {canEdit && (
                                        <td className="px-4 py-2">
                                            <div className="flex items-center gap-1">
                                                <button type="button" onClick={() => openEdit(t)} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Edit" aria-label="Edit">
                                                    <HiOutlinePencil className="h-4 w-4" />
                                                </button>
                                                <button type="button" onClick={() => onDelete(t)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete" aria-label="Delete">
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
    );
}

export default function RegistersPanel({ project, canEdit }) {
    return (
        <div className="space-y-6">
            <DelayNoticeSection project={project} canEdit={canEdit} />
            <TestSection project={project} canEdit={canEdit} />
        </div>
    );
}
