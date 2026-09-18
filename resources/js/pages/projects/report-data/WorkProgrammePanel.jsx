import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import reportDataService from '@/services/reportDataService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useConfirm } from '@/context/ConfirmContext';
import { HiOutlinePencil, HiOutlineTrash, HiOutlineEye, HiOutlineUpload, HiOutlineCheckCircle, HiOutlineX } from 'react-icons/hi';
import ImportWizard from './programme/ImportWizard';
import ActivitiesTable from './programme/ActivitiesTable';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

const SOURCE_LABELS = { xlsx: 'Excel', mspdi: 'MS Project XML' };

export default function WorkProgrammePanel({ project, canEdit }) {
    const confirm = useConfirm();
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [versions, setVersions] = useState([]);
    const [importMode, setImportMode] = useState(null); // 'xlsx' | 'mspdi' | null
    const [viewingId, setViewingId] = useState(null);
    const [editForm, setEditForm] = useState(null); // { id, label, status_date }
    const [saving, setSaving] = useState(false);
    const [busyId, setBusyId] = useState(null);

    const load = () => {
        setLoading(true);
        setLoadError(false);
        reportDataService.listProgrammeVersions(project.id)
            .then((res) => setVersions(res.data || []))
            .catch(() => { setLoadError(true); toast.error('Failed to load work programme versions'); })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id]);

    const onImported = (version) => {
        setImportMode(null);
        if (version?.id) setViewingId(version.id);
        load();
    };

    const setCurrent = async (version) => {
        setBusyId(version.id);
        try {
            await reportDataService.updateProgrammeVersion(project.id, version.id, { is_current: true });
            toast.success('Set as current programme');
            load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to set current programme');
        } finally {
            setBusyId(null);
        }
    };

    const openEdit = (version) => setEditForm({ id: version.id, label: version.label || '', status_date: version.status_date || '' });
    const closeEdit = () => setEditForm(null);

    const saveEdit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            await reportDataService.updateProgrammeVersion(project.id, editForm.id, {
                label: editForm.label,
                status_date: editForm.status_date || null,
            });
            toast.success('Programme version updated');
            closeEdit();
            load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update programme version');
        } finally {
            setSaving(false);
        }
    };

    const onDelete = async (version) => {
        if (!(await confirm({ title: 'Delete programme version?', message: `Delete "${version.label}"? This cannot be undone.`, confirmText: 'Delete' }))) return;
        setBusyId(version.id);
        try {
            await reportDataService.deleteProgrammeVersion(project.id, version.id);
            toast.success('Programme version deleted');
            if (viewingId === version.id) setViewingId(null);
            setVersions((rows) => rows.filter((v) => v.id !== version.id));
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete programme version');
        } finally {
            setBusyId(null);
        }
    };

    const viewingVersion = versions.find((v) => v.id === viewingId) || null;

    if (loading) return <LoadingSpinner />;
    if (loadError) {
        return (
            <div className="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">
                <p className="mb-3">Failed to load work programme versions.</p>
                <button type="button" onClick={load} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Retry</button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-semibold text-gray-900">Work Programme</h2>
                <p className="mt-1 text-sm text-gray-500">
                    Imported versions of the MS Project work programme. The "current" version supplies section 2.5 of the monthly report.
                </p>
            </div>

            {canEdit && (
                <div className="flex flex-wrap gap-2">
                    <button type="button" onClick={() => setImportMode('xlsx')} className="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700">
                        <HiOutlineUpload className="h-4 w-4" /> Import Excel
                    </button>
                    <button type="button" onClick={() => setImportMode('mspdi')} className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <HiOutlineUpload className="h-4 w-4" /> Import MS Project XML
                    </button>
                </div>
            )}

            {editForm && (
                <form onSubmit={saveEdit} className="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-gray-900">Rename version</h3>
                        <button type="button" onClick={closeEdit} className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <HiOutlineX className="h-4 w-4" />
                        </button>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Label</label>
                            <input value={editForm.label} onChange={(e) => setEditForm((f) => ({ ...f, label: e.target.value }))} className={input} required />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Status date</label>
                            <input type="date" value={editForm.status_date ? editForm.status_date.slice(0, 10) : ''} onChange={(e) => setEditForm((f) => ({ ...f, status_date: e.target.value }))} className={input} />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={closeEdit} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving…' : 'Save'}</button>
                    </div>
                </form>
            )}

            {versions.length === 0 ? (
                <div className="rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">No work programme has been imported yet.</div>
            ) : (
                <div className="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase text-gray-500">
                                <th className="px-4 py-2">Label</th>
                                <th className="px-4 py-2">Status date</th>
                                <th className="px-4 py-2">Source</th>
                                <th className="px-4 py-2">Activities</th>
                                <th className="px-4 py-2">Imported by</th>
                                <th className="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {versions.map((v) => (
                                <tr key={v.id}>
                                    <td className="px-4 py-2">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-gray-900">{v.label}</span>
                                            {v.is_current && (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                                    <HiOutlineCheckCircle className="h-3.5 w-3.5" /> Current
                                                </span>
                                            )}
                                        </div>
                                        {v.source_file_name && <div className="text-xs text-gray-400">{v.source_file_name}</div>}
                                    </td>
                                    <td className="px-4 py-2">{v.status_date || '-'}</td>
                                    <td className="px-4 py-2">{SOURCE_LABELS[v.source_type] || v.source_type}</td>
                                    <td className="px-4 py-2">{v.activity_count ?? 0}</td>
                                    <td className="px-4 py-2 text-gray-500">
                                        {v.imported_by_name || '-'}
                                        {v.created_at && <div className="text-xs text-gray-400">{v.created_at.slice(0, 10)}</div>}
                                    </td>
                                    <td className="px-4 py-2">
                                        <div className="flex items-center gap-1">
                                            <button type="button" onClick={() => setViewingId(v.id)} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="View" aria-label="View">
                                                <HiOutlineEye className="h-4 w-4" />
                                            </button>
                                            {canEdit && (
                                                <>
                                                    {!v.is_current && (
                                                        <button type="button" onClick={() => setCurrent(v)} disabled={busyId === v.id} className="rounded p-1.5 text-gray-400 hover:bg-green-50 hover:text-green-600 disabled:opacity-40" title="Set current" aria-label="Set current">
                                                            <HiOutlineCheckCircle className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                    <button type="button" onClick={() => openEdit(v)} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Rename / date" aria-label="Rename / date">
                                                        <HiOutlinePencil className="h-4 w-4" />
                                                    </button>
                                                    <button type="button" onClick={() => onDelete(v)} disabled={busyId === v.id} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-40" title="Delete" aria-label="Delete">
                                                        <HiOutlineTrash className="h-4 w-4" />
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {viewingVersion && (
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-gray-900">Activities</h3>
                        <button type="button" onClick={() => setViewingId(null)} className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <HiOutlineX className="h-4 w-4" />
                        </button>
                    </div>
                    <ActivitiesTable projectId={project.id} version={viewingVersion} />
                </div>
            )}

            {importMode && (
                <ImportWizard
                    projectId={project.id}
                    mode={importMode}
                    onClose={() => setImportMode(null)}
                    onImported={onImported}
                />
            )}
        </div>
    );
}
