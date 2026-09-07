import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import staffService from '@/services/staffService';
import { HiOutlineBriefcase } from 'react-icons/hi';

/**
 * Projects this staff member is involved in, shown on their detail page (Ciri 15).
 *
 * "Involved" = a member of the project (project_members). Read-only here: this
 * card answers "what is this person on?" at a glance; managing membership lives
 * on the project itself.
 */

const statusColors = {
    active: 'bg-green-50 text-green-700',
    on_hold: 'bg-amber-50 text-amber-700',
    completed: 'bg-gray-100 text-gray-600',
    cancelled: 'bg-red-50 text-red-700',
};

export default function StaffProjectsCard({ employeeId }) {
    const [projects, setProjects] = useState([]);
    const [loading, setLoading] = useState(true);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!employeeId) return;
        let cancelled = false;
        setLoading(true);
        staffService
            .projects(employeeId)
            .then((res) => {
                if (cancelled) return;
                setProjects(res.data || []);
                setFailed(false);
            })
            .catch(() => !cancelled && setFailed(true))
            .finally(() => !cancelled && setLoading(false));
        return () => { cancelled = true; };
    }, [employeeId]);

    const active = projects.filter((p) => p.active);

    return (
        <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 lg:col-span-2">
            <h2 className="mb-3 text-sm font-semibold uppercase text-gray-500">
                Project Involvement
                {!loading && !failed && (
                    <span className="ml-1 font-normal normal-case text-gray-400">({active.length} active)</span>
                )}
            </h2>

            {loading && <p className="py-2 text-sm text-gray-400">Loading…</p>}
            {!loading && failed && <p className="py-2 text-sm text-gray-400">Project involvement is unavailable.</p>}
            {!loading && !failed && projects.length === 0 && (
                <p className="py-2 text-sm text-gray-400">Not a member of any project.</p>
            )}

            {!loading && !failed && projects.length > 0 && (
                <ul className="divide-y divide-gray-100">
                    {projects.map((p) => (
                        <li key={p.project_id} className="flex items-center justify-between gap-3 py-2">
                            <div className="min-w-0">
                                <Link
                                    to={`/projects/${p.project_id}`}
                                    className="flex items-center gap-2 text-sm font-medium text-gray-900 hover:text-primary-700"
                                >
                                    <HiOutlineBriefcase className="h-4 w-4 shrink-0 text-gray-400" />
                                    <span className="truncate">{p.name}</span>
                                </Link>
                                <p className="ml-6 text-xs text-gray-400">
                                    {p.code ? `${p.code} · ` : ''}{p.role}
                                    {p.left_at ? ` · left ${p.left_at}` : ''}
                                </p>
                            </div>
                            <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusColors[p.status] || 'bg-gray-100 text-gray-600'}`}>
                                {String(p.status || '').replace(/_/g, ' ')}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
