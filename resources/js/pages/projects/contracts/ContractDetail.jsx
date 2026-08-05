import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import contractService from '@/services/contractService';
import drawingService from '@/services/drawingService';
import LoadingSpinner from '@/components/LoadingSpinner';
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
                        {contract.start_date ? String(contract.start_date).split('T')[0] : '-'}
                    </p>
                </div>
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <p className="text-xs font-medium text-gray-400">End Date</p>
                    <p className="mt-1 flex items-center gap-1 text-sm text-gray-900">
                        <HiOutlineCalendar className="h-4 w-4 text-gray-400" />
                        {contract.end_date ? String(contract.end_date).split('T')[0] : '-'}
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
                <nav className="-mb-px flex gap-6 overflow-x-auto">
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
            {activeTab === 'boq' && <BoqTab contract={contract} canEdit={canEdit} />}

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
        if (!confirm('Delete this document?')) return;
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

// ─── Drawings Tab ──────────────────────────────────────────────
const emptyDrawingForm = () => ({ title: '', drawing_no: '', discipline: 'civil', revision: '', tag: '', file: null });

function DrawingsTab({ contract, canEdit }) {
    const { can } = useAuth();
    const [drawings, setDrawings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(emptyDrawingForm());

    const canUpload = can('drawings.upload');
    const canManage = can('drawings.manage');

    const fetchDrawings = useCallback(async () => {
        setLoading(true);
        try {
            const res = await drawingService.list({ contract_id: contract.id, per_page: 100 });
            setDrawings(res.data?.data || []);
        } catch {
            setDrawings([]);
        } finally {
            setLoading(false);
        }
    }, [contract.id]);

    useEffect(() => { fetchDrawings(); }, [fetchDrawings]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.file) return toast.error('Please select a file');
        setSaving(true);
        try {
            const fd = new FormData();
            fd.append('title', form.title);
            fd.append('drawing_no', form.drawing_no);
            fd.append('discipline', form.discipline);
            if (form.revision) fd.append('revision', form.revision);
            if (form.tag) fd.append('tag', form.tag);
            fd.append('project_id', contract.project_id);
            fd.append('contract_id', contract.id);
            fd.append('file', form.file);
            await drawingService.create(fd);
            toast.success('Drawing uploaded');
            setShowForm(false);
            setForm(emptyDrawingForm());
            fetchDrawings();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Upload failed');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this drawing?')) return;
        try {
            await drawingService.remove(id);
            toast.success('Drawing deleted');
            fetchDrawings();
        } catch {
            toast.error('Failed to delete');
        }
    };

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900">Drawings</h2>
                {canEdit && canUpload && (
                    <button onClick={() => setShowForm(!showForm)} className="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700">
                        <HiOutlinePlus className="h-4 w-4" /> Add Drawing
                    </button>
                )}
            </div>

            {showForm && (
                <form onSubmit={handleSubmit} className="mb-4 rounded-lg border border-primary-200 bg-primary-50 p-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <input type="text" placeholder="Title" required value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <input type="text" placeholder="Drawing No." required value={form.drawing_no} onChange={(e) => setForm((p) => ({ ...p, drawing_no: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <select value={form.discipline} onChange={(e) => setForm((p) => ({ ...p, discipline: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            {['architectural', 'structural', 'civil', 'mechanical', 'electrical', 'other'].map((d) => (
                                <option key={d} value={d}>{d.charAt(0).toUpperCase() + d.slice(1)}</option>
                            ))}
                        </select>
                        <input type="text" placeholder="Revision" value={form.revision} onChange={(e) => setForm((p) => ({ ...p, revision: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <input type="text" placeholder="Tag" value={form.tag} onChange={(e) => setForm((p) => ({ ...p, tag: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <input type="file" accept=".dwg,.dxf,.pdf,.png,.jpg,.jpeg" onChange={(e) => setForm((p) => ({ ...p, file: e.target.files[0] }))} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-sm file:font-medium file:text-primary-700" />
                    </div>
                    <div className="mt-3 flex justify-end gap-2">
                        <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Uploading...' : 'Upload'}</button>
                    </div>
                </form>
            )}

            {loading ? (
                <LoadingSpinner />
            ) : drawings.length ? (
                <ul className="divide-y divide-gray-100 rounded-lg border border-gray-100">
                    {drawings.map((d) => (
                        <li key={d.id} className="flex items-center justify-between px-3 py-2">
                            <span className="flex min-w-0 items-center gap-2 text-sm text-gray-700">
                                <HiOutlineDocumentText className="h-4 w-4 flex-shrink-0 text-gray-400" />
                                <span className="truncate">{d.title} <span className="text-gray-400">({d.drawing_no}{d.revision ? ` · Rev ${d.revision}` : ''})</span></span>
                            </span>
                            <span className="flex shrink-0 items-center gap-1">
                                <a href={drawingService.getDownloadUrl(d.id)} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Download">
                                    <HiOutlineDownload className="h-4 w-4" />
                                </a>
                                {canEdit && canManage && (
                                    <button onClick={() => handleDelete(d.id)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                        <HiOutlineTrash className="h-4 w-4" />
                                    </button>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-gray-400">No drawings for this contract</p>
            )}
        </div>
    );
}

// ─── Bill of Quantity Tab ──────────────────────────────────────────────
const emptyBoqForm = () => ({ item_no: '', description: '', unit: '', quantity: '', rate: '' });

function BoqTab({ contract, canEdit }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [form, setForm] = useState(emptyBoqForm());
    const [saving, setSaving] = useState(false);

    const fetchItems = useCallback(async () => {
        setLoading(true);
        try {
            const res = await contractService.listBoqItems(contract.id);
            setItems(res.data || []);
        } catch {
            setItems([]);
        } finally {
            setLoading(false);
        }
    }, [contract.id]);

    useEffect(() => { fetchItems(); }, [fetchItems]);

    const addItem = async () => {
        if (!form.description || form.quantity === '' || form.rate === '') return;
        setSaving(true);
        try {
            const res = await contractService.addBoqItem(contract.id, form);
            setItems((p) => [...p, res.data]);
            setForm(emptyBoqForm());
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to add item');
        } finally {
            setSaving(false);
        }
    };

    const removeItem = async (itemId) => {
        if (!confirm('Remove this BOQ item?')) return;
        try {
            await contractService.removeBoqItem(itemId);
            setItems((p) => p.filter((i) => i.id !== itemId));
        } catch {
            toast.error('Failed to remove item');
        }
    };

    const total = items.reduce((sum, i) => sum + Number(i.amount || 0), 0);

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <h2 className="mb-4 text-lg font-semibold text-gray-900">Bill of Quantity (BQ)</h2>

            {loading ? (
                <LoadingSpinner />
            ) : (
                <div className="overflow-x-auto rounded-lg border border-gray-100">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Item No.</th>
                                <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Description</th>
                                <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Unit</th>
                                <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">Qty</th>
                                <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">Rate (RM)</th>
                                <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">Amount (RM)</th>
                                {canEdit && <th className="px-3 py-2"></th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {items.map((i) => (
                                <tr key={i.id}>
                                    <td className="px-3 py-2 text-sm text-gray-700">{i.item_no || '-'}</td>
                                    <td className="px-3 py-2 text-sm text-gray-700">{i.description}</td>
                                    <td className="px-3 py-2 text-sm text-gray-500">{i.unit || '-'}</td>
                                    <td className="px-3 py-2 text-right text-sm text-gray-700">{Number(i.quantity).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</td>
                                    <td className="px-3 py-2 text-right text-sm text-gray-700">{Number(i.rate).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</td>
                                    <td className="px-3 py-2 text-right text-sm font-medium text-gray-900">{Number(i.amount).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</td>
                                    {canEdit && (
                                        <td className="px-3 py-2 text-right">
                                            <button onClick={() => removeItem(i.id)} className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                                                <HiOutlineTrash className="h-4 w-4" />
                                            </button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {!items.length && (
                                <tr><td colSpan={canEdit ? 7 : 6} className="px-3 py-6 text-center text-sm text-gray-400">No BOQ items yet</td></tr>
                            )}
                        </tbody>
                        {items.length > 0 && (
                            <tfoot className="bg-gray-50">
                                <tr>
                                    <td colSpan={5} className="px-3 py-2 text-right text-sm font-semibold text-gray-700">Total</td>
                                    <td className="px-3 py-2 text-right text-sm font-bold text-gray-900">{total.toLocaleString('en-MY', { minimumFractionDigits: 2 })}</td>
                                    {canEdit && <td />}
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            )}

            {canEdit && (
                <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-6 sm:items-end">
                    <div className="col-span-1">
                        <label className="mb-1 block text-[11px] font-medium text-gray-500">Item No.</label>
                        <input type="text" value={form.item_no} onChange={(e) => setForm((p) => ({ ...p, item_no: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div className="col-span-2 sm:col-span-1">
                        <label className="mb-1 block text-[11px] font-medium text-gray-500">Description</label>
                        <input type="text" value={form.description} onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div className="col-span-1">
                        <label className="mb-1 block text-[11px] font-medium text-gray-500">Unit</label>
                        <input type="text" value={form.unit} onChange={(e) => setForm((p) => ({ ...p, unit: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div className="col-span-1">
                        <label className="mb-1 block text-[11px] font-medium text-gray-500">Qty</label>
                        <input type="number" step="0.01" min="0" value={form.quantity} onChange={(e) => setForm((p) => ({ ...p, quantity: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div className="col-span-1">
                        <label className="mb-1 block text-[11px] font-medium text-gray-500">Rate (RM)</label>
                        <input type="number" step="0.01" min="0" value={form.rate} onChange={(e) => setForm((p) => ({ ...p, rate: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div className="col-span-2 sm:col-span-1">
                        <button type="button" disabled={saving || !form.description || form.quantity === '' || form.rate === ''} onClick={addItem} className="w-full rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Adding...' : 'Add Item'}</button>
                    </div>
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
