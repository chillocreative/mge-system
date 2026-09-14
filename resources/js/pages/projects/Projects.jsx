import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { useConfirm } from '@/context/ConfirmContext';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import ProjectFilesPanel from '@/components/ProjectFilesPanel';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus, HiOutlineSearch, HiOutlineBriefcase, HiOutlinePaperClip, HiOutlineX,
    HiOutlineArchive, HiOutlineRefresh, HiOutlineTrash,
} from 'react-icons/hi';

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    planning: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-yellow-100 text-yellow-700',
    on_hold: 'bg-orange-100 text-orange-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};

const priorityColors = {
    low: 'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-600',
    high: 'bg-orange-100 text-orange-600',
    critical: 'bg-red-100 text-red-600',
};

export default function Projects() {
    const { can } = useAuth();
    const canEdit = can('projects.edit');
    const canDelete = can('projects.delete');
    const confirm = useConfirm();

    const [projects, setProjects] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [filesModal, setFilesModal] = useState(null);

    const openFiles = (e, project) => { e.preventDefault(); e.stopPropagation(); setFilesModal(project); };

    const fetchProjects = async () => {
        setLoading(true);
        try {
            const params = {};
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            const response = await projectService.list(params);
            setProjects(response.data?.data || []);
        } catch {
            setProjects([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchProjects();
    }, [statusFilter]);

    useEffect(() => {
        const timer = setTimeout(() => {
            fetchProjects();
        }, 400);
        return () => clearTimeout(timer);
    }, [search]);

    const handleArchive = async (project) => {
        if (!(await confirm({ title: 'Archive project?', message: 'It will be hidden from the main list until restored.', confirmText: 'Archive', danger: false }))) return;
        try {
            await projectService.archive(project.id);
            toast.success('Project archived');
            fetchProjects();
        } catch {
            toast.error('Failed to archive project');
        }
    };

    const handleUnarchive = async (project) => {
        if (!(await confirm({ title: 'Restore project?', message: 'Restore this project to the active list?', confirmText: 'Restore', danger: false }))) return;
        try {
            await projectService.unarchive(project.id);
            toast.success('Project restored');
            fetchProjects();
        } catch {
            toast.error('Failed to restore project');
        }
    };

    const handleDelete = async (project) => {
        if (!(await confirm({ title: 'Delete project?', message: 'This cannot be undone from the UI.' }))) return;
        try {
            await projectService.delete(project.id);
            toast.success('Project deleted');
            fetchProjects();
        } catch {
            toast.error('Failed to delete project');
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Projects</h1>
                    <p className="text-sm text-gray-500">Manage all construction projects</p>
                </div>
                <Link
                    to="/projects/create"
                    className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                >
                    <HiOutlinePlus className="h-5 w-5" />
                    New Project
                </Link>
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search projects..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                </div>
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="planning">Planning</option>
                    <option value="in_progress">In Progress</option>
                    <option value="on_hold">On Hold</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : projects.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineBriefcase className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No projects found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Client</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Priority</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Progress</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Due</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Files</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {projects.map((project) => (
                                    <tr key={project.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <Link to={`/projects/${project.id}`} className="text-sm font-semibold text-gray-900 hover:text-primary-600">
                                                {project.name}
                                            </Link>
                                            <p className="text-xs text-gray-500">{project.code}</p>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{project.client?.company_name || '-'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${priorityColors[project.priority]}`}>
                                                {project.priority}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[project.status]}`}>
                                                {project.status.replace('_', ' ')}
                                            </span>
                                            {project.archived_at && (
                                                <span className="ml-1.5 text-xs text-gray-400">&middot; Archived</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex min-w-[7rem] items-center gap-2">
                                                <div className="h-2 w-full rounded-full bg-gray-200">
                                                    <div
                                                        className="h-2 rounded-full bg-primary-500 transition-all"
                                                        style={{ width: `${project.progress}%` }}
                                                    />
                                                </div>
                                                <span className="text-xs text-gray-500">{project.progress}%</span>
                                            </div>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                            {project.end_date ? formatDate(project.end_date) : '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <button
                                                type="button"
                                                onClick={(e) => openFiles(e, project)}
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-500 hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700"
                                                title="View project files"
                                            >
                                                <HiOutlinePaperClip className="h-3.5 w-3.5" />
                                                {project.documents_count ?? 0}
                                            </button>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {canEdit && !project.archived_at && (
                                                    <button
                                                        onClick={() => handleArchive(project)}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-amber-50 hover:text-amber-600"
                                                        title="Archive"
                                                    >
                                                        <HiOutlineArchive className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {canEdit && project.archived_at && (
                                                    <button
                                                        onClick={() => handleUnarchive(project)}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-green-50 hover:text-green-600"
                                                        title="Restore"
                                                    >
                                                        <HiOutlineRefresh className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {canDelete && (
                                                    <button
                                                        onClick={() => handleDelete(project)}
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
                </div>
            )}

            {/* Project files modal */}
            {filesModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setFilesModal(null)}>
                    <div className="max-h-[85vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-start justify-between">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900">{filesModal.name}</h3>
                                <p className="text-sm text-gray-500">Project files</p>
                            </div>
                            <button onClick={() => setFilesModal(null)} className="rounded p-1 text-gray-400 hover:bg-gray-100"><HiOutlineX className="h-5 w-5" /></button>
                        </div>
                        <ProjectFilesPanel projectId={filesModal.id} />
                    </div>
                </div>
            )}
        </div>
    );
}
