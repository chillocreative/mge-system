import { useState } from 'react';
import { HiOutlinePlus, HiOutlineTrash, HiOutlineX } from 'react-icons/hi';
import { buildRfaSubtypeOptions } from './rfaSubtypeOptions';

export const DETAIL_DEFAULTS = {
    category: '', discipline: '', document_reference: '', request_kind: '', work_scope: '',
    work_category: '', inspection_type: '', inspection_date: '', location: '', criticality: '',
    subcontractor_party_id: '', compliance_due_date: '', complied_date: '', action_required: '', memo_nature: '',
};

export const REVIEW_ROLES = [
    { role: 'jpriz', label: 'JPRIZ' },
    { role: 'jps', label: 'JPS' },
    { role: 'client', label: 'Client' },
    { role: 'subcontractor', label: 'Subcontractor' },
];

export const REVIEW_DEFAULTS = () => REVIEW_ROLES.map(({ role }) => ({
    party_role: role, project_party_id: '', status_raw: '', status_normalized: '',
    decision_date: '', closed_date: '', remarks: '',
}));

const NORMALIZED_STATUSES = [
    'open', 'pending', 'received', 'approved', 'accepted', 'replied', 'resolved',
    'declined', 'rejected', 'resubmit', 'closed', 'other',
];
const RELATION_TYPES = [
    ['response_to', 'Response to'], ['resubmission_of', 'Resubmission of'],
    ['supersedes', 'Supersedes'], ['closes', 'Closes'], ['related', 'Related'],
];
const inputClass = 'w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

function Field({ label, value, onChange, type = 'text', options, rows = 2 }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {options ? (
                <select value={value || ''} onChange={(e) => onChange(e.target.value)} className={inputClass}>
                    <option value="">Select…</option>
                    {options.map(([key, text]) => <option key={key} value={key}>{text}</option>)}
                </select>
            ) : type === 'textarea' ? (
                <textarea rows={rows} value={value || ''} onChange={(e) => onChange(e.target.value)} className={inputClass} />
            ) : (
                <input type={type} value={value || ''} onChange={(e) => onChange(e.target.value)} className={inputClass} />
            )}
        </div>
    );
}

function RfaSubtypeField({ value, onChange, subtypes, canEdit, onAdd, onDelete, saving, deletingId }) {
    const [managing, setManaging] = useState(false);
    const [draft, setDraft] = useState({ code: '', name: '' });
    const options = buildRfaSubtypeOptions(subtypes, value);

    const submit = async (event) => {
        event.preventDefault();
        if (!draft.code.trim() || !draft.name.trim()) return;
        if (await onAdd({ code: draft.code, name: draft.name })) {
            setDraft({ code: '', name: '' });
        }
    };

    return (
        <div className={managing ? 'sm:col-span-2' : ''}>
            <div className="mb-1 flex items-center justify-between gap-2">
                <label className="block text-sm font-medium text-gray-700">RFA Subtype</label>
                {canEdit && (
                    <button type="button" onClick={() => setManaging((current) => !current)}
                        className="inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-800">
                        {managing && <HiOutlineX className="h-3.5 w-3.5" />}
                        {managing ? 'Close' : '+ Add'}
                    </button>
                )}
            </div>
            <select value={value || ''} onChange={(event) => onChange(event.target.value)} className={inputClass}>
                <option value="">Select…</option>
                {options.map((option) => (
                    <option key={`${option.legacy ? 'legacy' : option.id}-${option.code}`} value={option.code}>
                        {option.code} — {option.name}{option.legacy ? ' (not in current list)' : ''}
                    </option>
                ))}
            </select>

            {managing && (
                <div className="mt-3 rounded-lg border border-gray-200 bg-white p-3">
                    <form onSubmit={submit} className="grid grid-cols-1 gap-2 sm:grid-cols-[10rem_1fr_auto]">
                        <input type="text" value={draft.code} maxLength={60} required placeholder="Code, e.g. CALCS"
                            onChange={(event) => setDraft((current) => ({ ...current, code: event.target.value.toUpperCase() }))}
                            className={inputClass} />
                        <input type="text" value={draft.name} maxLength={255} required placeholder="Display name"
                            onChange={(event) => setDraft((current) => ({ ...current, name: event.target.value }))}
                            className={inputClass} />
                        <button type="submit" disabled={saving}
                            className="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                            {saving ? 'Adding…' : 'Add'}
                        </button>
                    </form>

                    <div className="mt-3 space-y-1 border-t border-gray-100 pt-3">
                        {subtypes.map((subtype) => (
                            <div key={subtype.id} className="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-gray-50">
                                <span className="min-w-0 text-sm text-gray-700">
                                    <strong>{subtype.code}</strong> — {subtype.name}
                                </span>
                                <button type="button" onClick={() => onDelete(subtype)} disabled={deletingId !== null}
                                    aria-label={`Delete ${subtype.code}`}
                                    className="shrink-0 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-40">
                                    {deletingId === subtype.id ? <span className="px-1 text-xs">Deleting…</span> : <HiOutlineTrash className="h-4 w-4" />}
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

export default function CorrespondenceTrackingFields({
    form, setForm, parties, linkCandidates, editingId, rfaSubtypes = [], canEditRfaSubtypes = false,
    onAddRfaSubtype, onDeleteRfaSubtype, rfaSubtypeSaving = false, deletingRfaSubtypeId = null,
}) {
    const type = String(form.type || '').toLowerCase();
    const detail = form.detail || DETAIL_DEFAULTS;
    const setDetail = (key, value) => setForm((current) => ({
        ...current,
        detail: { ...current.detail, [key]: value },
    }));
    const setReview = (index, key, value) => setForm((current) => ({
        ...current,
        party_reviews: current.party_reviews.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row),
    }));
    const setLink = (index, key, value) => setForm((current) => ({
        ...current,
        links: current.links.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row),
    }));
    const addLink = () => {
        const candidate = linkCandidates.find((item) => String(item.id) !== String(editingId));
        if (!candidate) return;
        setForm((current) => ({
            ...current,
            links: [...current.links, { target_correspondence_id: candidate.id, relation_type: 'related', note: '' }],
        }));
    };
    const removeLink = (index) => setForm((current) => ({
        ...current,
        links: current.links.filter((_, rowIndex) => rowIndex !== index),
    }));

    const showCommonDocumentFields = ['rfa', 'rfi', 'rfwi', 'ei', 'ncr', 'site_memo', 'sm'].includes(type);

    return (
        <>
            <section className="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
                <div className="mb-3">
                    <h4 className="text-sm font-semibold text-gray-900">Document details</h4>
                    <p className="text-xs text-gray-500">Fields follow the selected correspondence type.</p>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {type === 'rfa' ? (
                        <RfaSubtypeField value={form.document_subtype} subtypes={rfaSubtypes} canEdit={canEditRfaSubtypes}
                            saving={rfaSubtypeSaving} deletingId={deletingRfaSubtypeId}
                            onAdd={onAddRfaSubtype} onDelete={onDeleteRfaSubtype}
                            onChange={(value) => setForm((current) => ({ ...current, document_subtype: value }))} />
                    ) : (
                        <Field label="Document Subtype" value={form.document_subtype}
                            onChange={(value) => setForm((current) => ({ ...current, document_subtype: value }))} />
                    )}

                    {showCommonDocumentFields && <Field label="Document Reference" value={detail.document_reference} onChange={(value) => setDetail('document_reference', value)} />}
                    {['rfa', 'rfi'].includes(type) && <Field label="Category" value={detail.category} onChange={(value) => setDetail('category', value)} />}
                    {['rfa', 'rfi'].includes(type) && <Field label="Discipline" value={detail.discipline} onChange={(value) => setDetail('discipline', value)} />}
                    {type === 'rfi' && <Field label="Request Kind" value={detail.request_kind} onChange={(value) => setDetail('request_kind', value)} />}

                    {type === 'rfwi' && <Field label="Work Scope" value={detail.work_scope} onChange={(value) => setDetail('work_scope', value)} />}
                    {type === 'rfwi' && <Field label="Work Category" value={detail.work_category} onChange={(value) => setDetail('work_category', value)} />}
                    {type === 'rfwi' && <Field label="Inspection / Test Type" value={detail.inspection_type} onChange={(value) => setDetail('inspection_type', value)} />}
                    {type === 'rfwi' && <Field label="Inspection Date" type="date" value={detail.inspection_date} onChange={(value) => setDetail('inspection_date', value)} />}

                    {['rfwi', 'ncr'].includes(type) && <Field label="Location / Zone" value={detail.location} onChange={(value) => setDetail('location', value)} />}
                    {type === 'ncr' && <Field label="Criticality" value={detail.criticality}
                        options={['low', 'medium', 'high', 'critical'].map((item) => [item, item[0].toUpperCase() + item.slice(1)])}
                        onChange={(value) => setDetail('criticality', value)} />}

                    {['ei', 'ncr'].includes(type) && (
                        <Field label="Subcontractor" value={detail.subcontractor_party_id}
                            options={parties.filter((party) => party.type === 'subcontractor').map((party) => [party.id, party.name])}
                            onChange={(value) => setDetail('subcontractor_party_id', value)} />
                    )}
                    {type === 'ei' && <Field label="Compliance Due Date" type="date" value={detail.compliance_due_date} onChange={(value) => setDetail('compliance_due_date', value)} />}
                    {type === 'ei' && <Field label="Complied Date" type="date" value={detail.complied_date} onChange={(value) => setDetail('complied_date', value)} />}
                    {['site_memo', 'sm'].includes(type) && <Field label="Memo Nature" value={detail.memo_nature} onChange={(value) => setDetail('memo_nature', value)} />}
                </div>

                {['ei', 'ncr', 'site_memo', 'sm'].includes(type) && (
                    <div className="mt-4">
                        <Field label="Action Required" type="textarea" value={detail.action_required} onChange={(value) => setDetail('action_required', value)} />
                    </div>
                )}
            </section>

            <section className="rounded-xl border border-gray-200 p-4">
                <div className="mb-3">
                    <h4 className="text-sm font-semibold text-gray-900">Party review status</h4>
                    <p className="text-xs text-gray-500">JPRIZ, JPS, Client and Subcontractor remain separately traceable.</p>
                </div>
                <div className="space-y-3">
                    {form.party_reviews.map((review, index) => {
                        const label = REVIEW_ROLES.find((item) => item.role === review.party_role)?.label || review.party_role;
                        return (
                            <div key={review.party_role} className="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">{label}</p>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <Field label="Party" value={review.project_party_id}
                                        options={parties.map((party) => [party.id, party.name])}
                                        onChange={(value) => setReview(index, 'project_party_id', value)} />
                                    <Field label="Standard Status" value={review.status_normalized}
                                        options={NORMALIZED_STATUSES.map((status) => [status, status[0].toUpperCase() + status.slice(1)])}
                                        onChange={(value) => setReview(index, 'status_normalized', value)} />
                                    <Field label="Decision Date" type="date" value={review.decision_date} onChange={(value) => setReview(index, 'decision_date', value)} />
                                    <Field label="Closed Date" type="date" value={review.closed_date} onChange={(value) => setReview(index, 'closed_date', value)} />
                                    <Field label="Remarks" value={review.remarks} onChange={(value) => setReview(index, 'remarks', value)} />
                                </div>
                            </div>
                        );
                    })}
                </div>
            </section>

            <section className="rounded-xl border border-gray-200 p-4">
                <div className="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h4 className="text-sm font-semibold text-gray-900">Linked correspondence</h4>
                        <p className="text-xs text-gray-500">Connect responses, resubmissions, superseded documents and closing references.</p>
                    </div>
                    <button type="button" onClick={addLink} disabled={!linkCandidates.some((item) => String(item.id) !== String(editingId))}
                        className="inline-flex shrink-0 items-center gap-1 rounded-lg border border-primary-200 px-2.5 py-1.5 text-xs font-semibold text-primary-700 hover:bg-primary-50 disabled:opacity-40">
                        <HiOutlinePlus className="h-4 w-4" /> Add link
                    </button>
                </div>
                <div className="space-y-3">
                    {form.links.map((link, index) => (
                        <div key={`${link.target_correspondence_id}-${index}`} className="grid grid-cols-1 gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-[1fr_10rem_auto]">
                            <select value={link.target_correspondence_id || ''} onChange={(e) => setLink(index, 'target_correspondence_id', e.target.value)} className={inputClass}>
                                <option value="">Select correspondence…</option>
                                {linkCandidates.filter((item) => String(item.id) !== String(editingId)).map((item) => (
                                    <option key={item.id} value={item.id}>{item.reference_no || `#${item.id}`} — {item.title}</option>
                                ))}
                            </select>
                            <select value={link.relation_type} onChange={(e) => setLink(index, 'relation_type', e.target.value)} className={inputClass}>
                                {RELATION_TYPES.map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                            </select>
                            <button type="button" onClick={() => removeLink(index)} className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove link">
                                <HiOutlineTrash className="h-5 w-5" />
                            </button>
                            <input type="text" value={link.note || ''} onChange={(e) => setLink(index, 'note', e.target.value)} placeholder="Link note (optional)" className={`${inputClass} sm:col-span-3`} />
                        </div>
                    ))}
                    {form.links.length === 0 && <p className="rounded-lg bg-gray-50 px-3 py-4 text-center text-xs text-gray-400">No linked correspondence.</p>}
                </div>
            </section>
        </>
    );
}
