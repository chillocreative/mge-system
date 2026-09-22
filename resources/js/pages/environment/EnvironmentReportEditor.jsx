import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { useConfirm } from '@/context/ConfirmContext';
import environmentReportService from '@/services/environmentReportService';
import environmentSettingService from '@/services/environmentSettingService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import toast from 'react-hot-toast';
import {
    HiOutlineArrowLeft, HiOutlineSave, HiOutlineRefresh, HiOutlineCheckCircle, HiOutlineLockOpen,
    HiOutlineDownload, HiOutlineExclamation, HiOutlinePlus, HiOutlineTrash,
} from 'react-icons/hi';

const statusColors = {
    draft: 'bg-gray-100 text-gray-600',
    final: 'bg-green-100 text-green-700',
};

const SECTION_TITLES = {
    contract: '1.0 Contract Particular',
    ems: '2.0 Environmental Management System (EMS)',
    introduction: '3.0 Introduction',
    flow_chart: '4.0 Flow Chart',
    policy: '5.0 Environment Policy',
    location: '6.0 Monitoring Location',
    parameters: '7.0 Quality References',
    results: '8.0 Monitoring Result',
    bmp: '9.0 Best Management Practices (BMP)',
};

const SECTION_ORDER = ['contract', 'ems', 'introduction', 'flow_chart', 'policy', 'location', 'parameters', 'results', 'bmp'];

const FLOW_BOXES = [
    { key: 'proponent', label: 'Project Proponent' },
    { key: 'implementer', label: 'Project Implementer' },
    { key: 'main_contractor', label: 'Main Contractor' },
    { key: 'env_officer', label: 'Environmental Officer' },
    { key: 'env_consultant', label: 'Environmental Consultant' },
];

const RESULT_PARAM_COLUMNS = ['nwqs', 'doe', 'w1', 'w2', 'w3', 'w4'];

function deepClone(v) {
    return v === undefined ? v : JSON.parse(JSON.stringify(v));
}
function linesToText(lines) {
    return Array.isArray(lines) ? lines.join('\n') : '';
}
function textToLines(text) {
    return text.split('\n').map((l) => l).filter((l, i, arr) => !(l === '' && i === arr.length - 1));
}

export default function EnvironmentReportEditor() {
    const { id } = useParams();
    const { can } = useAuth();
    const canManage = can('environmental.manage');
    const confirm = useConfirm();

    const [report, setReport] = useState(null);
    const [draft, setDraft] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [regenKey, setRegenKey] = useState(null);
    const [statusBusy, setStatusBusy] = useState(false);
    const [settings, setSettings] = useState(null);
    const [assets, setAssets] = useState([]);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await environmentReportService.get(id);
            setReport(res.data);
            setDraft({
                title: res.data.title || '',
                period_start: res.data.period_start || '',
                period_end: res.data.period_end || '',
                signatories: deepClone(res.data.signatories) || [],
                sections: deepClone(res.data.sections) || {},
            });
            const assetRes = await environmentReportService.listAssets(id);
            setAssets(assetRes.data || []);
            if (res.data.project_id) {
                try {
                    const settingsRes = await environmentSettingService.get(res.data.project_id);
                    setSettings(settingsRes.data);
                } catch { /* ignore */ }
            }
        } catch {
            toast.error('Failed to load report');
        } finally {
            setLoading(false);
        }
    }, [id]);

    useEffect(() => { load(); }, [load]);

    const isFinal = report?.status === 'final';
    const canEdit = canManage && !isFinal;

    const setSection = (key, updater) => {
        setDraft((prev) => {
            const current = prev.sections[key] || {};
            const next = typeof updater === 'function' ? updater(current) : updater;
            return { ...prev, sections: { ...prev.sections, [key]: next } };
        });
    };

    const dirtyKeys = report && draft
        ? SECTION_ORDER.filter((k) => JSON.stringify(draft.sections[k] || {}) !== JSON.stringify(report.sections[k] || {}))
        : [];
    const topDirty = report && draft && (
        draft.title !== (report.title || '')
        || draft.period_start !== (report.period_start || '')
        || draft.period_end !== (report.period_end || '')
        || JSON.stringify(draft.signatories) !== JSON.stringify(report.signatories || [])
    );
    const anyDirty = dirtyKeys.length > 0 || topDirty;

    const saveAll = async () => {
        if (!report || !anyDirty) return;
        setSaving(true);
        try {
            const payload = {};
            if (draft.title !== (report.title || '')) payload.title = draft.title;
            if (draft.period_start !== (report.period_start || '')) payload.period_start = draft.period_start;
            if (draft.period_end !== (report.period_end || '')) payload.period_end = draft.period_end;
            if (JSON.stringify(draft.signatories) !== JSON.stringify(report.signatories || [])) payload.signatories = draft.signatories;
            if (dirtyKeys.length) {
                payload.sections = {};
                dirtyKeys.forEach((k) => { payload.sections[k] = draft.sections[k]; });
            }
            const res = await environmentReportService.update(report.id, payload);
            setReport(res.data);
            setDraft({
                title: res.data.title || '',
                period_start: res.data.period_start || '',
                period_end: res.data.period_end || '',
                signatories: deepClone(res.data.signatories) || [],
                sections: deepClone(res.data.sections) || {},
            });
            toast.success('Report saved');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save report');
        } finally {
            setSaving(false);
        }
    };

    const regenerateSection = async (key) => {
        if (!(await confirm({ title: 'Regenerate section?', message: 'This section will be rebuilt from source records, discarding any unsaved edits for it.', danger: false, confirmText: 'Regenerate' }))) return;
        setRegenKey(key);
        try {
            const res = await environmentReportService.regenerate(report.id, key);
            setReport(res.data);
            setDraft((prev) => ({ ...prev, sections: { ...prev.sections, [key]: deepClone(res.data.sections[key]) } }));
            toast.success('Section regenerated');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to regenerate section');
        } finally {
            setRegenKey(null);
        }
    };

    const finalise = async () => {
        if (!(await confirm({ title: 'Finalise report?', message: 'Finalised reports cannot be edited unless reopened. Continue?', confirmText: 'Finalise' }))) return;
        setStatusBusy(true);
        try {
            const res = await environmentReportService.finalise(report.id);
            setReport(res.data);
            toast.success('Report finalised');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to finalise report');
        } finally {
            setStatusBusy(false);
        }
    };

    const reopen = async () => {
        if (!(await confirm({ title: 'Reopen report?', message: 'This report will become editable again.', danger: false, confirmText: 'Reopen' }))) return;
        setStatusBusy(true);
        try {
            const res = await environmentReportService.reopen(report.id);
            setReport(res.data);
            toast.success('Report reopened');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to reopen report');
        } finally {
            setStatusBusy(false);
        }
    };

    const downloadPdf = async () => {
        try { await environmentReportService.downloadPdf(report.id); }
        catch { toast.error('Failed to download PDF'); }
    };
    const downloadDocx = async () => {
        try { await environmentReportService.downloadDocx(report.id); }
        catch { toast.error('Failed to download DOCX'); }
    };

    const refreshAssets = async () => {
        try {
            const res = await environmentReportService.listAssets(report.id);
            setAssets(res.data || []);
        } catch { /* ignore */ }
    };

    if (loading) return <LoadingSpinner />;
    if (!report || !draft) {
        return (
            <div className="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">
                Report not found.
            </div>
        );
    }

    const fieldClass = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-500';
    const cardClass = 'rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-6';

    return (
        <div className="space-y-6">
            <Link to="/environment/reports" className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <HiOutlineArrowLeft className="h-4 w-4" /> Back to Monthly Reports
            </Link>

            {/* Sticky top bar */}
            <div className="sticky top-0 z-10 flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-lg font-bold text-gray-900">{report.title || 'Monthly Environment Report'}</h1>
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[report.status]}`}>{report.status}</span>
                    </div>
                    <p className="mt-1 text-sm text-gray-500">
                        No. {report.report_no} &middot; {formatDate(report.period_start)} - {formatDate(report.period_end)}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {canEdit && (
                        <button type="button" onClick={saveAll} disabled={saving || !anyDirty} className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                            <HiOutlineSave className="h-4 w-4" /> {saving ? 'Saving…' : 'Save'}
                        </button>
                    )}
                    <Link to={`/environment/reports/${id}`} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Preview
                    </Link>
                    {canManage && !isFinal && (
                        <button type="button" onClick={finalise} disabled={statusBusy} className="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50">
                            <HiOutlineCheckCircle className="h-4 w-4" /> Finalise
                        </button>
                    )}
                    {canManage && isFinal && (
                        <button type="button" onClick={reopen} disabled={statusBusy} className="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-100 disabled:opacity-50">
                            <HiOutlineLockOpen className="h-4 w-4" /> Reopen
                        </button>
                    )}
                    <button type="button" onClick={downloadPdf} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <HiOutlineDownload className="h-4 w-4" /> PDF
                    </button>
                    <button type="button" onClick={downloadDocx} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <HiOutlineDownload className="h-4 w-4" /> DOCX
                    </button>
                </div>
            </div>

            {isFinal && (
                <div className="flex items-center gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                    <HiOutlineExclamation className="h-5 w-5 shrink-0" />
                    This report is finalised and read-only. Reopen it to make changes.
                </div>
            )}

            {/* Cover & Signatories */}
            <div className={cardClass}>
                <h2 className="mb-4 text-base font-semibold text-gray-900">Cover &amp; Signatories</h2>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="sm:col-span-3">
                        <label className="mb-1 block text-sm font-medium text-gray-700">Title</label>
                        <input type="text" disabled={!canEdit} value={draft.title} onChange={(e) => setDraft((p) => ({ ...p, title: e.target.value }))} className={fieldClass} />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Period Start</label>
                        <input type="date" disabled={!canEdit} value={draft.period_start} onChange={(e) => setDraft((p) => ({ ...p, period_start: e.target.value }))} className={fieldClass} />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Period End</label>
                        <input type="date" disabled={!canEdit} value={draft.period_end} onChange={(e) => setDraft((p) => ({ ...p, period_end: e.target.value }))} className={fieldClass} />
                    </div>
                </div>

                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    {['prepared', 'verified', 'accepted'].map((slot) => {
                        const idx = draft.signatories.findIndex((s) => s.slot === slot);
                        const sig = idx >= 0 ? draft.signatories[idx] : { slot, name: '', designation: '', company: '' };
                        const updateSig = (field, value) => {
                            setDraft((p) => {
                                const list = [...p.signatories];
                                if (idx >= 0) list[idx] = { ...list[idx], [field]: value };
                                else list.push({ ...sig, [field]: value });
                                return { ...p, signatories: list };
                            });
                        };
                        return (
                            <div key={slot} className="rounded-lg border border-gray-200 p-3">
                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">{slot}</p>
                                <div className="space-y-2">
                                    <input type="text" placeholder="Name" disabled={!canEdit} value={sig.name || ''} onChange={(e) => updateSig('name', e.target.value)} className={fieldClass} />
                                    <input type="text" placeholder="Designation" disabled={!canEdit} value={sig.designation || ''} onChange={(e) => updateSig('designation', e.target.value)} className={fieldClass} />
                                    <input type="text" placeholder="Company" disabled={!canEdit} value={sig.company || ''} onChange={(e) => updateSig('company', e.target.value)} className={fieldClass} />
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* 1.0 Contract Particular */}
            <SectionCard title={SECTION_TITLES.contract} onRegenerate={() => regenerateSection('contract')} regenerating={regenKey === 'contract'} canEdit={canEdit}>
                {(() => {
                    const rows = draft.sections.contract?.rows || [];
                    const setRows = (next) => setSection('contract', { rows: next });
                    return (
                        <div className="space-y-2">
                            {rows.map((row, i) => (
                                <div key={i} className="flex gap-2">
                                    <input type="text" disabled={!canEdit} value={row.label || ''} onChange={(e) => { const next = [...rows]; next[i] = { ...next[i], label: e.target.value }; setRows(next); }}
                                        className={`${fieldClass} sm:max-w-[16rem]`} placeholder="Label" />
                                    <textarea disabled={!canEdit} rows={1} value={row.value || ''} onChange={(e) => { const next = [...rows]; next[i] = { ...next[i], value: e.target.value }; setRows(next); }}
                                        className={fieldClass} placeholder="Value (use | to separate multiple lines in the export)" />
                                    {canEdit && (
                                        <button type="button" onClick={() => setRows(rows.filter((_, idx) => idx !== i))} className="shrink-0 rounded p-2 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                            <HiOutlineTrash className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                            ))}
                            <p className="text-xs text-gray-400">Tip: separate a value into multiple lines in the export using |</p>
                            {canEdit && (
                                <button type="button" onClick={() => setRows([...rows, { label: '', value: '' }])} className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                                    <HiOutlinePlus className="h-4 w-4" /> Add row
                                </button>
                            )}
                        </div>
                    );
                })()}
            </SectionCard>

            {/* 2.0 EMS */}
            <SectionCard title={SECTION_TITLES.ems} onRegenerate={() => regenerateSection('ems')} regenerating={regenKey === 'ems'} canEdit={canEdit}>
                {(() => {
                    const ems = draft.sections.ems || {};
                    const setEms = (patch) => setSection('ems', (cur) => ({ ...cur, ...patch }));
                    const certAssets = assets.filter((a) => a.kind === 'consultant_cert');
                    return (
                        <div className="space-y-3">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <Labeled label="Consultant Company"><input type="text" disabled={!canEdit} value={ems.consultant_company || ''} onChange={(e) => setEms({ consultant_company: e.target.value })} className={fieldClass} /></Labeled>
                                <Labeled label="Consultant Name"><input type="text" disabled={!canEdit} value={ems.consultant_name || ''} onChange={(e) => setEms({ consultant_name: e.target.value })} className={fieldClass} /></Labeled>
                                <Labeled label="Consultant Reg. No"><input type="text" disabled={!canEdit} value={ems.consultant_reg_no || ''} onChange={(e) => setEms({ consultant_reg_no: e.target.value })} className={fieldClass} /></Labeled>
                                <Labeled label="Officer Name"><input type="text" disabled={!canEdit} value={ems.officer_name || ''} onChange={(e) => setEms({ officer_name: e.target.value })} className={fieldClass} /></Labeled>
                                <Labeled label="Officer Reg. No"><input type="text" disabled={!canEdit} value={ems.officer_reg_no || ''} onChange={(e) => setEms({ officer_reg_no: e.target.value })} className={fieldClass} /></Labeled>
                            </div>
                            <Labeled label="Certified Text"><textarea rows={3} disabled={!canEdit} value={ems.certified_text || ''} onChange={(e) => setEms({ certified_text: e.target.value })} className={fieldClass} /></Labeled>

                            <AssetUploadSlot
                                label="Consultant certificate"
                                kind="consultant_cert"
                                reportId={report.id}
                                canEdit={canEdit}
                                multiple
                                items={certAssets}
                                onChanged={refreshAssets}
                            />
                        </div>
                    );
                })()}
            </SectionCard>

            {/* 3.0 Introduction */}
            <SectionCard title={SECTION_TITLES.introduction} onRegenerate={() => regenerateSection('introduction')} regenerating={regenKey === 'introduction'} canEdit={canEdit}>
                <textarea rows={6} disabled={!canEdit} value={draft.sections.introduction?.text || ''} onChange={(e) => setSection('introduction', { text: e.target.value })} className={fieldClass} />
            </SectionCard>

            {/* 4.0 Flow Chart */}
            <SectionCard title={SECTION_TITLES.flow_chart} onRegenerate={() => regenerateSection('flow_chart')} regenerating={regenKey === 'flow_chart'} canEdit={canEdit}>
                {(() => {
                    const fc = draft.sections.flow_chart || {};
                    const setBox = (key, patch) => setSection('flow_chart', (cur) => ({ ...cur, [key]: { ...(cur[key] || {}), ...patch } }));
                    const plans = fc.plans || [];
                    const setPlans = (next) => setSection('flow_chart', (cur) => ({ ...cur, plans: next }));
                    return (
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {FLOW_BOXES.map(({ key, label }) => {
                                    const box = fc[key] || { title: label, lines: [] };
                                    return (
                                        <div key={key} className="rounded-lg border border-gray-200 p-3">
                                            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">{label}</p>
                                            <input type="text" disabled={!canEdit} value={box.title || ''} onChange={(e) => setBox(key, { title: e.target.value })} className={`${fieldClass} mb-2`} placeholder="Box title" />
                                            <textarea rows={3} disabled={!canEdit} value={linesToText(box.lines)} onChange={(e) => setBox(key, { lines: textToLines(e.target.value) })} className={fieldClass} placeholder="One line per row" />
                                        </div>
                                    );
                                })}
                            </div>
                            <div>
                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Plans</p>
                                <div className="space-y-2">
                                    {plans.map((plan, i) => (
                                        <div key={i} className="flex gap-2">
                                            <input type="text" disabled={!canEdit} value={plan} onChange={(e) => { const next = [...plans]; next[i] = e.target.value; setPlans(next); }} className={fieldClass} />
                                            {canEdit && (
                                                <button type="button" onClick={() => setPlans(plans.filter((_, idx) => idx !== i))} className="shrink-0 rounded p-2 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                                    <HiOutlineTrash className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                    {canEdit && (
                                        <button type="button" onClick={() => setPlans([...plans, ''])} className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                                            <HiOutlinePlus className="h-4 w-4" /> Add plan
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    );
                })()}
            </SectionCard>

            {/* 5.0 Environment Policy */}
            <ImageSourceCard
                title={SECTION_TITLES.policy}
                sectionKey="policy"
                overrideKind="policy_override"
                projectImageUrl={settings?.policy_image_url}
                report={report}
                draft={draft}
                assets={assets}
                setSection={setSection}
                canEdit={canEdit}
                regenerating={regenKey === 'policy'}
                onRegenerate={() => regenerateSection('policy')}
                onAssetsChanged={refreshAssets}
                fieldClass={fieldClass}
            />

            {/* 6.0 Monitoring Location */}
            <ImageSourceCard
                title={SECTION_TITLES.location}
                sectionKey="location"
                overrideKind="location_override"
                projectImageUrl={settings?.location_map_url}
                report={report}
                draft={draft}
                assets={assets}
                setSection={setSection}
                canEdit={canEdit}
                regenerating={regenKey === 'location'}
                onRegenerate={() => regenerateSection('location')}
                onAssetsChanged={refreshAssets}
                fieldClass={fieldClass}
            />

            {/* 7.0 Quality References */}
            <SectionCard title={SECTION_TITLES.parameters} onRegenerate={() => regenerateSection('parameters')} regenerating={regenKey === 'parameters'} canEdit={canEdit}>
                {(() => {
                    const params = draft.sections.parameters || {};
                    const setList = (key, text) => setSection('parameters', (cur) => ({ ...cur, [key]: textToLines(text) }));
                    const periods = params.periods || [];
                    const setPeriods = (next) => setSection('parameters', (cur) => ({ ...cur, periods: next }));
                    const toggle = (v) => (v === '✓' ? '-' : '✓');
                    return (
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Labeled label="Water Parameters"><textarea rows={4} disabled={!canEdit} value={linesToText(params.water)} onChange={(e) => setList('water', e.target.value)} className={fieldClass} /></Labeled>
                                <Labeled label="Air Parameters"><textarea rows={4} disabled={!canEdit} value={linesToText(params.air)} onChange={(e) => setList('air', e.target.value)} className={fieldClass} /></Labeled>
                                <Labeled label="Noise Parameters"><textarea rows={2} disabled={!canEdit} value={linesToText(params.noise)} onChange={(e) => setList('noise', e.target.value)} className={fieldClass} /></Labeled>
                                <Labeled label="Vibration Parameters"><textarea rows={2} disabled={!canEdit} value={linesToText(params.vibration)} onChange={(e) => setList('vibration', e.target.value)} className={fieldClass} /></Labeled>
                            </div>
                            <div>
                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Monitoring Period</p>
                                <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
                                    <table className="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-2 py-2 text-left font-semibold text-gray-500">No.</th>
                                                <th className="px-2 py-2 text-left font-semibold text-gray-500">Session</th>
                                                <th className="px-2 py-2 text-center font-semibold text-gray-500">Water</th>
                                                <th className="px-2 py-2 text-center font-semibold text-gray-500">Air</th>
                                                <th className="px-2 py-2 text-center font-semibold text-gray-500">Noise</th>
                                                <th className="px-2 py-2 text-center font-semibold text-gray-500">Vibration</th>
                                                {canEdit && <th></th>}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {periods.map((row, i) => (
                                                <tr key={i}>
                                                    <td className="px-2 py-1.5">
                                                        <input type="number" disabled={!canEdit} value={row.no ?? ''} onChange={(e) => { const next = [...periods]; next[i] = { ...next[i], no: Number(e.target.value) }; setPeriods(next); }} className="w-14 rounded border border-gray-300 px-1.5 py-1 text-xs" />
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <input type="text" disabled={!canEdit} value={row.session || ''} onChange={(e) => { const next = [...periods]; next[i] = { ...next[i], session: e.target.value }; setPeriods(next); }} className="w-32 rounded border border-gray-300 px-1.5 py-1 text-xs" />
                                                    </td>
                                                    {['water', 'air', 'noise', 'vibration'].map((f) => (
                                                        <td key={f} className="px-2 py-1.5 text-center">
                                                            <button type="button" disabled={!canEdit} onClick={() => { const next = [...periods]; next[i] = { ...next[i], [f]: toggle(next[i][f]) }; setPeriods(next); }}
                                                                className={`rounded px-2 py-1 font-bold ${row[f] === '✓' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'}`}>
                                                                {row[f] === '✓' ? '✓' : '-'}
                                                            </button>
                                                        </td>
                                                    ))}
                                                    {canEdit && (
                                                        <td className="px-2 py-1.5 text-right">
                                                            <button type="button" onClick={() => setPeriods(periods.filter((_, idx) => idx !== i))} className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                                                <HiOutlineTrash className="h-3.5 w-3.5" />
                                                            </button>
                                                        </td>
                                                    )}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {canEdit && (
                                    <button type="button" onClick={() => setPeriods([...periods, { no: periods.length + 1, session: '', water: '-', air: '-', noise: '-', vibration: '-' }])} className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                                        <HiOutlinePlus className="h-4 w-4" /> Add row
                                    </button>
                                )}
                            </div>
                        </div>
                    );
                })()}
            </SectionCard>

            {/* 8.0 Monitoring Result */}
            <SectionCard
                title={SECTION_TITLES.results}
                onRegenerate={() => regenerateSection('results')}
                regenerating={regenKey === 'results'}
                canEdit={canEdit}
                extraAction={canEdit && (
                    <button type="button" onClick={() => regenerateSection('results')} disabled={regenKey === 'results'} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                        <HiOutlineRefresh className="h-3.5 w-3.5" /> Regenerate from water records
                    </button>
                )}
            >
                {(() => {
                    const results = draft.sections.results || { columns: ['NWQS', 'DOE', 'W1', 'W2', 'W3', 'W4'], rows: [] };
                    const rows = results.rows || [];
                    const setRows = (next) => setSection('results', (cur) => ({ ...cur, rows: next }));
                    return (
                        <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
                            <table className="min-w-full divide-y divide-gray-200 text-xs">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-2 py-2 text-left font-semibold text-gray-500">Parameter</th>
                                        {(results.columns || RESULT_PARAM_COLUMNS.map((c) => c.toUpperCase())).map((c) => (
                                            <th key={c} className="px-2 py-2 text-center font-semibold text-gray-500">{c}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {rows.map((row, i) => (
                                        <tr key={i}>
                                            <td className="px-2 py-1.5 text-gray-700">{row.parameter}</td>
                                            {RESULT_PARAM_COLUMNS.map((col) => (
                                                <td key={col} className="px-2 py-1.5">
                                                    <input type="text" disabled={!canEdit} value={row[col] ?? ''} onChange={(e) => { const next = [...rows]; next[i] = { ...next[i], [col]: e.target.value }; setRows(next); }} className="w-16 rounded border border-gray-300 px-1.5 py-1 text-xs" />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    );
                })()}
            </SectionCard>

            {/* 9.0 BMP */}
            <SectionCard title={SECTION_TITLES.bmp} onRegenerate={() => regenerateSection('bmp')} regenerating={regenKey === 'bmp'} canEdit={canEdit}>
                {(() => {
                    const bmp = draft.sections.bmp || {};
                    const items = bmp.items || [];
                    const setItems = (next) => setSection('bmp', (cur) => ({ ...cur, items: next }));
                    const photos = assets.filter((a) => a.kind === 'bmp_photo');
                    return (
                        <div className="space-y-4">
                            <Labeled label="Intro"><textarea rows={2} disabled={!canEdit} value={bmp.intro || ''} onChange={(e) => setSection('bmp', (cur) => ({ ...cur, intro: e.target.value }))} className={fieldClass} /></Labeled>

                            <div>
                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Items</p>
                                <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
                                    <table className="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-2 py-2 text-left font-semibold text-gray-500">No.</th>
                                                <th className="px-2 py-2 text-left font-semibold text-gray-500">Item</th>
                                                <th className="px-2 py-2 text-center font-semibold text-gray-500">Installed</th>
                                                {canEdit && <th></th>}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {items.map((item, i) => (
                                                <tr key={i}>
                                                    <td className="px-2 py-1.5">
                                                        <input type="number" disabled={!canEdit} value={item.no ?? ''} onChange={(e) => { const next = [...items]; next[i] = { ...next[i], no: Number(e.target.value) }; setItems(next); }} className="w-14 rounded border border-gray-300 px-1.5 py-1 text-xs" />
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <input type="text" disabled={!canEdit} value={item.item || ''} onChange={(e) => { const next = [...items]; next[i] = { ...next[i], item: e.target.value }; setItems(next); }} className="w-full rounded border border-gray-300 px-1.5 py-1 text-xs" />
                                                    </td>
                                                    <td className="px-2 py-1.5 text-center">
                                                        <input type="checkbox" disabled={!canEdit} checked={!!item.installed} onChange={(e) => { const next = [...items]; next[i] = { ...next[i], installed: e.target.checked }; setItems(next); }} className="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                    </td>
                                                    {canEdit && (
                                                        <td className="px-2 py-1.5 text-right">
                                                            <button type="button" onClick={() => setItems(items.filter((_, idx) => idx !== i))} className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                                                <HiOutlineTrash className="h-3.5 w-3.5" />
                                                            </button>
                                                        </td>
                                                    )}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {canEdit && (
                                    <button type="button" onClick={() => setItems([...items, { no: items.length + 1, item: '', installed: false }])} className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                                        <HiOutlinePlus className="h-4 w-4" /> Add item
                                    </button>
                                )}
                            </div>

                            <div>
                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Photos</p>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                    {photos.map((photo) => (
                                        <BmpPhotoCard key={photo.id} photo={photo} canEdit={canEdit} onChanged={refreshAssets} />
                                    ))}
                                </div>
                                {canEdit && (
                                    <div className="mt-3">
                                        <AssetUploadSlot label="Add photos" kind="bmp_photo" reportId={report.id} canEdit={canEdit} multiple items={[]} onChanged={refreshAssets} hideList />
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })()}
            </SectionCard>
        </div>
    );
}

function SectionCard({ title, children, onRegenerate, regenerating, canEdit, extraAction }) {
    return (
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-base font-semibold text-gray-900">{title}</h2>
                <div className="flex items-center gap-2">
                    {extraAction}
                    {canEdit && (
                        <button type="button" onClick={onRegenerate} disabled={regenerating} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            <HiOutlineRefresh className="h-3.5 w-3.5" /> {regenerating ? 'Regenerating…' : 'Regenerate'}
                        </button>
                    )}
                </div>
            </div>
            {children}
        </div>
    );
}

function Labeled({ label, children }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {children}
        </div>
    );
}

function ImageSourceCard({ title, sectionKey, overrideKind, projectImageUrl, report, draft, assets, setSection, canEdit, regenerating, onRegenerate, onAssetsChanged, fieldClass }) {
    const data = draft.sections[sectionKey] || { source: 'project', note: '' };
    const overrideAsset = assets.find((a) => a.kind === overrideKind);

    return (
        <SectionCard title={title} onRegenerate={onRegenerate} regenerating={regenerating} canEdit={canEdit}>
            <div className="space-y-3">
                <div className="flex flex-wrap gap-4">
                    <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" disabled={!canEdit} checked={(data.source || 'project') === 'project'} onChange={() => setSection(sectionKey, (cur) => ({ ...cur, source: 'project' }))} className="text-primary-600 focus:ring-primary-500" />
                        Use project image
                    </label>
                    <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" disabled={!canEdit} checked={data.source === 'report'} onChange={() => setSection(sectionKey, (cur) => ({ ...cur, source: 'report' }))} className="text-primary-600 focus:ring-primary-500" />
                        Use report-specific image
                    </label>
                </div>

                {(data.source || 'project') === 'project' ? (
                    projectImageUrl ? (
                        <img src={projectImageUrl} alt={title} className="h-40 w-auto rounded-lg ring-1 ring-gray-200" />
                    ) : (
                        <p className="text-xs text-gray-400">No project image set. Configure it under Project Settings.</p>
                    )
                ) : (
                    <div className="space-y-2">
                        {overrideAsset ? (
                            <div className="flex items-center gap-3">
                                <a href={environmentReportService.assetUrl(overrideAsset.id)} target="_blank" rel="noopener" className="text-sm text-primary-600 hover:underline">{overrideAsset.file_name}</a>
                                {canEdit && (
                                    <button type="button" onClick={async () => { await environmentReportService.removeAsset(overrideAsset.id); onAssetsChanged(); }} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                        <HiOutlineTrash className="h-4 w-4" />
                                    </button>
                                )}
                            </div>
                        ) : (
                            <p className="text-xs text-gray-400">No report-specific image uploaded yet.</p>
                        )}
                        {canEdit && (
                            <AssetUploadSlot label={overrideAsset ? 'Replace' : 'Upload'} kind={overrideKind} reportId={report.id} canEdit={canEdit} items={[]} onChanged={onAssetsChanged} hideList compact />
                        )}
                    </div>
                )}

                <Labeled label="Note">
                    <textarea rows={2} disabled={!canEdit} value={data.note || ''} onChange={(e) => setSection(sectionKey, (cur) => ({ ...cur, note: e.target.value }))} className={fieldClass} />
                </Labeled>
            </div>
        </SectionCard>
    );
}

function AssetUploadSlot({ label, kind, reportId, canEdit, multiple, items, onChanged, hideList, compact }) {
    const inputRef = useRef(null);
    const [busy, setBusy] = useState(false);

    const handlePick = async (e) => {
        const files = Array.from(e.target.files || []);
        e.target.value = '';
        if (!files.length) return;
        setBusy(true);
        try {
            for (const file of files) {
                // eslint-disable-next-line no-await-in-loop
                await environmentReportService.uploadAsset(reportId, kind, file);
            }
            toast.success('Uploaded');
            onChanged();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to upload');
        } finally {
            setBusy(false);
        }
    };

    const removeItem = async (assetId) => {
        try {
            await environmentReportService.removeAsset(assetId);
            toast.success('Removed');
            onChanged();
        } catch {
            toast.error('Failed to remove');
        }
    };

    return (
        <div className={compact ? '' : 'rounded-lg border border-gray-200 p-3'}>
            {!hideList && !compact && <p className="mb-2 text-sm font-medium text-gray-700">{label}</p>}
            {!hideList && (
                <ul className="mb-2 space-y-1">
                    {items.map((a) => (
                        <li key={a.id} className="flex items-center justify-between gap-2 rounded bg-gray-50 px-2 py-1 text-xs text-gray-600">
                            <a href={environmentReportService.assetUrl(a.id)} target="_blank" rel="noopener" className="truncate text-primary-600 hover:underline">{a.file_name}</a>
                            {canEdit && (
                                <button type="button" onClick={() => removeItem(a.id)} className="shrink-0 text-gray-400 hover:text-red-600">
                                    <HiOutlineTrash className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {canEdit && (
                <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                    {busy ? 'Uploading...' : label}
                    <input ref={inputRef} type="file" accept="image/*,.pdf" multiple={multiple} className="hidden" disabled={busy} onChange={handlePick} />
                </label>
            )}
        </div>
    );
}

function BmpPhotoCard({ photo, canEdit, onChanged }) {
    const [caption, setCaption] = useState(photo.caption || '');

    const save = async () => {
        if (caption === (photo.caption || '')) return;
        try {
            await environmentReportService.updateAsset(photo.id, { caption });
            onChanged();
        } catch {
            toast.error('Failed to update caption');
        }
    };

    const remove = async () => {
        try {
            await environmentReportService.removeAsset(photo.id);
            toast.success('Photo removed');
            onChanged();
        } catch {
            toast.error('Failed to remove photo');
        }
    };

    return (
        <div className="overflow-hidden rounded-lg ring-1 ring-gray-200">
            <a href={environmentReportService.assetUrl(photo.id)} target="_blank" rel="noopener">
                <img src={environmentReportService.assetUrl(photo.id)} alt={photo.caption || photo.file_name} className="h-24 w-full object-cover" />
            </a>
            <div className="space-y-1 p-1.5">
                <input
                    type="text"
                    disabled={!canEdit}
                    value={caption}
                    onChange={(e) => setCaption(e.target.value)}
                    onBlur={save}
                    placeholder="Caption"
                    className="w-full rounded border border-gray-200 px-1.5 py-1 text-[11px] disabled:bg-gray-50"
                />
                {canEdit && (
                    <button type="button" onClick={remove} className="flex w-full items-center justify-center gap-1 rounded border border-gray-200 py-1 text-[11px] text-gray-500 hover:bg-red-50 hover:text-red-600">
                        <HiOutlineTrash className="h-3 w-3" /> Remove
                    </button>
                )}
            </div>
        </div>
    );
}
