import { useEffect, useRef, useState } from 'react';
import toast from 'react-hot-toast';
import reportDataService from '@/services/reportDataService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useConfirm } from '@/context/ConfirmContext';
import { HiOutlineTrash, HiOutlineUpload, HiOutlineChevronUp, HiOutlineChevronDown } from 'react-icons/hi';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

const SECTIONS = [
    ['location', 'Location'],
    ['site_access', 'Site Access'],
    ['progress_key_plan', 'Progress Key Plan'],
    ['progress', 'Progress Photos'],
];

export default function ReportImagesPanel({ project, canEdit }) {
    const confirm = useConfirm();
    const [section, setSection] = useState('location');
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [images, setImages] = useState([]);
    const [periods, setPeriods] = useState([]);
    const [uploadLabel, setUploadLabel] = useState('');
    const [uploading, setUploading] = useState(false);
    const [savingId, setSavingId] = useState(null);
    const fileInputRef = useRef(null);

    const load = () => {
        setLoading(true);
        setLoadError(false);
        Promise.all([
            reportDataService.listImages(project.id, { section }),
            reportDataService.listPeriods(project.id),
        ])
            .then(([imagesRes, periodsRes]) => {
                setImages(imagesRes.data || []);
                setPeriods(periodsRes.data || []);
            })
            .catch(() => { setLoadError(true); toast.error('Failed to load images'); })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
        setUploadLabel('');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id, section]);

    const triggerUpload = () => {
        if (section === 'progress' && !uploadLabel.trim()) {
            toast.error('Enter a label before uploading progress photos');
            return;
        }
        fileInputRef.current?.click();
    };

    const onFilesSelected = async (e) => {
        const files = Array.from(e.target.files || []);
        e.target.value = '';
        if (files.length === 0) return;
        setUploading(true);
        let successCount = 0;
        for (const file of files) {
            try {
                const formData = new FormData();
                formData.append('image', file);
                formData.append('section', section);
                if (section === 'progress' && uploadLabel.trim()) formData.append('label', uploadLabel.trim());
                await reportDataService.uploadImage(project.id, formData);
                successCount += 1;
            } catch (err) {
                toast.error(err.response?.data?.message || `Failed to upload ${file.name}`);
            }
        }
        setUploading(false);
        if (successCount > 0) {
            toast.success(`${successCount} image${successCount > 1 ? 's' : ''} uploaded`);
            if (section === 'progress') setUploadLabel('');
            load();
        }
    };

    const updateField = async (img, field, value) => {
        setSavingId(img.id);
        try {
            await reportDataService.updateImage(project.id, img.id, { [field]: value });
            setImages((rows) => rows.map((r) => (r.id === img.id ? { ...r, [field]: value } : r)));
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to update image');
        } finally {
            setSavingId(null);
        }
    };

    const onDelete = async (img) => {
        if (!(await confirm({ title: 'Remove image?', message: `Remove "${img.label || img.file_name}"?` }))) return;
        try {
            await reportDataService.deleteImage(project.id, img.id);
            toast.success('Image removed');
            setImages((rows) => rows.filter((r) => r.id !== img.id));
        } catch {
            toast.error('Failed to remove image');
        }
    };

    const move = async (index, direction) => {
        const other = index + direction;
        if (other < 0 || other >= images.length) return;
        const a = images[index];
        const b = images[other];
        const aOrder = a.sort_order;
        const bOrder = b.sort_order;
        try {
            await Promise.all([
                reportDataService.updateImage(project.id, a.id, { sort_order: bOrder }),
                reportDataService.updateImage(project.id, b.id, { sort_order: aOrder }),
            ]);
            setImages((rows) => {
                const next = [...rows];
                next[index] = { ...a, sort_order: bOrder };
                next[other] = { ...b, sort_order: aOrder };
                next.sort((x, y) => (x.sort_order ?? 0) - (y.sort_order ?? 0));
                return next;
            });
        } catch {
            toast.error('Failed to reorder images');
        }
    };

    return (
        <div className="space-y-4">
            <div>
                <h2 className="text-lg font-semibold text-gray-900">Report Images</h2>
                <p className="mt-1 text-sm text-gray-500">Images used to build the monthly report.</p>
            </div>

            <div className="inline-flex flex-wrap rounded-lg border border-gray-300 bg-white p-1">
                {SECTIONS.map(([v, l]) => (
                    <button
                        key={v}
                        type="button"
                        onClick={() => setSection(v)}
                        className={`rounded-md px-3 py-1.5 text-sm font-medium ${section === v ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50'}`}
                    >
                        {l}
                    </button>
                ))}
            </div>

            {canEdit && (
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div className="flex flex-wrap items-end gap-3">
                        {section === 'progress' && (
                            <div className="w-64">
                                <label className="mb-1 block text-sm font-medium text-gray-700">Label (required)</label>
                                <input value={uploadLabel} onChange={(e) => setUploadLabel(e.target.value)} placeholder="e.g. Aerial 1" className={input} />
                            </div>
                        )}
                        <button type="button" onClick={triggerUpload} disabled={uploading} className="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                            <HiOutlineUpload className="h-4 w-4" /> {uploading ? 'Uploading…' : 'Upload images'}
                        </button>
                        <input ref={fileInputRef} type="file" accept=".png,.jpg,.jpeg,.webp" multiple className="hidden" onChange={onFilesSelected} />
                    </div>
                </div>
            )}

            {loading ? (
                <LoadingSpinner />
            ) : loadError ? (
                <div className="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">
                    <p className="mb-3">Failed to load images.</p>
                    <button type="button" onClick={load} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Retry</button>
                </div>
            ) : images.length === 0 ? (
                <div className="rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">No images uploaded for this section yet.</div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {images.map((img, idx) => (
                        <div key={img.id} className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                            <div className="aspect-video w-full overflow-hidden bg-gray-50">
                                <img
                                    src={`${reportDataService.getImageViewUrl(project.id, img.id)}?v=${encodeURIComponent(img.updated_at || '')}`}
                                    alt={img.label || img.file_name}
                                    className="h-full w-full object-cover"
                                />
                            </div>
                            <div className="space-y-2 p-3">
                                <input
                                    value={img.label || ''}
                                    onChange={(e) => setImages((rows) => rows.map((r) => (r.id === img.id ? { ...r, label: e.target.value } : r)))}
                                    onBlur={(e) => updateField(img, 'label', e.target.value)}
                                    disabled={!canEdit || savingId === img.id}
                                    placeholder="Label"
                                    className={input}
                                />
                                <textarea
                                    value={img.caption || ''}
                                    onChange={(e) => setImages((rows) => rows.map((r) => (r.id === img.id ? { ...r, caption: e.target.value } : r)))}
                                    onBlur={(e) => updateField(img, 'caption', e.target.value)}
                                    disabled={!canEdit || savingId === img.id}
                                    placeholder="Caption"
                                    rows={2}
                                    className={input}
                                />
                                <input
                                    type="date"
                                    value={img.taken_on ? img.taken_on.slice(0, 10) : ''}
                                    onChange={(e) => setImages((rows) => rows.map((r) => (r.id === img.id ? { ...r, taken_on: e.target.value } : r)))}
                                    onBlur={(e) => updateField(img, 'taken_on', e.target.value || null)}
                                    disabled={!canEdit || savingId === img.id}
                                    className={input}
                                />
                                {section === 'progress' && (
                                    <select
                                        value={img.period_id ?? ''}
                                        onChange={(e) => updateField(img, 'period_id', e.target.value ? Number(e.target.value) : null)}
                                        disabled={!canEdit || savingId === img.id}
                                        className={input}
                                    >
                                        <option value="">No period</option>
                                        {periods.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                Period {p.period_no} ({p.period_start?.slice(0, 10)} – {p.period_end?.slice(0, 10)})
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {canEdit && (
                                    <div className="flex items-center justify-between pt-1">
                                        <div className="flex items-center gap-1">
                                            <button type="button" onClick={() => move(idx, -1)} disabled={idx === 0} aria-label="Move up" title="Move up" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-30">
                                                <HiOutlineChevronUp className="h-4 w-4" />
                                            </button>
                                            <button type="button" onClick={() => move(idx, 1)} disabled={idx === images.length - 1} aria-label="Move down" title="Move down" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-30">
                                                <HiOutlineChevronDown className="h-4 w-4" />
                                            </button>
                                        </div>
                                        <button type="button" onClick={() => onDelete(img)} aria-label="Remove" title="Remove" className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                            <HiOutlineTrash className="h-4 w-4" />
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
