import { useState } from 'react';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

const SLOT_LABELS = { prepared: 'Prepared by', verified: 'Verified by', accepted: 'Accepted by' };

// Value/form editor. Two kinds of fields render here:
//  - `config.fields` (e.g. cover's `project_title`) save into this section's
//    `overrides` object, keyed by field name.
//  - `config.reportFields` (e.g. cover's `evaluation_date`) and the
//    signatory cards (`config.signatories`) are report-level fields — they
//    save via PUT /monthly-reports/{id}, not section overrides (see
//    task-9-brief.md ruling), so edits go through `onReportFieldChange`
//    instead of `onOverridesChange`.
export default function ValueSection({ config, merged, canEdit, onOverridesChange, reportDraft, onReportFieldChange }) {
    const [fields, setFields] = useState(() => {
        const initial = {};
        (config.fields || []).forEach((f) => {
            initial[f.key] = merged?.[f.key] ?? '';
        });
        return initial;
    });

    const setField = (key, value) => {
        const next = { ...fields, [key]: value };
        setFields(next);
        onOverridesChange(next);
    };

    const signatories = reportDraft?.signatories || [];

    const setSignatory = (slot, key, value) => {
        const next = signatories.map((s) => (s.slot === slot ? { ...s, [key]: value } : s));
        onReportFieldChange('signatories', next);
    };

    return (
        <div className="space-y-6">
            {(config.fields || []).length > 0 && (
                <div className="grid gap-4 sm:grid-cols-2">
                    {config.fields.map((f) => (
                        <div key={f.key}>
                            <label className="mb-1 block text-sm font-medium text-gray-700">{f.label}</label>
                            <input
                                type={f.type === 'date' ? 'date' : 'text'}
                                value={fields[f.key] ?? ''}
                                onChange={(e) => setField(f.key, e.target.value)}
                                disabled={!canEdit}
                                className={`${input} disabled:bg-gray-100`}
                            />
                        </div>
                    ))}
                </div>
            )}

            {(config.reportFields || []).length > 0 && (
                <div className="grid gap-4 sm:grid-cols-2">
                    {config.reportFields.map((f) => (
                        <div key={f.key}>
                            <label className="mb-1 block text-sm font-medium text-gray-700">{f.label}</label>
                            <input
                                type={f.type === 'date' ? 'date' : 'text'}
                                value={reportDraft?.[f.key] ?? ''}
                                onChange={(e) => onReportFieldChange(f.key, e.target.value)}
                                disabled={!canEdit}
                                className={`${input} disabled:bg-gray-100`}
                            />
                        </div>
                    ))}
                </div>
            )}

            {config.signatories && (
                <div>
                    <h3 className="mb-2 text-sm font-semibold text-gray-900">Signatories</h3>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {signatories.map((s) => (
                            <div key={s.slot} className="rounded-lg border border-gray-200 p-3">
                                <p className="mb-2 text-xs font-semibold uppercase text-gray-500">{SLOT_LABELS[s.slot] || s.slot}</p>
                                <div className="space-y-2">
                                    <input
                                        value={s.name || ''}
                                        onChange={(e) => setSignatory(s.slot, 'name', e.target.value)}
                                        placeholder="Name"
                                        disabled={!canEdit}
                                        className={`${input} disabled:bg-gray-100`}
                                    />
                                    <input
                                        value={s.designation || ''}
                                        onChange={(e) => setSignatory(s.slot, 'designation', e.target.value)}
                                        placeholder="Designation"
                                        disabled={!canEdit}
                                        className={`${input} disabled:bg-gray-100`}
                                    />
                                    <input
                                        value={s.company || ''}
                                        onChange={(e) => setSignatory(s.slot, 'company', e.target.value)}
                                        placeholder="Company"
                                        disabled={!canEdit}
                                        className={`${input} disabled:bg-gray-100`}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
