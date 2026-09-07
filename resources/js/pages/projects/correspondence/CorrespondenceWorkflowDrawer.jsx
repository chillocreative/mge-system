import { useState, useEffect, useCallback } from 'react';
import correspondenceService from '@/services/correspondenceService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineX, HiOutlineDownload, HiOutlineArrowRight, HiOutlinePlus } from 'react-icons/hi';

/**
 * Workflow drawer for one correspondence (Batch 7): current party, the
 * append-only history, and the guarded actions — hand over, note, close,
 * reopen, and a PDF export. The close rule (reference + attachment) is enforced
 * by the server; this only surfaces the error it returns.
 */

const EVENT_LABEL = {
    raised: 'Raised', handed_over: 'Handed over', noted: 'Note',
    status_changed: 'Status changed', closed: 'Closed', reopened: 'Reopened',
};

const PARTY_TYPES = ['client', 'consultant', 'main_contractor', 'subcontractor', 'supplier', 'authority', 'other'];

export default function CorrespondenceWorkflowDrawer({ correspondence, canEdit, onClose, onChanged }) {
    const id = correspondence.id;
    const projectId = correspondence.project_id;
    const [detail, setDetail] = useState(null);
    const [parties, setParties] = useState([]);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [note, setNote] = useState('');
    const [handoverTo, setHandoverTo] = useState('');
    const [closingRef, setClosingRef] = useState('');
    const [showClose, setShowClose] = useState(false);
    const [newParty, setNewParty] = useState(null); // {name,type} when adding

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const [d, p] = await Promise.all([
                correspondenceService.get(id),
                projectId ? correspondenceService.listParties(projectId) : Promise.resolve({ data: [] }),
            ]);
            setDetail(d.data || null);
            setParties(p.data || []);
        } finally {
            setLoading(false);
        }
    }, [id, projectId]);

    useEffect(() => { load(); }, [load]);

    const run = async (fn, successMsg) => {
        setBusy(true);
        try {
            await fn();
            if (successMsg) toast.success(successMsg);
            await load();
            onChanged?.();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Action failed');
        } finally {
            setBusy(false);
        }
    };

    const doHandover = () => {
        if (!handoverTo) return toast.error('Choose a party');
        run(() => correspondenceService.handOver(id, { to_party_id: Number(handoverTo), note: note || null }), 'Handed over').then(() => { setNote(''); setHandoverTo(''); });
    };
    const doNote = () => {
        if (!note.trim()) return;
        run(() => correspondenceService.note(id, note), 'Note added').then(() => setNote(''));
    };
    const doClose = () => {
        run(() => correspondenceService.close(id, closingRef, note || null), 'Closed').then(() => { setShowClose(false); setClosingRef(''); setNote(''); });
    };
    const doReopen = () => run(() => correspondenceService.reopen(id, note || null), 'Reopened').then(() => setNote(''));
    const addParty = () => {
        if (!newParty?.name?.trim()) return;
        run(() => correspondenceService.createParty({ project_id: projectId, name: newParty.name, type: newParty.type || 'other' }), 'Party added').then(() => setNewParty(null));
    };

    const inputCls = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';
    const isClosed = detail?.status === 'closed';

    return (
        <div className="fixed inset-0 z-50 flex justify-end bg-black/50" onClick={onClose}>
            <div className="h-full w-full max-w-lg overflow-y-auto bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="sticky top-0 flex items-center justify-between border-b bg-white px-5 py-4">
                    <div>
                        <h3 className="text-base font-semibold text-gray-900">{correspondence.title}</h3>
                        <p className="text-xs text-gray-400">{correspondence.reference_no || `#${id}`}</p>
                    </div>
                    <div className="flex items-center gap-1">
                        <button onClick={() => correspondenceService.downloadPdf(id, correspondence.reference_no)} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Download PDF"><HiOutlineDownload className="h-5 w-5" /></button>
                        <button onClick={onClose} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><HiOutlineX className="h-5 w-5" /></button>
                    </div>
                </div>

                {loading || !detail ? <div className="p-6"><LoadingSpinner /></div> : (
                    <div className="space-y-5 p-5">
                        <div className="grid grid-cols-2 gap-3 rounded-lg bg-gray-50 p-3 text-sm">
                            <div><span className="text-xs uppercase text-gray-400">Status</span><div className="font-medium capitalize">{detail.status}</div></div>
                            <div><span className="text-xs uppercase text-gray-400">Currently at</span><div className="font-medium">{detail.current_party?.name || '—'}</div></div>
                            <div><span className="text-xs uppercase text-gray-400">Expected close</span><div>{detail.expected_close_date || '—'}</div></div>
                            <div><span className="text-xs uppercase text-gray-400">Actual close</span><div>{detail.actual_close_date || '—'}</div></div>
                            {detail.closing_reference && <div className="col-span-2"><span className="text-xs uppercase text-gray-400">Closing ref</span><div>{detail.closing_reference}</div></div>}
                        </div>

                        {/* Timeline */}
                        <div>
                            <h4 className="mb-2 text-sm font-semibold text-gray-700">History</h4>
                            <ol className="space-y-2 border-l-2 border-gray-100 pl-4">
                                {(detail.events || []).map((e) => (
                                    <li key={e.id} className="relative">
                                        <span className="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-primary-400" />
                                        <div className="text-sm font-medium text-gray-800">{EVENT_LABEL[e.event_type] || e.event_type}</div>
                                        {e.event_type === 'handed_over' && <div className="text-xs text-gray-500">{e.from_party?.name || '—'} → {e.to_party?.name || '—'}</div>}
                                        {e.event_type === 'status_changed' && <div className="text-xs text-gray-500">{e.from_status} → {e.to_status}</div>}
                                        {e.note && <div className="text-xs text-gray-600">{e.note}</div>}
                                        <div className="text-[11px] text-gray-400">{e.created_at ? new Date(e.created_at).toLocaleString() : ''}{e.creator ? ` · ${e.creator.first_name} ${e.creator.last_name}` : ''}</div>
                                    </li>
                                ))}
                                {(detail.events || []).length === 0 && <li className="text-sm text-gray-400">No history yet.</li>}
                            </ol>
                        </div>

                        {canEdit && !isClosed && (
                            <div className="space-y-4 border-t pt-4">
                                <textarea rows={2} placeholder="Note (optional for actions below)" value={note} onChange={(e) => setNote(e.target.value)} className={inputCls} />

                                {/* Hand over */}
                                <div>
                                    <label className="mb-1 block text-xs font-medium uppercase text-gray-500">Hand over to</label>
                                    <div className="flex gap-2">
                                        <select value={handoverTo} onChange={(e) => setHandoverTo(e.target.value)} className={inputCls}>
                                            <option value="">Select party…</option>
                                            {parties.map((p) => <option key={p.id} value={p.id}>{p.name}{p.type ? ` (${p.type.replace(/_/g, ' ')})` : ''}</option>)}
                                        </select>
                                        <button onClick={doHandover} disabled={busy} className="inline-flex shrink-0 items-center gap-1 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"><HiOutlineArrowRight className="h-4 w-4" /> Send</button>
                                    </div>
                                    {newParty === null ? (
                                        <button onClick={() => setNewParty({ name: '', type: 'other' })} className="mt-1.5 inline-flex items-center gap-1 text-xs text-primary-600 hover:underline"><HiOutlinePlus className="h-3.5 w-3.5" /> Add a party</button>
                                    ) : (
                                        <div className="mt-2 flex gap-2">
                                            <input type="text" placeholder="Party name" value={newParty.name} onChange={(e) => setNewParty((p) => ({ ...p, name: e.target.value }))} className={inputCls} />
                                            <select value={newParty.type} onChange={(e) => setNewParty((p) => ({ ...p, type: e.target.value }))} className={inputCls}>
                                                {PARTY_TYPES.map((t) => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
                                            </select>
                                            <button onClick={addParty} disabled={busy} className="shrink-0 rounded-lg bg-gray-800 px-3 py-2 text-sm text-white disabled:opacity-50">Add</button>
                                        </div>
                                    )}
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <button onClick={doNote} disabled={busy || !note.trim()} className="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">Add note</button>
                                    <button onClick={() => setShowClose((v) => !v)} disabled={busy} className="rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">Close…</button>
                                </div>

                                {showClose && (
                                    <div className="rounded-lg border border-green-200 bg-green-50 p-3">
                                        <p className="mb-2 text-xs text-green-800">Closing requires a reference and at least one attached document.</p>
                                        <input type="text" placeholder="Closing reference *" value={closingRef} onChange={(e) => setClosingRef(e.target.value)} className={inputCls} />
                                        <button onClick={doClose} disabled={busy || !closingRef.trim()} className="mt-2 w-full rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">Confirm close</button>
                                    </div>
                                )}
                            </div>
                        )}

                        {canEdit && isClosed && (
                            <div className="border-t pt-4">
                                <button onClick={doReopen} disabled={busy} className="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">Reopen</button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
