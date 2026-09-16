import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { useConfirm } from '@/context/ConfirmContext';
import contractService from '@/services/contractService';
import drawingService from '@/services/drawingService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import useDragScroll from '@/hooks/useDragScroll';
import toast from 'react-hot-toast';
import {
    HiOutlineArrowLeft,
    HiOutlinePencil,
    HiOutlineX,
    HiOutlineUser,
    HiOutlineMail,
    HiOutlinePhone,
    HiOutlineCalendar,
    HiOutlineUpload,
    HiOutlineDocumentText,
    HiOutlineDownload,
    HiOutlineEye,
    HiOutlineTrash,
    HiOutlinePlus,
    HiOutlineDocumentDownload,
    HiOutlineFolderOpen,
    HiOutlineClipboardList,
} from 'react-icons/hi';

const statusColors = {
    active: 'bg-green-100 text-green-700',
    completed: 'bg-blue-100 text-blue-700',
    terminated: 'bg-red-100 text-red-700',
};
const statuses = ['active', 'completed', 'terminated'];

function formatCurrency(val) {
    if (val === null || val === undefined || val === '') return '-';
    return 'RM ' + Number(val).toLocaleString('en-MY', { minimumFractionDigits: 2 });
}

const tabs = [
    { id: 'documents', label: 'Documents', icon: HiOutlineDocumentDownload },
    { id: 'drawings', label: 'Drawings', icon: HiOutlineFolderOpen },
    { id: 'boq', label: 'Bill of Quantity (BQ)', icon: HiOutlineClipboardList },
];

const emptyPic = () => ({ name: '', email: '', phone: '', company: '', designation: '' });

export default function ContractDetail() {
    const { id } = useParams();
    const { can } = useAuth();
    const canEdit = can('projects.edit');
    const [contract, setContract] = useState(null);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('documents');
    const [showEdit, setShowEdit] = useState(false);
    const dragScrollRef = useDragScroll();

    const fetchContract = useCallback(async () => {
        try {
            const res = await contractService.get(id);
            setContract(res.data);
        } catch {
            setContract(null);
        } finally {
            setLoading(false);
        }
    }, [id]);

    useEffect(() => { fetchContract(); }, [fetchContract]);

    if (loading) return <LoadingSpinner />;
    if (!contract) {
        return (
            <div className="py-12 text-center text-gray-500">
                Contract not found.{' '}
                <Link to="/projects/contracts" className="text-primary-600 hover:underline">Back to contracts</Link>
            </div>
        );
    }

    return (
        <div>
            <div className="mb-6">
                <Link to="/projects/contracts" className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <HiOutlineArrowLeft className="h-4 w-4" /> Back to Contracts
                </Link>
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">{contract.title}</h1>
                        <p className="text-sm text-gray-500">{contract.project?.name || '-'}{contract.contract_no ? ` · ${contract.contract_no}` : ''}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className={`rounded-full px-3 py-1 text-sm font-medium ${statusColors[contract.status] || 'bg-gray-100 text-gray-600'}`}>
                            {contract.status}
                        </span>
                        {canEdit && (
                            <button
                                onClick={() => setShowEdit(true)}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                <HiOutlinePencil className="h-4 w-4" /> Edit
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {/* Overview */}
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p className="text-xs font-medium text-gray-400">Contract Value</p>
                    <p className="mt-1 text-sm font-semibold text-gray-900">{formatCurrency(contract.contract_value)}</p>
                </div>
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p className="text-xs font-medium text-gray-400">Start Date</p>
                    <p className="mt-1 flex items-center gap-1 text-sm text-gray-900">
                        <HiOutlineCalendar className="h-4 w-4 text-gray-400" />
                        {formatDate(contract.start_date)}
                    </p>
                </div>
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p className="text-xs font-medium text-gray-400">End Date</p>
                    <p className="mt-1 flex items-center gap-1 text-sm text-gray-900">
                        <HiOutlineCalendar className="h-4 w-4 text-gray-400" />
                        {formatDate(contract.end_date)}
                    </p>
                </div>
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p className="text-xs font-medium text-gray-400">Correspondence PICs</p>
                    <p className="mt-1 text-sm text-gray-900">{contract.pics?.length || 0} recorded</p>
                </div>
            </div>

            {(contract.pics?.length > 0 || contract.notes) && (
                <div className="mb-6 grid gap-4 sm:grid-cols-2">
                    {contract.pics?.length > 0 && (
                        <div className="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p className="mb-3 text-xs font-semibold uppercase text-gray-500">Correspondence PICs</p>
                            <div className="space-y-2">
                                {contract.pics.map((p) => (
                                    <div key={p.id} className="rounded-lg border border-gray-200 bg-white p-3 text-sm text-gray-700">
                                        <p className="flex items-center gap-2 font-semibold text-gray-900">
                                            <HiOutlineUser className="h-4 w-4 text-gray-400" />
                                            {p.name}{p.designation ? <span className="font-normal text-gray-500"> — {p.designation}</span> : ''}
                                        </p>
                                        {p.company && <p className="mt-0.5 pl-6 text-xs text-gray-500">{p.company}</p>}
                                        <div className="mt-1 space-y-0.5 pl-6">
                                            {p.email && <p className="flex items-center gap-2"><HiOutlineMail className="h-4 w-4 text-gray-400" />{p.email}</p>}
                                            {p.phone && <p className="flex items-center gap-2"><HiOutlinePhone className="h-4 w-4 text-gray-400" />{p.phone}</p>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                    {contract.notes && (
                        <div className="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p className="mb-1 text-xs font-semibold uppercase text-gray-500">Notes</p>
                            <p className="whitespace-pre-line text-sm text-gray-700">{contract.notes}</p>
                        </div>
                    )}
                </div>
            )}

            {/* Tabs */}
            <div className="mb-6 border-b border-gray-200">
                <nav ref={dragScrollRef} className="-mb-px flex gap-6 overflow-x-auto cursor-grab active:cursor-grabbing">
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`flex items-center gap-1.5 whitespace-nowrap border-b-2 py-3 text-sm font-medium transition-colors ${
                                activeTab === tab.id
                                    ? 'border-primary-500 text-primary-600'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                            }`}
                        >
                            <tab.icon className="h-4 w-4" />
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>

            {activeTab === 'documents' && <DocumentsTab contract={contract} canEdit={canEdit} onRefresh={fetchContract} />}
            {activeTab === 'drawings' && <DrawingsTab contract={contract} canEdit={canEdit} />}
            {activeTab === 'boq' && <BoqTab contract={contract} canEdit={canEdit} onContractChange={setContract} />}

            {showEdit && (
                <EditContractModal
                    contract={contract}
                    onClose={() => setShowEdit(false)}
                    onSaved={() => { setShowEdit(false); fetchContract(); }}
                />
            )}
        </div>
    );
}

// ─── Documents Tab ──────────────────────────────────────────────
function DocumentsTab({ contract, canEdit, onRefresh }) {
    const confirm = useConfirm();
    const [uploading, setUploading] = useState(false);

    const handleUpload = async (e) => {
        const files = Array.from(e.target.files || []);
        if (!files.length) return;
        setUploading(true);
        try {
            const fd = new FormData();
            files.forEach((f) => fd.append('files[]', f));
            await contractService.uploadFiles(contract.id, fd);
            toast.success('Document(s) uploaded');
            onRefresh();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Upload failed');
        } finally {
            setUploading(false);
            e.target.value = '';
        }
    };

    const handleDelete = async (fileId) => {
        if (!(await confirm({ message: 'Delete this document?' }))) return;
        try {
            await contractService.deleteFile(fileId);
            toast.success('Document deleted');
            onRefresh();
        } catch {
            toast.error('Failed to delete');
        }
    };

    const files = contract.files || [];

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900">Documents</h2>
                {canEdit && (
                    <label className="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700">
                        <HiOutlineUpload className="h-4 w-4" /> {uploading ? 'Uploading...' : 'Upload'}
                        <input type="file" multiple disabled={uploading} onChange={handleUpload} className="hidden" />
                    </label>
                )}
            </div>
            {files.length ? (
                <ul className="divide-y divide-gray-100 rounded-lg border border-gray-100">
                    {files.map((f) => (
                        <li key={f.id} className="flex items-center justify-between px-3 py-2">
                            <span className="flex min-w-0 items-center gap-2 text-sm text-gray-700">
                                <HiOutlineDocumentText className="h-4 w-4 flex-shrink-0 text-gray-400" />
                                <span className="truncate">{f.file_name}</span>
                            </span>
                            <span className="flex shrink-0 items-center gap-1">
                                <a href={contractService.getFileDownloadUrl(f.id)} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Download">
                                    <HiOutlineDownload className="h-4 w-4" />
                                </a>
                                {canEdit && (
                                    <button onClick={() => handleDelete(f.id)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                        <HiOutlineTrash className="h-4 w-4" />
                                    </button>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-gray-400">No documents uploaded</p>
            )}
        </div>
    );
}

// ─── Drawings Tab (bulk / folder upload on the shared attachments engine) ──
function formatSize(bytes) {
    if (!bytes) return '';
    const mb = bytes / (1024 * 1024);
    return mb >= 1 ? `${mb.toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function DrawingsTab({ contract, canEdit }) {
    const confirm = useConfirm();
    const [drawings, setDrawings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState(null);
    const folderInput = useRef(null);
    const fileInput = useRef(null);

    const fetchDrawings = useCallback(async () => {
        setLoading(true);
        try {
            const res = await contractService.listDrawings(contract.id);
            setDrawings(res.data || []);
        } catch {
            setDrawings([]);
        } finally {
            setLoading(false);
        }
    }, [contract.id]);

    useEffect(() => { fetchDrawings(); }, [fetchDrawings]);

    const upload = async (fileList) => {
        const files = Array.from(fileList || []);
        if (!files.length) return;

        setUploading(true);
        setProgress({ done: 0, total: files.length });

        // Upload in batches so a whole folder does not go up in one giant
        // request the server would reject; each batch carries its files' relative
        // folder paths (webkitRelativePath) so structure is preserved.
        const BATCH = 10;
        let uploaded = 0;
        let skipped = 0;
        try {
            for (let i = 0; i < files.length; i += BATCH) {
                const slice = files.slice(i, i + BATCH);
                const fd = new FormData();
                slice.forEach((f) => {
                    fd.append('files[]', f);
                    fd.append('paths[]', f.webkitRelativePath || f.name);
                });
                const res = await contractService.uploadDrawings(contract.id, fd);
                uploaded += res.data?.uploaded ?? 0;
                skipped += res.data?.skipped ?? 0;
                setProgress({ done: Math.min(i + BATCH, files.length), total: files.length });
            }
            toast.success(`Uploaded ${uploaded} file${uploaded === 1 ? '' : 's'}${skipped ? `, skipped ${skipped} duplicate${skipped === 1 ? '' : 's'}` : ''}`);
            fetchDrawings();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Upload failed');
        } finally {
            setUploading(false);
            setProgress(null);
            if (folderInput.current) folderInput.current.value = '';
            if (fileInput.current) fileInput.current.value = '';
        }
    };

    const handleDelete = async (id) => {
        if (!(await confirm({ message: 'Delete this drawing?' }))) return;
        try {
            await contractService.deleteDrawing(id);
            toast.success('Drawing deleted');
            fetchDrawings();
        } catch {
            toast.error('Failed to delete');
        }
    };

    // Group by folder for display.
    const groups = drawings.reduce((acc, d) => {
        const key = d.folder || '(root)';
        (acc[key] = acc[key] || []).push(d);
        return acc;
    }, {});

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold text-gray-900">Drawings</h2>
                {canEdit && (
                    <div className="flex items-center gap-2">
                        <input
                            ref={folderInput}
                            type="file"
                            webkitdirectory=""
                            directory=""
                            multiple
                            hidden
                            onChange={(e) => upload(e.target.files)}
                        />
                        <input
                            ref={fileInput}
                            type="file"
                            multiple
                            hidden
                            onChange={(e) => upload(e.target.files)}
                        />
                        <button
                            onClick={() => fileInput.current?.click()}
                            disabled={uploading}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                        >
                            <HiOutlinePlus className="h-4 w-4" /> Add Files
                        </button>
                        <button
                            onClick={() => folderInput.current?.click()}
                            disabled={uploading}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        >
                            <HiOutlineFolderOpen className="h-4 w-4" /> Upload Folder
                        </button>
                    </div>
                )}
            </div>

            {uploading && progress && (
                <div className="mb-4">
                    <div className="mb-1 flex justify-between text-xs text-gray-500">
                        <span>Uploading…</span>
                        <span>{progress.done} / {progress.total}</span>
                    </div>
                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                        <div className="h-full bg-primary-500 transition-all" style={{ width: `${(progress.done / progress.total) * 100}%` }} />
                    </div>
                </div>
            )}

            {loading ? (
                <LoadingSpinner />
            ) : drawings.length ? (
                <div className="space-y-4">
                    {Object.keys(groups).sort().map((folder) => (
                        <div key={folder}>
                            <p className="mb-1 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                <HiOutlineFolderOpen className="h-3.5 w-3.5" /> {folder}
                            </p>
                            <ul className="divide-y divide-gray-100 rounded-lg border border-gray-100">
                                {groups[folder].map((d) => (
                                    <li key={d.id} className="flex items-center justify-between px-3 py-2">
                                        <span className="flex min-w-0 items-center gap-2 text-sm text-gray-700">
                                            <HiOutlineDocumentText className="h-4 w-4 flex-shrink-0 text-gray-400" />
                                            <span className="truncate">{d.name}</span>
                                            {d.size ? <span className="shrink-0 text-xs text-gray-400">{formatSize(d.size)}</span> : null}
                                        </span>
                                        <span className="flex shrink-0 items-center gap-1">
                                            <a href={contractService.getDrawingDownloadUrl(d.id)} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Download">
                                                <HiOutlineDownload className="h-4 w-4" />
                                            </a>
                                            {canEdit && (
                                                <button onClick={() => handleDelete(d.id)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                                    <HiOutlineTrash className="h-4 w-4" />
                                                </button>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-sm text-gray-400">No drawings for this contract. Use “Upload Folder” to add a whole folder at once.</p>
            )}
        </div>
    );
}

// ─── Bill of Quantity Tab ──────────────────────────────────────────────
function BoqTab({ contract, canEdit, onContractChange }) {
    const confirm = useConfirm();
    const bqFileInput = useRef(null);
    const [uploading, setUploading] = useState(false);

    const handleUpload = async (file) => {
        if (!file) return;
        setUploading(true);
        try {
            const fd = new FormData();
            fd.append('file', file);
            const res = await contractService.uploadBqFile(contract.id, fd);
            onContractChange(res.data);
            toast.success('BQ document uploaded');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Upload failed');
        } finally {
            setUploading(false);
            if (bqFileInput.current) bqFileInput.current.value = '';
        }
    };

    const handleRemove = async () => {
        if (!(await confirm({ message: 'Remove the BQ document?', confirmText: 'Remove' }))) return;
        try {
            await contractService.deleteBqFile(contract.id);
            onContractChange({ ...contract, bq_file_path: null, bq_file_name: null });
            toast.success('BQ document removed');
        } catch {
            toast.error('Failed to remove BQ document');
        }
    };

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Bill of Quantity (BQ)</h2>

            <input
                ref={bqFileInput}
                type="file"
                accept=".pdf,.xls,.xlsx,.doc,.docx"
                hidden
                onChange={(e) => handleUpload(e.target.files?.[0])}
            />

            {contract.bq_file_name ? (
                <div className="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3">
                    <span className="flex items-center gap-2 text-sm text-gray-700">
                        <HiOutlineDocumentText className="h-4 w-4 text-gray-400" />
                        <span>{contract.bq_file_name}</span>
                    </span>
                    <div className="flex items-center gap-2">
                        <a
                            href={contractService.getBqFileUrl(contract.id)}
                            target="_blank"
                            rel="noopener"
                            className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <HiOutlineEye className="h-4 w-4" /> View BQ
                        </a>
                        {canEdit && (
                            <>
                                <button
                                    onClick={() => bqFileInput.current?.click()}
                                    disabled={uploading}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    <HiOutlineUpload className="h-4 w-4" /> {uploading ? 'Uploading…' : 'Replace'}
                                </button>
                                <button
                                    onClick={handleRemove}
                                    className="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                    title="Remove BQ"
                                >
                                    <HiOutlineTrash className="h-4 w-4" />
                                </button>
                            </>
                        )}
                    </div>
                </div>
            ) : (
                <div className="text-center py-8">
                    <p className="text-gray-400">No BQ document uploaded yet</p>
                    {canEdit && (
                        <button
                            onClick={() => bqFileInput.current?.click()}
                            disabled={uploading}
                            className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        >
                            <HiOutlineUpload className="h-4 w-4" /> {uploading ? 'Uploading…' : 'Upload BQ'}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

// ─── Edit Contract Modal ──────────────────────────────────────────────
function EditContractModal({ contract, onClose, onSaved }) {
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({
        title: contract.title || '',
        contract_no: contract.contract_no || '',
        contract_value: contract.contract_value ?? '',
        start_date: contract.start_date ? String(contract.start_date).split('T')[0] : '',
        end_date: contract.end_date ? String(contract.end_date).split('T')[0] : '',
        status: contract.status || 'active',
        notes: contract.notes || '',
        pics: contract.pics?.length
            ? contract.pics.map((p) => ({ name: p.name || '', email: p.email || '', phone: p.phone || '', company: p.company || '', designation: p.designation || '' }))
            : [emptyPic()],
    });

    const addPic = () => setForm((p) => ({ ...p, pics: [...p.pics, emptyPic()] }));
    const removePic = (idx) => setForm((p) => ({ ...p, pics: p.pics.filter((_, i) => i !== idx) }));
    const updatePic = (idx, field, value) => setForm((p) => ({ ...p, pics: p.pics.map((pic, i) => i === idx ? { ...pic, [field]: value } : pic) }));

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const fd = new FormData();
            fd.append('project_id', contract.project_id);
            fd.append('title', form.title);
            fd.append('status', form.status);
            if (form.contract_no) fd.append('contract_no', form.contract_no);
            if (form.contract_value !== '') fd.append('contract_value', form.contract_value);
            if (form.start_date) fd.append('start_date', form.start_date);
            if (form.end_date) fd.append('end_date', form.end_date);
            if (form.notes) fd.append('notes', form.notes);
            fd.append('pics_sync', '1');
            form.pics.filter((p) => p.name?.trim()).forEach((pic, i) => {
                fd.append(`pics[${i}][name]`, pic.name);
                if (pic.email) fd.append(`pics[${i}][email]`, pic.email);
                if (pic.phone) fd.append(`pics[${i}][phone]`, pic.phone);
                if (pic.company) fd.append(`pics[${i}][company]`, pic.company);
                if (pic.designation) fd.append(`pics[${i}][designation]`, pic.designation);
            });
            await contractService.update(contract.id, fd);
            toast.success('Contract updated');
            onSaved();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save contract');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-gray-900">Edit Contract</h3>
                    <button onClick={onClose} className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                        <HiOutlineX className="h-5 w-5" />
                    </button>
                </div>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Title *</label>
                            <input type="text" value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Contract No</label>
                            <input type="text" value={form.contract_no} onChange={(e) => setForm((p) => ({ ...p, contract_no: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Contract Value (RM)</label>
                            <input type="number" min="0" step="0.01" value={form.contract_value} onChange={(e) => setForm((p) => ({ ...p, contract_value: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Start Date</label>
                            <input type="date" value={form.start_date} onChange={(e) => setForm((p) => ({ ...p, start_date: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">End Date</label>
                            <input type="date" value={form.end_date} onChange={(e) => setForm((p) => ({ ...p, end_date: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        </div>
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                        <select value={form.status} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            {statuses.map((s) => <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>)}
                        </select>
                    </div>

                    <div className="rounded-lg border border-gray-100 bg-gray-50 p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <p className="text-xs font-semibold uppercase text-gray-500">Correspondence PICs</p>
                            <button type="button" onClick={addPic} className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                                <HiOutlinePlus className="h-4 w-4" /> Add PIC
                            </button>
                        </div>
                        <div className="space-y-3">
                            {form.pics.map((pic, i) => (
                                <div key={i} className="rounded-lg border border-gray-200 bg-white p-3">
                                    <div className="mb-2 flex items-center justify-between">
                                        <span className="text-xs font-semibold text-gray-400">PIC #{i + 1}</span>
                                        {form.pics.length > 1 && (
                                            <button type="button" onClick={() => removePic(i)} className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove PIC">
                                                <HiOutlineX className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-600">Name</label>
                                            <input type="text" value={pic.name} onChange={(e) => updatePic(i, 'name', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-600">Company</label>
                                            <input type="text" value={pic.company} onChange={(e) => updatePic(i, 'company', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-600">Designation</label>
                                            <input type="text" value={pic.designation} onChange={(e) => updatePic(i, 'designation', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-600">Email</label>
                                            <input type="email" value={pic.email} onChange={(e) => updatePic(i, 'email', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-600">Phone</label>
                                            <input type="text" value={pic.phone} onChange={(e) => updatePic(i, 'phone', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                        <textarea rows={2} value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : 'Update Contract'}</button>
                    </div>
                </form>
            </div>
        </div>
    );
}
