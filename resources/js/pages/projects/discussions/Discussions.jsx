import { useState, useEffect } from 'react';
import projectService from '@/services/projectService';
import ProjectDiscussions from '@/components/ProjectDiscussions';
import { HiOutlineChatAlt2 } from 'react-icons/hi';

export default function Discussions() {
    const [projects, setProjects] = useState([]);
    const [projectId, setProjectId] = useState('');

    useEffect(() => {
        projectService
            .list({ per_page: 100 })
            .then((r) => setProjects(r.data?.data || []))
            .catch(() => {});
    }, []);

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Discussions</h1>
                    <p className="text-sm text-gray-500">Post and follow project updates with your team</p>
                </div>
                <div className="w-full sm:max-w-xs">
                    <label className="mb-1 block text-sm font-medium text-gray-700">Project</label>
                    <select
                        value={projectId}
                        onChange={(e) => setProjectId(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    >
                        <option value="">Select a project...</option>
                        {projects.map((p) => (
                            <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                    </select>
                </div>
            </div>

            {!projectId ? (
                <div className="rounded-xl bg-white py-16 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineChatAlt2 className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">Select a project to view its discussion feed</p>
                </div>
            ) : (
                <ProjectDiscussions projectId={projectId} />
            )}
        </div>
    );
}
