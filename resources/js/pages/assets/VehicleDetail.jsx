import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import assetService from '@/services/assetService';
import maintenanceService from '@/services/maintenanceService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlineArrowLeft,
    HiOutlinePlus,
    HiOutlineDocumentText,
    HiOutlineDownload,
    HiOutlineTrash,
    HiOutlineCog,
} from 'react-icons/hi';

const MAINTAINABLE_TYPE = 'App\\Models\\Vehicle';

function cap(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : '';
}

function expiryChip(expiry) {
    if (!expiry) return { label: 'No expiry', cls: 'bg-gray-100 text-gray-500' };
    const days = Math.ceil((new Date(expiry) - new Date()) / 86400000);
    if (days < 0) return { label: `Expired ${Math.abs(days)}d ago`, cls: 'bg-red-100 text-red-700' };
    if (days <= 30) return { label: `${days}d left`, cls: 'bg-red-100 text-red-700' };
    if (days <= 60) return { label: `${days}d left`, cls: 'bg-amber-100 text-amber-700' };
    return { label: `${days}d left`, cls: 'bg-green-100 text-green-700' };
}

export default function VehicleDetail() {
    const { id } = useParams();
    const { can } = useAuth();
    const [vehicle, setVehicle] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showDocForm, setShowDocForm] = useState(false);
    const [showMaintForm, setShowMaintForm] = useState(false);
    const [saving, setSaving] = useState(false);
    const [docForm, setDocForm] = useState({
        doc_type: 'road_tax', provider: '', policy_or_ref_no: '', amount: '',
        start_date: '', expiry_date: '', notes: '', file: null,
    });
    const [maintForm, setMaintForm] = useState({
        maintenance_type: 'preventive', performed_date: new Date().toISOString().split('T')[0],
        next_due_date: '', description: '', cost: '', vendor: '', performed_by: '', status: 'completed',
    });

    const fetchVehicle = async () => {
        setLoading(true);
        try {
            const res = await assetService.getVehicle(id);
            setVehicle(res.data);
        } catch {
            toast.error('Failed to load vehicle');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchVehicle(); }, [id]);

    const handleDocSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const fd = new FormData();
            Object.entries(docForm).forEach(([k, val]) => {
                if (val !== '' && val !== null) fd.append(k, val);
            });
            await assetService.addDocument(id, fd);
            toast.success('Document added');
            setShowDocForm(false);
            setDocForm({ doc_type: 'road_tax', provider: '', policy_or_ref_no: '', amount: '', start_date: '', expiry_date: '', notes: '', file: null });
            fetchVehicle();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to add document');
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteDoc = async (docId) => {
        if (!confirm('Delete this document?')) return;
        try {
            await assetService.deleteDocument(id, docId);
            toast.success('Document deleted');
            fetchVehicle();
        } catch {
            toast.error('Failed to delete');
        }
    };

    const handleMaintSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = { ...maintForm, maintainable_type: MAINTAINABLE_TYPE, maintainable_id: Number(id) };
            Object.keys(payload).forEach((k) => { if (payload[k] === '') delete payload[k]; });
            await maintenanceService.create(payload);
            toast.success('Maintenance log added');
            setShowMaintForm(false);
            setMaintForm({ maintenance_type: 'preventive', performed_date: new Date().toISOString().split('T')[0], next_due_date: '', description: '', cost: '', vendor: '', performed_by: '', status: 'completed' });
            fetchVehicle();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to add log');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;
    if (!vehicle) return null;

    return (
        <div>
            <Link to="/assets/vehicles" className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary-600">
                <HiOutlineArrowLeft className="h-4 w-4" /> Back to Vehicles
            </Link>

            <div className="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">{vehicle.registration_no}</h1>
                        <p className="text-sm text-gray-500">{vehicle.make}{vehicle.model ? ` ${vehicle.model}` : ''}{vehicle.year ? ` (${vehicle.year})` : ''}</p>
                    </div>
                    <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{cap(vehicle.type)}</span>
                </div>
                <div className="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div><p className="text-gray-500">Status</p><p className="font-medium text-gray-900">{cap(vehicle.status)}</p></div>
                    <div><p className="text-gray-500">Assigned To</p><p className="font-medium text-gray-900">{vehicle.assigned_to?.full_name || '-'}</p></div>
                    <div><p className="text-gray-500">Purchase Date</p><p className="font-medium text-gray-900">{vehicle.purchase_date || '-'}</p></div>
                    <div><p className="text-gray-500">Current Value</p><p className="font-medium text-gray-900">{vehicle.current_value ? `RM ${Number(vehicle.current_value).toLocaleString('en-MY', { minimumFractionDigits: 2 })}` : '-'}</p></div>
                </div>
                {vehicle.notes && <p className="mt-4 text-sm text-gray-600">{vehicle.notes}</p>}
            </div>

            {/* Documents */}
            <div className="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-gray-900">Documents</h2>
                    {can('assets.manage') && (
                        <button onClick={() => setShowDocForm(true)} className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            <HiOutlinePlus className="h-4 w-4" /> Add Document
                        </button>
                    )}
                </div>
                {(vehicle.documents || []).length === 0 ? (
                    <p className="py-6 text-center text-sm text-gray-500">No documents recorded</p>
                ) : (
                    <div className="space-y-2">
                        {vehicle.documents.map((doc) => {
                            const chip = expiryChip(doc.expiry_date);
                            return (
                                <div key={doc.id} className="flex items-center justify-between rounded-lg border border-gray-100 p-3">
                                    <div className="flex items-center gap-3">
                                        <span className="rounded-lg bg-primary-50 p-2 text-primary-600"><HiOutlineDocumentText className="h-5 w-5" /></span>
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">{cap(doc.doc_type)}{doc.provider ? ` · ${doc.provider}` : ''}</p>
                                            <p className="text-xs text-gray-500">{doc.policy_or_ref_no || ''}{doc.expiry_date ? ` · expires ${doc.expiry_date}` : ''}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${chip.cls}`}>{chip.label}</span>
                                        {doc.file_path && (
                                            <a href={assetService.getDocumentDownloadUrl(id, doc.id)} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Download">
                                                <HiOutlineDownload className="h-4 w-4" />
                                            </a>
                                        )}
                                        {can('assets.manage') && (
                                            <button onClick={() => handleDeleteDoc(doc.id)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                                <HiOutlineTrash className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Maintenance history */}
            <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-gray-900">Maintenance History</h2>
                    {can('maintenance.manage') && (
                        <button onClick={() => setShowMaintForm(true)} className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            <HiOutlinePlus className="h-4 w-4" /> Add Log
                        </button>
                    )}
                </div>
                {(vehicle.maintenance_logs || []).length === 0 ? (
                    <p className="py-6 text-center text-sm text-gray-500">No maintenance records</p>
                ) : (
                    <div className="space-y-2">
                        {vehicle.maintenance_logs.map((log) => (
                            <div key={log.id} className="flex items-start justify-between rounded-lg border border-gray-100 p-3">
                                <div className="flex items-start gap-3">
                                    <span className="rounded-lg bg-gray-100 p-2 text-gray-600"><HiOutlineCog className="h-5 w-5" /></span>
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{cap(log.maintenance_type)} · {log.performed_date}</p>
                                        <p className="text-xs text-gray-500">{log.description}</p>
                                        {log.next_due_date && <p className="text-xs text-amber-600">Next due: {log.next_due_date}</p>}
                                    </div>
                                </div>
                                <div className="text-right">
                                    {log.cost && <p className="text-sm font-medium text-gray-900">RM {Number(log.cost).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>}
                                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{cap(log.status)}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Add Document Modal */}
            {showDocForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowDocForm(false)}>
                    <div className="mx-4 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Add Document</h3>
                        <form onSubmit={handleDocSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Type</label>
                                    <select value={docForm.doc_type} onChange={(e) => setDocForm((p) => ({ ...p, doc_type: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="road_tax">Road Tax</option>
                                        <option value="insurance">Insurance</option>
                                        <option value="permit">Permit</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Provider</label>
                                    <input type="text" value={docForm.provider} onChange={(e) => setDocForm((p) => ({ ...p, provider: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Policy / Ref No</label>
                                    <input type="text" value={docForm.policy_or_ref_no} onChange={(e) => setDocForm((p) => ({ ...p, policy_or_ref_no: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Amount (RM)</label>
                                    <input type="number" step="0.01" value={docForm.amount} onChange={(e) => setDocForm((p) => ({ ...p, amount: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Start Date</label>
                                    <input type="date" value={docForm.start_date} onChange={(e) => setDocForm((p) => ({ ...p, start_date: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Expiry Date</label>
                                    <input type="date" value={docForm.expiry_date} onChange={(e) => setDocForm((p) => ({ ...p, expiry_date: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">File (PDF/JPG/PNG, max 10MB)</label>
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setDocForm((p) => ({ ...p, file: e.target.files[0] || null }))} className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <textarea rows={2} value={docForm.notes} onChange={(e) => setDocForm((p) => ({ ...p, notes: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowDocForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : 'Add Document'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Add Maintenance Modal */}
            {showMaintForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowMaintForm(false)}>
                    <div className="mx-4 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Add Maintenance Log</h3>
                        <form onSubmit={handleMaintSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Type</label>
                                    <select value={maintForm.maintenance_type} onChange={(e) => setMaintForm((p) => ({ ...p, maintenance_type: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="preventive">Preventive</option>
                                        <option value="corrective">Corrective</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                    <select value={maintForm.status} onChange={(e) => setMaintForm((p) => ({ ...p, status: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="completed">Completed</option>
                                        <option value="planned">Planned</option>
                                        <option value="in_progress">In Progress</option>
                                    </select>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Performed Date *</label>
                                    <input type="date" value={maintForm.performed_date} onChange={(e) => setMaintForm((p) => ({ ...p, performed_date: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Next Due Date</label>
                                    <input type="date" value={maintForm.next_due_date} onChange={(e) => setMaintForm((p) => ({ ...p, next_due_date: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Description *</label>
                                <textarea rows={2} value={maintForm.description} onChange={(e) => setMaintForm((p) => ({ ...p, description: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Cost (RM)</label>
                                    <input type="number" step="0.01" value={maintForm.cost} onChange={(e) => setMaintForm((p) => ({ ...p, cost: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Vendor</label>
                                    <input type="text" value={maintForm.vendor} onChange={(e) => setMaintForm((p) => ({ ...p, vendor: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Performed By</label>
                                <input type="text" value={maintForm.performed_by} onChange={(e) => setMaintForm((p) => ({ ...p, performed_by: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowMaintForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : 'Add Log'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
