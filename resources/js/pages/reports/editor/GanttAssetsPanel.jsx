import { useCallback, useEffect, useRef, useState } from 'react';
import toast from 'react-hot-toast';
import { useConfirm } from '@/context/ConfirmContext';
import monthlyReportService from '@/services/monthlyReportService';
import { HiOutlineUpload, HiOutlineTrash, HiOutlineChevronUp, HiOutlineChevronDown, HiOutlineDocument } from 'react-icons/hi';

function fmtSize(bytes) {
    if (bytes === null || bytes === undefined) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

// 2.5 (Gantt chart pages) editor: upload PDF/PNG/JPG pages, reorder, delete.
// Uploading auto-sets section 2.5 `include=true` server-side, so a
// successful upload triggers `onUploaded` to let the shell refetch the
// report and keep the left-nav include checkbox in sync.
export default function GanttAssetsPanel({ reportId, canEdit, onUploaded }) {
    const confirm = useConfirm();
    const fileInputRef = useRef(null);
    const [assets, setAssets] = useState([]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [busyId, setBusyId] = useState(null);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await monthlyReportService.listAssets(reportId);
            setAssets(res.data || []);
        } catch {
            toast.error('Failed to load Gantt pages');
        } finally {
            setLoading(false);
        }
    }, [reportId]);

    useEffect(() => {
        load();
    }, [load]);

    const onPick = () => fileInputRef.current?.click();

    const onFileChange = async (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;
        setUploading(true);
        try {
            await monthlyReportService.uploadAsset(reportId, file, 'gantt_page');
            toast.success('Gantt page uploaded');
            await load();
            onUploaded?.();
        } catch (err) {
            const message = err.response?.data?.errors?.file?.[0] || err.response?.data?.message || 'Upload failed';
            toast.error(message);
        } finally {
            setUploading(false);
        }
    };

    const move = async (idx, dir) => {
        const other = idx + dir;
        if (other < 0 || other >= assets.length) return;
        const a = assets[idx];
        const b = assets[other];
        const next = [...assets];
        [next[idx], next[other]] = [next[other], next[idx]];
        setAssets(next);
        setBusyId(a.id);
        try {
            await Promise.all([
                monthlyReportService.updateAsset(reportId, a.id, { sort_order: b.sort_order }),
                monthlyReportService.updateAsset(reportId, b.id, { sort_order: a.sort_order }),
            ]);
            await load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to reorder');
            await load();
        } finally {
            setBusyId(null);
        }
    };

    const onDelete = async (asset) => {
        if (!(await confirm({
            title: 'Delete this Gantt page?',
            message: `"${asset.file_name}" will be permanently removed.`,
            confirmText: 'Delete',
        }))) return;
        setBusyId(asset.id);
        try {
            await monthlyReportService.deleteAsset(reportId, asset.id);
            toast.success('Gantt page deleted');
            await load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete');
        } finally {
            setBusyId(null);
        }
    };

    return (
        <div className="space-y-4">
            {canEdit && (
                <div>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept=".pdf,.png,.jpg,.jpeg"
                        onChange={onFileChange}
                        className="hidden"
                    />
                    <button
                        type="button"
                        onClick={onPick}
                        disabled={uploading}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                        <HiOutlineUpload className="h-4 w-4" /> {uploading ? 'Uploading…' : 'Upload Gantt page'}
                    </button>
                    <span className="ml-2 text-xs text-gray-400">PDF, PNG or JPG, up to 20 MB</span>
                </div>
            )}

            {loading ? (
                <p className="text-sm text-gray-500">Loading…</p>
            ) : assets.length === 0 ? (
                <p className="text-sm text-gray-500">No Gantt pages uploaded yet.</p>
            ) : (
                <ul className="divide-y divide-gray-100 rounded-lg ring-1 ring-gray-200">
                    {assets.map((a, idx) => (
                        <li key={a.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                            <HiOutlineDocument className="h-4 w-4 shrink-0 text-gray-400" />
                            <span className="min-w-0 flex-1 truncate text-gray-700">
                                {a.file_name}
                                {a.pages ? ` · ${a.pages}p` : ''} · {fmtSize(a.size)}
                            </span>
                            {canEdit && (
                                <div className="flex shrink-0 items-center gap-1">
                                    <button
                                        type="button"
                                        onClick={() => move(idx, -1)}
                                        disabled={idx === 0 || busyId === a.id}
                                        aria-label="Move up"
                                        className="rounded p-1 text-gray-400 hover:bg-gray-100 disabled:opacity-30"
                                    >
                                        <HiOutlineChevronUp className="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => move(idx, 1)}
                                        disabled={idx === assets.length - 1 || busyId === a.id}
                                        aria-label="Move down"
                                        className="rounded p-1 text-gray-400 hover:bg-gray-100 disabled:opacity-30"
                                    >
                                        <HiOutlineChevronDown className="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => onDelete(a)}
                                        disabled={busyId === a.id}
                                        aria-label="Delete"
                                        className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-30"
                                    >
                                        <HiOutlineTrash className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
