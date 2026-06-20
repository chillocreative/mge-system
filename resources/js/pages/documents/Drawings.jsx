import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import drawingService from '@/services/drawingService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineTemplate,
    HiOutlineDownload,
    HiOutlineTrash,
    HiOutlineTag,
} from 'react-icons/hi';

const disciplines = [
    { value: '', label: 'All Disciplines' },
    { value: 'architectural', label: 'Architectural' },
    { value: 'structural', label: 'Structural' },
    { value: 'civil', label: 'Civil' },
    { value: 'mechanical', label: 'Mechanical' },
    { value: 'electrical', label: 'Electrical' },
    { value: 'other', label: 'Other' },
];

const disciplineColors = {
    architectural: 'bg-pink-100 text-pink-700',
    structural: 'bg-orange-100 text-orange-700',
    civil: 'bg-green-100 text-green-700',
    mechanical: 'bg-blue-100 text-blue-700',
    electrical: 'bg-yellow-100 text-yellow-700',
    other: 'bg-gray-100 text-gray-600',
};

function formatSize(bytes) {
    if (!bytes) return '-';
    const kb = bytes / 1024;
    return kb > 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${kb.toFixed(0)} KB`;
}

export default function Drawings() {
    const { can } = useAuth();
    const [drawings, setDrawings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [disciplineFilter, setDisciplineFilter] = useState('');
    const [pagination, setPagination] = useState({});
    const [showForm, setShowForm] = useState(false);
    const [projects, setProjects] = useState([]);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({
        title: '',
        drawing_no: '',
        reference_no: '',
        revision: '',
        tag: '',
        discipline: 'civil',
        project_id: '',
        file: null,
    });

    const fetchDrawings = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (disciplineFilter) params.discipline = disciplineFilter;
            const res = await drawingService.list(params);
            setDrawings(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setDrawings([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchDrawings(), 400);
        return () => clearTimeout(timer);
    }, [search, disciplineFilter]);

    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const formData = new FormData();
            formData.append('title', form.title);
            formData.append('drawing_no', form.drawing_no);
            formData.append('discipline', form.discipline);
            if (form.reference_no) formData.append('reference_no', form.reference_no);
            if (form.revision) formData.append('revision', form.revision);
            if (form.tag) formData.append('tag', form.tag);
            if (form.project_id) formData.append('project_id', form.project_id);
            if (form.file) formData.append('file', form.file);

            await drawingService.create(formData);
            toast.success('Drawing uploaded');
            setShowForm(false);
            setForm({ title: '', drawing_no: '', reference_no: '', revision: '', tag: '', discipline: 'civil', project_id: '', file: null });
            fetchDrawings();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to upload drawing');
        } finally {
            setSaving(false);
        }
    };

    const handleDownload = (id) => {
        window.open(drawingService.getDownloadUrl(id), '_blank');
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this drawing?')) return;
        try {
            await drawingService.remove(id);
            toast.success('Drawing deleted');
            fetchDrawings();
        } catch {
            toast.error('Failed to delete drawing');
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Drawings</h1>
                    <p className="text-sm text-gray-500">Engineering drawings indexed by tag, reference and drawing no</p>
                </div>
                {can('drawings.upload') && (
                    <button
                        onClick={() => setShowForm(true)}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        Upload Drawing
                    </button>
                )}
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search by tag, drawing no or reference..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                </div>
                <select
                    value={disciplineFilter}
                    onChange={(e) => setDisciplineFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    {disciplines.map((d) => (
                        <option key={d.value} value={d.value}>{d.label}</option>
                    ))}
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : drawings.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineTemplate className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No drawings found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Drawing No</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tag</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Discipline</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Rev</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {drawings.map((dwg) => (
                                    <tr key={dwg.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">{dwg.drawing_no}</p>
                                            {dwg.reference_no && <p className="text-xs text-gray-500">Ref: {dwg.reference_no}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700">{dwg.title}</td>
                                        <td className="px-4 py-3">
                                            {dwg.tag ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                    <HiOutlineTag className="h-3 w-3" />
                                                    {dwg.tag}
                                                </span>
                                            ) : (
                                                <span className="text-xs text-gray-400">-</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${disciplineColors[dwg.discipline] || disciplineColors.other}`}>
                                                {dwg.discipline}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{dwg.revision || '-'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{dwg.project?.name || '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {can('drawings.view') && (
                                                    <button
                                                        onClick={() => handleDownload(dwg.id)}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600"
                                                        title="Download"
                                                    >
                                                        <HiOutlineDownload className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {can('drawings.manage') && (
                                                    <button
                                                        onClick={() => handleDelete(dwg.id)}
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
                                        onClick={() => fetchDrawings(page)}
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
                    <div className="mx-4 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Upload Drawing</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
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
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Drawing No *</label>
                                    <input
                                        type="text"
                                        value={form.drawing_no}
                                        onChange={(e) => setForm((p) => ({ ...p, drawing_no: e.target.value }))}
                                        required
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Reference No</label>
                                    <input
                                        type="text"
                                        value={form.reference_no}
                                        onChange={(e) => setForm((p) => ({ ...p, reference_no: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Revision</label>
                                    <input
                                        type="text"
                                        value={form.revision}
                                        onChange={(e) => setForm((p) => ({ ...p, revision: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Tag</label>
                                    <input
                                        type="text"
                                        value={form.tag}
                                        onChange={(e) => setForm((p) => ({ ...p, tag: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Discipline</label>
                                    <select
                                        value={form.discipline}
                                        onChange={(e) => setForm((p) => ({ ...p, discipline: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        {disciplines.filter((d) => d.value).map((d) => (
                                            <option key={d.value} value={d.value}>{d.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Project</label>
                                    <select
                                        value={form.project_id}
                                        onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        <option value="">No Project</option>
                                        {projects.map((p) => (
                                            <option key={p.id} value={p.id}>{p.name}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">File * (DWG, DXF, PDF, PNG, JPG — max 100MB)</label>
                                <input
                                    type="file"
                                    accept=".dwg,.dxf,.pdf,.png,.jpg,.jpeg"
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
