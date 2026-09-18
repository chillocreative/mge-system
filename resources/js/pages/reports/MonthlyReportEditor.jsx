import { useCallback, useEffect, useRef, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useConfirm } from '@/context/ConfirmContext';
import usePermission from '@/hooks/usePermission';
import monthlyReportService from '@/services/monthlyReportService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import toast from 'react-hot-toast';
import {
    HiOutlineArrowLeft,
    HiOutlineDownload,
    HiOutlineRefresh,
    HiOutlineSave,
    HiOutlineCheckCircle,
    HiOutlineLockOpen,
    HiOutlineExclamation,
    HiOutlineViewGrid,
} from 'react-icons/hi';
import SECTION_EDITORS, { DEFAULT_LANDSCAPE } from './editor/sectionConfig';
import ValueSection from './editor/ValueSection';
import TableSection from './editor/TableSection';
import TextSection from './editor/TextSection';
import ImageSection from './editor/ImageSection';
import ChartSection from './editor/ChartSection';
import GanttAssetsPanel from './editor/GanttAssetsPanel';
import ReportOptionsDialog from './editor/ReportOptionsDialog';
import SectionNotes from './editor/SectionNotes';
import applyDraft from './editor/applyDraft';
import { snapshotChartPng } from './editor/chartSnapshot';

// Section keys whose chart snapshot gets uploaded as a Word-export asset,
// mapped to the asset `kind` the backend replaces in place.
const CHART_ASSET_KINDS = { '2.2': 'chart_physical_scurve', '2.4': 'chart_financial_scurve' };

const statusColors = {
    draft: 'bg-gray-100 text-gray-600',
    final: 'bg-green-100 text-green-700',
};

function buildSectionDrafts(sections) {
    const drafts = {};
    (sections || []).forEach((s) => {
        drafts[s.key] = { overrides: s.overrides || {}, notes: s.notes || '', include: s.include };
    });
    return drafts;
}

function buildReportDraft(report) {
    const cover = report?.sections?.find((s) => s.key === 'cover');
    return {
        title: report?.title || '',
        month_label: report?.month_label || '',
        evaluation_date: report?.evaluation_date || cover?.merged?.evaluation_date || '',
        signatories: report?.signatories?.length ? report.signatories : (cover?.merged?.signatories || []),
    };
}

export default function MonthlyReportEditor() {
    const { id } = useParams();
    const { can } = usePermission();
    const confirm = useConfirm();
    const canManage = can('reports.manage');

    const [report, setReport] = useState(null);
    const [loading, setLoading] = useState(true);
    const [activeKey, setActiveKey] = useState(null);
    const [sectionDrafts, setSectionDrafts] = useState({});
    const [reportDraft, setReportDraft] = useState({ title: '', month_label: '', evaluation_date: '', signatories: [] });
    const [saving, setSaving] = useState(false);
    const [regeneratingAll, setRegeneratingAll] = useState(false);
    const [regeneratingKey, setRegeneratingKey] = useState(null);
    const [statusBusy, setStatusBusy] = useState(false);
    const [chartVersion, setChartVersion] = useState(0);
    const [showOptions, setShowOptions] = useState(false);
    const [exportingWord, setExportingWord] = useState(false);
    const docxAnchorRef = useRef(null);
    const reportRef = useRef(null);
    useEffect(() => {
        reportRef.current = report;
    }, [report]);

    const applyReport = useCallback((data) => {
        setReport(data);
        setSectionDrafts(buildSectionDrafts(data.sections));
        setReportDraft(buildReportDraft(data));
        setActiveKey((prev) => (data.sections?.some((s) => s.key === prev) ? prev : data.sections?.[0]?.key));
    }, []);

    // Like `applyReport`, but for callers that must not discard unsaved edits
    // sitting in `sectionDrafts` for *other* sections (a silent Gantt-upload
    // refetch, or the Layout dialog's onSaved) — updates `report` from the
    // server response (status/options/sections' include, data, overrides,
    // notes, regenerated_at) while keeping the local draft for any section
    // that is currently dirty, and taking the server value for clean ones
    // (which is how a server-side auto `include=true`, e.g. section 2.5 after
    // a Gantt page upload, still reaches the left nav).
    const applyReportKeepingDrafts = useCallback((data) => {
        const prevReport = reportRef.current;
        setReport(data);
        setSectionDrafts((prevDrafts) => {
            const next = buildSectionDrafts(data.sections);
            (data.sections || []).forEach((s) => {
                const draft = prevDrafts[s.key];
                if (!draft) return;
                const oldServerSection = prevReport?.sections?.find((ps) => ps.key === s.key);
                const dirty = oldServerSection
                    ? JSON.stringify(draft.overrides || {}) !== JSON.stringify(oldServerSection.overrides || {}) ||
                      (draft.notes || '') !== (oldServerSection.notes || '') ||
                      draft.include !== oldServerSection.include
                    : true;
                if (dirty) next[s.key] = draft;
            });
            return next;
        });
        setActiveKey((prev) => (data.sections?.some((s) => s.key === prev) ? prev : data.sections?.[0]?.key));
    }, []);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await monthlyReportService.get(id);
            applyReport(res.data);
        } catch {
            toast.error('Failed to load monthly report');
        } finally {
            setLoading(false);
        }
    }, [id, applyReport]);

    useEffect(() => {
        load();
    }, [load]);

    // Silent refetch (no full-page spinner) used after a Gantt page upload,
    // since the server auto-sets section 2.5 `include=true` and the left
    // nav needs to reflect that without disrupting the rest of the page.
    const refetchQuiet = useCallback(async () => {
        try {
            const res = await monthlyReportService.get(id);
            applyReportKeepingDrafts(res.data);
        } catch {
            toast.error('Failed to refresh report');
        }
    }, [id, applyReportKeepingDrafts]);

    const isFinal = report?.status === 'final';
    const canEdit = canManage && !isFinal;

    const isSectionDirty = (s) => {
        const draft = sectionDrafts[s.key];
        if (!draft) return false;
        return (
            JSON.stringify(draft.overrides || {}) !== JSON.stringify(s.overrides || {}) ||
            (draft.notes || '') !== (s.notes || '') ||
            draft.include !== s.include
        );
    };

    const dirtyKeys = report ? report.sections.filter(isSectionDirty).map((s) => s.key) : [];
    const reportDirty = report ? JSON.stringify(reportDraft) !== JSON.stringify(buildReportDraft(report)) : false;
    const anyDirty = reportDirty || dirtyKeys.length > 0;

    const setSectionOverrides = (key, overrides) => {
        setSectionDrafts((prev) => ({ ...prev, [key]: { ...prev[key], overrides } }));
    };
    const setSectionNotes = (key, notes) => {
        setSectionDrafts((prev) => ({ ...prev, [key]: { ...prev[key], notes } }));
    };
    const setSectionInclude = (key, include) => {
        setSectionDrafts((prev) => ({ ...prev, [key]: { ...prev[key], include } }));
    };
    const setReportField = (field, value) => {
        setReportDraft((prev) => ({ ...prev, [field]: value }));
    };

    // Persists every dirty section (and the report-level fields, if dirty)
    // without reloading/toasting — shared by `saveAll` and by the regenerate
    // actions, which must save unsaved edits first so they aren't discarded
    // by the `applyReport` that follows a regenerate response.
    const persistDirty = async () => {
        if (reportDirty) {
            await monthlyReportService.update(report.id, {
                title: reportDraft.title,
                month_label: reportDraft.month_label,
                evaluation_date: reportDraft.evaluation_date || null,
                signatories: reportDraft.signatories,
            });
        }
        for (const key of dirtyKeys) {
            const draft = sectionDrafts[key];
            // eslint-disable-next-line no-await-in-loop
            await monthlyReportService.saveSection(report.id, key, {
                overrides: draft.overrides,
                notes: draft.notes,
                include: draft.include,
            });
        }
    };

    const saveAll = async () => {
        if (!report) return;
        setSaving(true);
        try {
            await persistDirty();
            const res = await monthlyReportService.get(report.id);
            applyReport(res.data);
            setChartVersion((v) => v + 1);
            toast.success('Report saved');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save report');
        } finally {
            setSaving(false);
        }
    };

    const regenerateAll = async () => {
        const hasDirty = anyDirty;
        const message = hasDirty
            ? 'Unsaved edits will be saved first. Every section’s data will then be rebuilt from source records — saved overrides are kept and re-applied on top.'
            : 'Every section’s data will be rebuilt from source records. Saved overrides are kept and re-applied on top.';
        if (!(await confirm({ title: 'Regenerate all sections?', message, danger: false, confirmText: 'Regenerate' }))) return;
        setRegeneratingAll(true);
        try {
            if (hasDirty) {
                await persistDirty();
            }
            const res = await monthlyReportService.regenerate(report.id);
            applyReport(res.data);
            setChartVersion((v) => v + 1);
            toast.success('Report regenerated');
        } catch (err) {
            toast.error(err.response?.data?.message || (hasDirty ? 'Failed to save changes before regenerating' : 'Failed to regenerate report'));
        } finally {
            setRegeneratingAll(false);
        }
    };

    const regenerateSection = async (section) => {
        const hasOverrides = section.overrides && Object.keys(section.overrides).length > 0;
        const hasDirty = anyDirty;
        let message;
        if (hasDirty && hasOverrides) {
            message = 'Unsaved edits will be saved first. This section’s data will then be rebuilt from source records — your saved overrides are kept and re-applied on top.';
        } else if (hasDirty) {
            message = 'Unsaved edits will be saved first, then this section’s data will be rebuilt from source records.';
        } else {
            message = 'This section’s data will be rebuilt from source records. Your saved overrides are kept and re-applied on top.';
        }
        if ((hasOverrides || hasDirty) && !(await confirm({ title: 'Regenerate this section?', message, danger: false, confirmText: 'Regenerate' }))) return;
        setRegeneratingKey(section.key);
        try {
            if (hasDirty) {
                await persistDirty();
            }
            const res = await monthlyReportService.regenerate(report.id, section.key);
            applyReport(res.data);
            setChartVersion((v) => v + 1);
            toast.success(`${section.title} regenerated`);
        } catch (err) {
            toast.error(err.response?.data?.message || (hasDirty ? 'Failed to save changes before regenerating' : 'Failed to regenerate section'));
        } finally {
            setRegeneratingKey(null);
        }
    };

    const finalise = async () => {
        if (!(await confirm({
            title: 'Finalise report?',
            message: 'Finalised reports cannot be edited unless reopened. Continue?',
            confirmText: 'Finalise',
        }))) return;
        setStatusBusy(true);
        try {
            const res = await monthlyReportService.finalise(report.id);
            applyReport(res.data);
            toast.success('Report finalised');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to finalise report');
        } finally {
            setStatusBusy(false);
        }
    };

    const reopen = async () => {
        if (!(await confirm({
            title: 'Reopen report?',
            message: 'This report will become editable again.',
            danger: false,
            confirmText: 'Reopen',
        }))) return;
        setStatusBusy(true);
        try {
            const res = await monthlyReportService.reopen(report.id);
            applyReport(res.data);
            toast.success('Report reopened');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to reopen report');
        } finally {
            setStatusBusy(false);
        }
    };

    const openDocx = () => {
        const anchor = docxAnchorRef.current;
        if (!anchor || !report) return;
        // Same-origin download: the API answers with Content-Disposition: attachment, so a
        // plain `download` anchor works even after the awaited snapshot uploads have used up
        // the browser's transient user-activation window (a target="_blank" click would be
        // blocked as a pop-up).
        anchor.href = monthlyReportService.getDocxUrl(report.id);
        anchor.setAttribute('download', '');
        anchor.click();
    };

    const exportWord = async () => {
        if (!canManage || isFinal) {
            openDocx();
            return;
        }
        setExportingWord(true);
        const toastId = toast.loading('Preparing charts…');
        try {
            let anyFailed = false;
            for (const key of Object.keys(CHART_ASSET_KINDS)) {
                const section = report.sections.find((s) => s.key === key);
                if (!section?.include) continue;
                try {
                    // eslint-disable-next-line no-await-in-loop
                    const blob = await snapshotChartPng(monthlyReportService.getChartUrl(report.id, key, chartVersion));
                    // eslint-disable-next-line no-await-in-loop
                    await monthlyReportService.uploadAsset(report.id, new File([blob], `chart-${key}.png`, { type: 'image/png' }), CHART_ASSET_KINDS[key]);
                } catch {
                    anyFailed = true;
                }
            }
            if (anyFailed) {
                toast.error('Charts could not be captured — the Word file will contain the data tables instead');
            }
        } finally {
            toast.dismiss(toastId);
            setExportingWord(false);
        }
        openDocx();
    };

    if (loading) return <LoadingSpinner />;
    if (!report) {
        return (
            <div className="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">
                Monthly report not found.
            </div>
        );
    }

    const activeSection = report.sections.find((s) => s.key === activeKey) || report.sections[0];
    const config = SECTION_EDITORS[activeSection.key] || { type: 'text' };
    const draft = sectionDrafts[activeSection.key] || { overrides: {}, notes: '', include: activeSection.include };
    // Seed editors from `merged` with the in-progress draft overrides layered
    // on top (not raw `merged`), so switching sections and back — which
    // remounts the editor via the `key` below — doesn't visually revert
    // unsaved edits, and the next edit doesn't push stale data back into
    // `sectionDrafts`. See applyDraft.js.
    const effectiveMerged = applyDraft(activeSection.merged, draft.overrides);

    const renderEditor = () => {
        const onOverridesChange = (patch) => setSectionOverrides(activeSection.key, patch);
        switch (config.type) {
            case 'value':
                return (
                    <ValueSection
                        config={config}
                        merged={effectiveMerged}
                        canEdit={canEdit}
                        onOverridesChange={onOverridesChange}
                        reportDraft={reportDraft}
                        onReportFieldChange={setReportField}
                    />
                );
            case 'text':
                return <TextSection data={effectiveMerged} />;
            case 'image':
                return (
                    <ImageSection
                        config={config}
                        projectId={report.project_id}
                        merged={effectiveMerged}
                        canEdit={canEdit}
                        onOverridesChange={onOverridesChange}
                    />
                );
            case 'chart':
                return (
                    <ChartSection
                        config={config}
                        reportId={report.id}
                        sectionKey={activeSection.key}
                        merged={effectiveMerged}
                        canEdit={canEdit}
                        onOverridesChange={onOverridesChange}
                        version={chartVersion}
                    />
                );
            case 'gantt':
                return (
                    <div className="space-y-4">
                        <TextSection data={effectiveMerged} />
                        <GanttAssetsPanel
                            reportId={report.id}
                            canEdit={canEdit}
                            onUploaded={refetchQuiet}
                        />
                    </div>
                );
            default:
                return (
                    <TableSection
                        config={config}
                        data={activeSection.data}
                        merged={effectiveMerged}
                        canEdit={canEdit}
                        onOverridesChange={onOverridesChange}
                    />
                );
        }
    };

    return (
        <div>
            <Link to="/projects/monthly-reports" className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <HiOutlineArrowLeft className="h-4 w-4" /> Back to Monthly Reports
            </Link>

            <div className="mb-6 flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-xl font-bold text-gray-900">
                            {report.project?.name} {report.project?.code ? `(${report.project.code})` : ''}
                        </h1>
                        <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[report.status] || 'bg-gray-100 text-gray-600'}`}>
                            {report.status}
                        </span>
                    </div>
                    <p className="mt-1 text-sm text-gray-500">
                        Report No. {report.report_no} &middot; {report.title || report.month_label}
                        {(report.period?.period_start || report.period?.period_end) && (
                            <> &middot; {formatDate(report.period?.period_start)} - {formatDate(report.period?.period_end)}</>
                        )}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {canEdit && (
                        <>
                            <button
                                type="button"
                                onClick={saveAll}
                                disabled={saving || !anyDirty}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                            >
                                <HiOutlineSave className="h-4 w-4" /> {saving ? 'Saving…' : 'Save all'}
                            </button>
                            <button
                                type="button"
                                onClick={regenerateAll}
                                disabled={regeneratingAll}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            >
                                <HiOutlineRefresh className="h-4 w-4" /> {regeneratingAll ? 'Regenerating…' : 'Regenerate all'}
                            </button>
                        </>
                    )}
                    {canManage && !isFinal && (
                        <button
                            type="button"
                            onClick={() => setShowOptions(true)}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <HiOutlineViewGrid className="h-4 w-4" /> Layout
                        </button>
                    )}
                    <a
                        href={monthlyReportService.getPdfUrl(report.id)}
                        target="_blank"
                        rel="noopener"
                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <HiOutlineDownload className="h-4 w-4" /> Export PDF
                    </a>
                    <a ref={docxAnchorRef} download className="hidden" aria-hidden="true" />
                    <button
                        type="button"
                        onClick={exportWord}
                        disabled={exportingWord}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                        <HiOutlineDownload className="h-4 w-4" /> {exportingWord ? 'Preparing…' : 'Export Word'}
                    </button>
                    {canManage && !isFinal && (
                        <button
                            type="button"
                            onClick={finalise}
                            disabled={statusBusy}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                        >
                            <HiOutlineCheckCircle className="h-4 w-4" /> Finalise
                        </button>
                    )}
                    {canManage && isFinal && (
                        <button
                            type="button"
                            onClick={reopen}
                            disabled={statusBusy}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-100 disabled:opacity-50"
                        >
                            <HiOutlineLockOpen className="h-4 w-4" /> Reopen
                        </button>
                    )}
                </div>
            </div>

            {isFinal && (
                <div className="mb-6 flex items-center gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                    <HiOutlineExclamation className="h-5 w-5 shrink-0" />
                    This report is finalised and read-only. Reopen it to make changes.
                </div>
            )}
            {!canManage && (
                <div className="mb-6 flex items-center gap-2 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 ring-1 ring-gray-200">
                    <HiOutlineExclamation className="h-5 w-5 shrink-0" />
                    You have view-only access to this report.
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-[240px_1fr]">
                <nav className="space-y-1 rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-200 lg:self-start">
                    {report.sections.map((s) => {
                        const d = sectionDrafts[s.key] || { overrides: {}, notes: '', include: s.include };
                        const edited = Object.keys(d.overrides || {}).length > 0;
                        const hasNotes = !!(d.notes && d.notes.trim());
                        const isCover = s.key === 'cover';
                        const isLandscape = (report.options?.landscape_sections ?? DEFAULT_LANDSCAPE).includes(s.key);
                        return (
                            <div key={s.key} className={`flex items-center gap-2 rounded-lg px-2 py-1.5 ${activeKey === s.key ? 'bg-primary-50' : 'hover:bg-gray-50'}`}>
                                <input
                                    type="checkbox"
                                    checked={isCover ? true : !!d.include}
                                    onChange={(e) => setSectionInclude(s.key, e.target.checked)}
                                    disabled={!canEdit || isCover}
                                    title={isCover ? 'The cover section is always included in the PDF' : 'Include in PDF'}
                                    className="rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                />
                                <button
                                    type="button"
                                    onClick={() => setActiveKey(s.key)}
                                    className={`min-w-0 flex-1 truncate text-left text-sm ${activeKey === s.key ? 'font-semibold text-primary-700' : 'text-gray-600'}`}
                                >
                                    {s.title}
                                </button>
                                <div className="flex shrink-0 items-center gap-1">
                                    {isLandscape && <span className="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500" title="Prints landscape">L</span>}
                                    {edited && <span className="rounded-full bg-blue-100 px-1.5 py-0.5 text-[10px] font-medium text-blue-700" title="Has overrides">edited</span>}
                                    {hasNotes && <span className="rounded-full bg-purple-100 px-1.5 py-0.5 text-[10px] font-medium text-purple-700" title="Has notes">notes</span>}
                                </div>
                            </div>
                        );
                    })}
                </nav>

                <div className="min-w-0 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-6">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-lg font-semibold text-gray-900">{activeSection.title}</h2>
                        {canEdit && (
                            <button
                                type="button"
                                onClick={() => regenerateSection(activeSection)}
                                disabled={regeneratingKey === activeSection.key}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            >
                                <HiOutlineRefresh className="h-3.5 w-3.5" />
                                {regeneratingKey === activeSection.key ? 'Regenerating…' : 'Regenerate this section'}
                            </button>
                        )}
                    </div>

                    <div key={`${activeSection.id}-${activeSection.regenerated_at}`}>
                        {renderEditor()}
                        <SectionNotes
                            value={draft.notes}
                            onChange={(notes) => setSectionNotes(activeSection.key, notes)}
                            disabled={!canEdit}
                        />
                    </div>
                </div>
            </div>

            {showOptions && (
                <ReportOptionsDialog
                    report={report}
                    onClose={() => setShowOptions(false)}
                    onSaved={(data) => {
                        applyReportKeepingDrafts(data);
                        setShowOptions(false);
                    }}
                />
            )}
        </div>
    );
}
