import { useState, useEffect } from 'react';
import toast from 'react-hot-toast';
import qcService from '@/services/qcService';
import useDragScroll from '@/hooks/useDragScroll';
import { useAuth } from '@/context/AuthContext';
import { useConfirm } from '@/context/ConfirmContext';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import {
    HiOutlinePlus,
    HiOutlinePencilAlt,
    HiOutlineTrash,
    HiOutlineSearch,
    HiOutlineX,
    HiOutlineDocumentDownload,
} from 'react-icons/hi';

const TABS = [
    { label: 'Inspections', type: 'inspection' },
    { label: 'NCRs', type: 'ncr' },
    { label: 'Material Tests', type: 'material_test' },
    { label: 'Quality Audits', type: 'audit' },
];

const STATUSES = ['pending', 'open', 'in_progress', 'resolved', 'closed'];

const STATUS_BADGE = {
    resolved: 'bg-green-100 text-green-800',
    closed: 'bg-gray-100 text-gray-800',
    in_progress: 'bg-blue-100 text-blue-800',
    open: 'bg-purple-100 text-purple-800',
    pending: 'bg-yellow-100 text-yellow-800',
};

export default function QcRecords() {
    const { can } = useAuth();
    const confirm = useConfirm();
    const hasManage = can('qc.manage');
    const hasView = can('qc.view');

    const [activeTab, setActiveTab] = useState(TABS[0]);
    const [records, setRecords] = useState([]);
    const dragScrollRef = useDragScroll();
    const [loading, setLoading] = useState(true);
    const [projects, setProjects] = useState([]);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [currentRecord, setCurrentRecord] = useState(null);
    const [formLoading, setFormLoading] = useState(false);
    const [formData, setFormData] = useState({
        type: activeTab.type,
        title: '',
        reference_no: '',
        date: new Date().toISOString().split('T')[0],
        project_id: '',
        location: '',
        description: '',
        findings: '',
        corrective_action: '',
        verified_by: '',
        status: 'pending',
        attachment: null,
    });

    const resetForm = () => {
        setCurrentRecord(null);
        setFormData({
            type: activeTab.type,
            title: '',
            reference_no: '',
            date: new Date().toISOString().split('T')[0],
            project_id: '',
            location: '',
            description: '',
            findings: '',
            corrective_action: '',
            verified_by: '',
            status: 'pending',
            attachment: null,
        });
    };

    useEffect(() => {
        resetForm();
        fetchRecords();
        fetchProjects();
    }, [activeTab, search, statusFilter]);

    const fetchProjects = async () => {
        try {
            const res = await qcService.listProjects();
            setProjects(res.data?.data || res.data || res);
        } catch {
            toast.error('Failed to load projects');
        }
    };

    const fetchRecords = async () => {
        setLoading(true);
        try {
            const params = {
                type: activeTab.type,
                ...(statusFilter && { status: statusFilter }),
                ...(search && { search }),
            };
            const res = await qcService.listRecords(params);
            setRecords(res.data?.data || res.data || res);
        } catch {
            toast.error('Failed to load records');
        } finally {
            setLoading(false);
        }
    };

    const buildFormData = () => {
        const data = new FormData();
        Object.entries(formData).forEach(([key, value]) => {
            if (key === 'attachment') {
                if (value) data.append(key, value);
                return;
            }
            if (value !== null && value !== '') {
                data.append(key, value);
            }
        });
        return data;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setFormLoading(true);
        try {
            const data = buildFormData();
            if (currentRecord) {
                await qcService.updateRecord(currentRecord.id, data);
                toast.success('Record updated successfully');
            } else {
                await qcService.createRecord(data);
                toast.success('Record created successfully');
            }
            setShowModal(false);
            fetchRecords();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Operation failed');
        } finally {
            setFormLoading(false);
        }
    };

    const handleDelete = async (id) => {
        if (!(await confirm({ message: 'Are you sure you want to delete this record?' }))) return;
        try {
            await qcService.deleteRecord(id);
            toast.success('Record deleted');
            fetchRecords();
        } catch {
            toast.error('Failed to delete record');
        }
    };

    const openEdit = (record) => {
        setCurrentRecord(record);
        setFormData({
            type: record.type,
            title: record.title || '',
            reference_no: record.reference_no || '',
            date: record.date?.split('T')[0] || new Date().toISOString().split('T')[0],
            project_id: record.project_id || '',
            location: record.location || '',
            description: record.description || '',
            findings: record.findings || '',
            corrective_action: record.corrective_action || '',
            verified_by: record.verified_by || '',
            status: record.status || 'pending',
            attachment: null,
        });
        setShowModal(true);
    };

    if (!hasView) {
        return (
            <div className="flex h-64 items-center justify-center text-gray-500">
                You do not have permission to view QA/QC records.
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h1 className="text-2xl font-bold text-gray-800">QA/QC Records</h1>
                {hasManage && (
                    <button
                        onClick={() => { resetForm(); setShowModal(true); }}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" /> New Record
                    </button>
                )}
            </div>

            <div className="border-b border-gray-200">
                <nav className="-mb-px flex space-x-8">
                    {TABS.map((tab) => (
                        <button
                            key={tab.type}
                            onClick={() => setActiveTab(tab)}
                            className={`border-b-2 pb-4 px-3 text-sm font-medium ${
                                activeTab.type === tab.type
                                    ? 'border-primary-500 text-primary-600'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="relative flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search records..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 pl-10 pr-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                </div>
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none"
                >
                    <option value="">All Statuses</option>
                    {STATUSES.map((s) => (
                        <option key={s} value={s}>{s.replace('_', ' ')}</option>
                    ))}
                </select>
            </div>

            <div className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                {loading ? (
                    <div className="flex h-32 items-center justify-center">
                        <LoadingSpinner />
                    </div>
                ) : (
                    <div ref={dragScrollRef} className="overflow-x-auto cursor-grab active:cursor-grabbing">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reference No</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Title</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Project</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Attachment</th>
                                    {hasManage && <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white">
                                {records.length === 0 ? (
                                    <tr>
                                        <td colSpan={hasManage ? 7 : 6} className="px-6 py-8 text-center text-sm text-gray-500">No records found.</td>
                                    </tr>
                                ) : (
                                    records.map((record) => (
                                        <tr key={record.id} className="hover:bg-gray-50">
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{formatDate(record.date)}</td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{record.reference_no || '-'}</td>
                                            <td className="px-6 py-4 text-sm text-gray-600 max-w-xs truncate">{record.title}</td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{record.project?.name || '-'}</td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${STATUS_BADGE[record.status] || STATUS_BADGE.pending}`}>
                                                    {record.status?.replace('_', ' ')}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                                {record.attachment_path ? (
                                                    <a
                                                        href={qcService.getAttachmentUrl(record)}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center text-primary-600 hover:text-primary-800"
                                                        title="Download attachment"
                                                    >
                                                        <HiOutlineDocumentDownload className="h-5 w-5" />
                                                    </a>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>
                                            {hasManage && (
                                                <td className="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                                    <button onClick={() => openEdit(record)} className="text-primary-600 hover:text-primary-900 mr-3"><HiOutlinePencilAlt className="h-4 w-4" /></button>
                                                    <button onClick={() => handleDelete(record.id)} className="text-red-600 hover:text-red-900"><HiOutlineTrash className="h-4 w-4" /></button>
                                                </td>
                                            )}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-xl max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between border-b px-6 py-4">
                            <h3 className="text-lg font-semibold text-gray-800">
                                {currentRecord ? 'Edit Record' : 'Create New Record'}
                            </h3>
                            <button onClick={() => setShowModal(false)} className="text-gray-400 hover:text-gray-600">
                                <HiOutlineX className="h-6 w-6" />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Type</label>
                                    <input disabled value={activeTab.label} className="mt-1 w-full rounded border-gray-300 bg-gray-100 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Reference No</label>
                                    <input type="text" value={formData.reference_no} onChange={(e) => setFormData({ ...formData, reference_no: e.target.value })} className="mt-1 w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Date *</label>
                                    <input required type="date" value={formData.date} onChange={(e) => setFormData({ ...formData, date: e.target.value })} className="mt-1 w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Project *</label>
                                    <select required value={formData.project_id} onChange={(e) => setFormData({ ...formData, project_id: e.target.value })} className="mt-1 w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                                        <option value="">Select Project</option>
                                        {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </select>
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700">Title *</label>
                                    <input required type="text" value={formData.title} onChange={(e) => setFormData({ ...formData, title: e.target.value })} className="mt-1 w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Location</label>
                                    <input type="text" value={formData.location} onChange={(e) => setFormData({ ...formData, location: e.target.value })} className="mt-1 w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Status</label>
                                    <select value={formData.status} onChange={(e) => setFormData({ ...formData, status: e.target.value })} className="mt-1 w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                                        {STATUSES.map((s) => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
                                    </select>
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700">Description</label>
                                    <textarea rows={2} value={formData.description} onChange={(e) => setFormData({ ...formData, description: e.target.value })} className="mt-1 w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700">Findings</label>
                                    <textarea rows={2} value={formData.findings} onChange={(e) => setFormData({ ...formData, findings: e.target.value })} className="mt-1 w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700">Corrective Action</label>
                                    <textarea rows={2} value={formData.corrective_action} onChange={(e) => setFormData({ ...formData, corrective_action: e.target.value })} className="mt-1 w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Verified By</label>
                                    <input type="text" value={formData.verified_by} onChange={(e) => setFormData({ ...formData, verified_by: e.target.value })} className="mt-1 w-full rounded border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Attachment</label>
                                    <input type="file" onChange={(e) => setFormData({ ...formData, attachment: e.target.files[0] })} className="mt-1 w-full text-sm text-gray-500 file:mr-4 file:rounded file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100" />
                                </div>
                            </div>
                            <div className="flex justify-end gap-3 pt-4">
                                <button type="button" onClick={() => setShowModal(false)} className="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={formLoading} className="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-70">
                                    {formLoading ? 'Saving...' : 'Save Record'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
