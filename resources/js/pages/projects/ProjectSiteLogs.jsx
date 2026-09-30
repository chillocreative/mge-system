import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { HiOutlineDocumentText } from 'react-icons/hi';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useAuth } from '@/context/AuthContext';
import projectService from '@/services/projectService';
import { SiteLogsTab } from './ProjectDetail';

export default function ProjectSiteLogs() {
    const { can } = useAuth();
    const [searchParams, setSearchParams] = useSearchParams();
    const [projects, setProjects] = useState([]);
    const [project, setProject] = useState(null);
    const [loadingProjects, setLoadingProjects] = useState(true);
    const [loadingProject, setLoadingProject] = useState(false);
    const selectedProjectId = searchParams.get('project');

    useEffect(() => {
        let active = true;

        projectService.list({ per_page: 200 })
            .then((response) => {
                if (!active) return;

                const availableProjects = response.data?.data || [];
                setProjects(availableProjects);

                const selectedExists = availableProjects.some(
                    (item) => String(item.id) === String(selectedProjectId)
                );

                if (!selectedExists && availableProjects.length > 0) {
                    setSearchParams({ project: String(availableProjects[0].id) }, { replace: true });
                }
            })
            .catch(() => setProjects([]))
            .finally(() => setLoadingProjects(false));

        return () => { active = false; };
    }, []);

    const fetchProject = async () => {
        if (!selectedProjectId) {
            setProject(null);
            return;
        }

        setLoadingProject(true);
        try {
            const response = await projectService.get(selectedProjectId);
            setProject(response.data);
        } catch {
            setProject(null);
        } finally {
            setLoadingProject(false);
        }
    };

    useEffect(() => {
        fetchProject();
    }, [selectedProjectId]);

    if (loadingProjects) return <LoadingSpinner />;

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <HiOutlineDocumentText className="h-7 w-7" />
                        Site Logs
                    </h1>
                    <p className="text-sm text-gray-500">Record and review daily activity for each project.</p>
                </div>

                {projects.length > 0 && (
                    <label className="w-full sm:w-80">
                        <span className="mb-1 block text-xs font-semibold uppercase text-gray-500">Project</span>
                        <select
                            value={selectedProjectId || ''}
                            onChange={(event) => setSearchParams({ project: event.target.value })}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        >
                            {projects.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.code ? `${item.code} — ` : ''}{item.name}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
            </div>

            {projects.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <p className="text-sm text-gray-500">No projects available.</p>
                </div>
            ) : loadingProject ? (
                <LoadingSpinner />
            ) : project ? (
                <SiteLogsTab
                    key={project.id}
                    project={project}
                    canEdit={can('projects.edit')}
                    onRefresh={fetchProject}
                />
            ) : (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <p className="text-sm text-gray-500">Unable to load this project.</p>
                </div>
            )}
        </div>
    );
}
