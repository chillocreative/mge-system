import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { HiOutlinePlus, HiOutlineSearch, HiOutlineBriefcase } from 'react-icons/hi';

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
    const [projects, setProjects] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');

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
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {projects.map((project) => (
                        <Link
                            key={project.id}
                            to={`/projects/${project.id}`}
                            className="group rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition-shadow hover:shadow-md"
                        >
                            <div className="mb-3 flex items-start justify-between">
                                <div>
                                    <h3 className="text-sm font-semibold text-gray-900 group-hover:text-primary-600">
                                        {project.name}
                                    </h3>
                                    <p className="text-xs text-gray-500">{project.code}</p>
                                </div>
                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${priorityColors[project.priority]}`}>
                                    {project.priority}
                                </span>
                            </div>

                            {project.client && (
                                <p className="mb-3 text-xs text-gray-500">
                                    Client: {project.client.company_name}
                                </p>
                            )}

                            <div className="mb-3">
                                <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
                                    <span>Progress</span>
                                    <span>{project.progress}%</span>
                                </div>
                                <div className="h-2 w-full rounded-full bg-gray-200">
                                    <div
                                        className="h-2 rounded-full bg-primary-500 transition-all"
                                        style={{ width: `${project.progress}%` }}
                                    />
                                </div>
                            </div>

                            <div className="flex items-center justify-between">
                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[project.status]}`}>
                                    {project.status.replace('_', ' ')}
                                </span>
                                {project.end_date && (
                                    <span className="text-xs text-gray-400">
                                        Due: {project.end_date}
                                    </span>
                                )}
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}
