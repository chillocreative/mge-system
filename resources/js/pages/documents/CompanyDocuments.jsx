import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import documentService from '@/services/documentService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineDocumentText,
    HiOutlineDownload,
    HiOutlineTrash,
} from 'react-icons/hi';

const docTypes = [
    { value: '', label: 'All Types' },
    { value: 'contract', label: 'Contracts' },
    { value: 'tender', label: 'Tender' },
    { value: 'sst', label: 'SST' },
    { value: 'policy', label: 'Policy' },
    { value: 'procedure', label: 'Procedure' },
    { value: 'other', label: 'Other' },
];

const typeColors = {
    contract: 'bg-blue-100 text-blue-700',
    tender: 'bg-purple-100 text-purple-700',
    sst: 'bg-amber-100 text-amber-700',
    policy: 'bg-emerald-100 text-emerald-700',
    procedure: 'bg-teal-100 text-teal-700',
    other: 'bg-gray-100 text-gray-600',
};

function formatSize(bytes) {
    if (!bytes) return '-';
    const kb = bytes / 1024;
    return kb > 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${kb.toFixed(0)} KB`;
}

export default function CompanyDocuments() {
    const { can } = useAuth();
    const [documents, setDocuments] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [pagination, setPagination] = useState({});
    const [showForm, setShowForm] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({
        title: '',
        doc_type: 'contract',
        reference_no: '',
        description: '',
        file: null,
    });

    const fetchDocuments = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (typeFilter) params.doc_type = typeFilter;
            const res = await documentService.list(params);
            setDocuments(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setDocuments([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchDocuments(), 400);
        return () => clearTimeout(timer);
    }, [search, typeFilter]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const formData = new FormData();
            formData.append('title', form.title);
            formData.append('doc_type', form.doc_type);
            if (form.reference_no) formData.append('reference_no', form.reference_no);
            if (form.description) formData.append('description', form.description);
            if (form.file) formData.append('file', form.file);

            await documentService.create(formData);
            toast.success('Document uploaded');
            setShowForm(false);
            setForm({ title: '', doc_type: 'contract', reference_no: '', description: '', file: null });
            fetchDocuments();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to upload document');
        } finally {
            setSaving(false);
        }
    };

    const handleDownload = (id) => {
        window.open(documentService.getDownloadUrl(id), '_blank');
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this document?')) return;
        try {
            await documentService.remove(id);
            toast.success('Document deleted');
            fetchDocuments();
        } catch {
            toast.error('Failed to delete document');
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Company Documents</h1>
                    <p className="text-sm text-gray-500">Contracts, Tender, SST and company policies</p>
                </div>
                {can('documents.upload') && (
                    <button
                        onClick={() => setShowForm(true)}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        Upload Document
                    </button>
                )}
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search by title or reference no..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                </div>
                <div className="flex flex-wrap gap-2">
                    {docTypes.map((t) => (
                        <button
                            key={t.value}
                            onClick={() => setTypeFilter(t.value)}
                            className={`rounded-lg px-3 py-2 text-sm font-medium transition ${
                                typeFilter === t.value
                                    ? 'bg-primary-600 text-white'
                                    : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : documents.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineDocumentText className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No documents found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Reference No</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Size</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Uploaded</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {documents.map((doc) => (
                                    <tr key={doc.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">{doc.title}</p>
                                            <p className="text-xs text-gray-500">{doc.file_name}</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${typeColors[doc.doc_type] || typeColors.other}`}>
                                                {doc.doc_type}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{doc.reference_no || '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{formatSize(doc.file_size)}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                            {doc.created_at?.split('T')[0]}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {can('documents.view') && (
                                                    <button
                                                        onClick={() => handleDownload(doc.id)}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600"
                                                        title="Download"
                                                    >
                                                        <HiOutlineDownload className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {can('documents.manage') && (
                                                    <button
                                                        onClick={() => handleDelete(doc.id)}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                        title="Delete"
                                                    >
                                                        <HiOutlineTrash className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {pagination.last_page > 1 && (
                        <div className="flex items-center justify-between border-t px-4 py-3">
                            <p className="text-sm text-gray-500">
                                Showing {pagination.from}-{pagination.to} of {pagination.total}
                            </p>
                            <div className="flex gap-1">
                                {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                                    <button
                                        key={page}
                                        onClick={() => fetchDocuments(page)}
                                        className={`rounded px-3 py-1 text-sm ${
                                            page === pagination.current_page
                                                ? 'bg-primary-600 text-white'
                                                : 'text-gray-600 hover:bg-gray-100'
                                        }`}
                                    >
                                        {page}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Upload Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowForm(false)}>
                    <div className="mx-4 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Upload Document</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Title *</label>
                                <input
                                    type="text"
                                    value={form.title}
                                    onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))}
                                    required
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Document Type</label>
                                    <select
                                        value={form.doc_type}
                                        onChange={(e) => setForm((p) => ({ ...p, doc_type: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        {docTypes.filter((t) => t.value).map((t) => (
                                            <option key={t.value} value={t.value}>{t.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Reference No</label>
                                    <input
                                        type="text"
                                        value={form.reference_no}
                                        onChange={(e) => setForm((p) => ({ ...p, reference_no: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                <textarea
                                    rows={2}
                                    value={form.description}
                                    onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">File * (PDF, DOC, DOCX, XLS, XLSX — max 50MB)</label>
                                <input
                                    type="file"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx"
                                    onChange={(e) => setForm((p) => ({ ...p, file: e.target.files[0] || null }))}
                                    required
                                    className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100"
                                />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowForm(false)}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={saving}
                                    className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                                >
                                    {saving ? 'Uploading...' : 'Upload'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
