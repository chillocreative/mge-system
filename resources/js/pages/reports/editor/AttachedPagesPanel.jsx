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

// Generic "attached pages" editor: upload PDF/PNG/JPG pages for a given
// asset `kind`, reorder, delete. Used for 2.5 (Gantt pages) and 2.2/2.4
// (S-curve chart pages) — the generated chart/table is only a fallback for
// the PDF/Word exports when nothing is uploaded here.
//
// Uploading auto-sets the corresponding section's `include=true`
// server-side, so a successful upload triggers `onUploaded` to let the
// caller refetch the report and keep the left-nav include checkbox in
// sync. `onCountChange` (optional) fires after every load — mount, upload,
// reorder, delete — with the current filtered asset count, for callers
// that need to react to the list being empty/non-empty (e.g. ChartSection
// collapsing its generated-chart preview).
export default function AttachedPagesPanel({ reportId, canEdit, kind, title, hint, onUploaded, onCountChange }) {
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
            const filtered = (res.data || []).filter((a) => a.kind === kind);
            setAssets(filtered);
            onCountChange?.(filtered.length);
        } catch {
            toast.error(`Failed to load ${title || 'pages'}`);
        } finally {
            setLoading(false);
        }
    }, [reportId, kind]);

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
            await monthlyReportService.uploadAsset(reportId, file, kind);
            toast.success('Page uploaded');
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
        const next = [...assets];
        [next[idx], next[other]] = [next[other], next[idx]];
        setAssets(next);
        setBusyId(a.id);
        try {
            // Renumber the whole visible list sequentially (1-based) rather
            // than swapping two `sort_order` values — assets can share an
            // equal sort_order (e.g. both default to the same value on
            // upload), in which case a plain swap is a no-op. Only PUT the
            // assets whose order actually changed.
            for (let i = 0; i < next.length; i += 1) {
                const asset = next[i];
                const newOrder = i + 1;
                if (asset.sort_order !== newOrder) {
                    // eslint-disable-next-line no-await-in-loop
                    await monthlyReportService.updateAsset(reportId, asset.id, { sort_order: newOrder });
                }
            }
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
            title: 'Delete this page?',
            message: `"${asset.file_name}" will be permanently removed.`,
            confirmText: 'Delete',
        }))) return;
        setBusyId(asset.id);
        try {
            await monthlyReportService.deleteAsset(reportId, asset.id);
            toast.success('Page deleted');
            await load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete');
        } finally {
            setBusyId(null);
        }
    };

    return (
        <div className="space-y-4">
            {title && <h3 className="text-sm font-semibold text-gray-700">{title}</h3>}

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
                        <HiOutlineUpload className="h-4 w-4" /> {uploading ? 'Uploading…' : 'Upload page'}
                    </button>
                    <span className="ml-2 text-xs text-gray-400">PDF, PNG or JPG, up to 20 MB</span>
                </div>
            )}

            {loading ? (
                <p className="text-sm text-gray-500">Loading…</p>
            ) : assets.length === 0 ? (
                <p className="text-sm text-gray-500">{hint || 'No pages uploaded yet.'}</p>
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
