import { useState, useEffect, useMemo, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import projectService from '@/services/projectService';
import taskService from '@/services/taskService';
import LoadingSpinner from '@/components/LoadingSpinner';
import ProjectDiscussions from '@/components/ProjectDiscussions';
import { useAuth } from '@/context/AuthContext';
import toast from 'react-hot-toast';
import {
    HiOutlineArrowLeft,
    HiOutlineCalendar,
    HiOutlineCurrencyDollar,
    HiOutlineLocationMarker,
    HiOutlineUser,
    HiOutlinePencil,
    HiOutlineTrash,
    HiOutlinePlus,
    HiOutlineDocumentDownload,
    HiOutlineClipboardList,
    HiOutlineFlag,
    HiOutlineDocumentText,
    HiOutlineClock,
    HiOutlineUpload,
    HiOutlineChatAlt2,
    HiOutlineX,
    HiOutlineSearch,
} from 'react-icons/hi';

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    planning: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-yellow-100 text-yellow-700',
    on_hold: 'bg-orange-100 text-orange-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
    pending: 'bg-gray-100 text-gray-700',
    in_review: 'bg-purple-100 text-purple-700',
    overdue: 'bg-red-100 text-red-700',
    scheduled: 'bg-blue-100 text-blue-700',
};

const weatherIcons = { sunny: '☀️', cloudy: '☁️', rainy: '🌧️', stormy: '⛈️', windy: '💨', other: '🌤️' };

const tabs = [
    { id: 'overview', label: 'Overview', icon: HiOutlineClipboardList },
    { id: 'tasks', label: 'Tasks', icon: HiOutlineClipboardList },
    { id: 'milestones', label: 'Milestones', icon: HiOutlineFlag },
    { id: 'timeline', label: 'Timeline', icon: HiOutlineClock },
    { id: 'site-logs', label: 'Site Logs', icon: HiOutlineDocumentText },
    { id: 'documents', label: 'Documents', icon: HiOutlineDocumentDownload },
    { id: 'calendar', label: 'Calendar', icon: HiOutlineCalendar },
    { id: 'discussions', label: 'Discussions', icon: HiOutlineChatAlt2 },
];

export default function ProjectDetail() {
    const { id } = useParams();
    const { can } = useAuth();
    const [project, setProject] = useState(null);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState('overview');

    const canEdit = can('projects.edit');

    const fetchProject = async () => {
        try {
            const res = await projectService.get(id);
            setProject(res.data);
        } catch {
            setProject(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchProject();
    }, [id]);

    if (loading) return <LoadingSpinner />;
    if (!project) {
        return (
            <div className="py-12 text-center text-gray-500">
                Project not found.{' '}
                <Link to="/projects" className="text-primary-600 hover:underline">Back to projects</Link>
            </div>
        );
    }

    return (
        <div>
            {/* Header */}
            <div className="mb-6">
                <Link to="/projects" className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <HiOutlineArrowLeft className="h-4 w-4" /> Back to Projects
                </Link>
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">{project.name}</h1>
                        <p className="text-sm text-gray-500">Code: {project.code}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className={`rounded-full px-3 py-1 text-sm font-medium ${statusColors[project.status]}`}>
                            {project.status?.replace('_', ' ')}
                        </span>
                        {canEdit && (
                            <Link
                                to={`/projects/${id}/edit`}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                <HiOutlinePencil className="h-4 w-4" /> Edit
                            </Link>
                        )}
                    </div>
                </div>
            </div>

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

            {/* Tab Content */}
            {activeTab === 'overview' && <OverviewTab project={project} />}
            {activeTab === 'tasks' && <TasksTab project={project} canEdit={canEdit} onRefresh={fetchProject} />}
            {activeTab === 'milestones' && <MilestonesTab project={project} canEdit={canEdit} onRefresh={fetchProject} />}
            {activeTab === 'timeline' && <TimelineTab project={project} />}
            {activeTab === 'site-logs' && <SiteLogsTab project={project} canEdit={canEdit} onRefresh={fetchProject} />}
            {activeTab === 'documents' && <DocumentsTab project={project} canEdit={canEdit} onRefresh={fetchProject} />}
            {activeTab === 'calendar' && <CalendarTab project={project} canEdit={canEdit} onRefresh={fetchProject} />}
            {activeTab === 'discussions' && <ProjectDiscussions projectId={project.id} />}
        </div>
    );
}

// ─── Overview Tab ──────────────────────────────────────────────
function OverviewTab({ project }) {
    return (
        <div className="grid gap-6 lg:grid-cols-3">
            <div className="lg:col-span-2 space-y-6">
                {project.description && (
                    <Card title="Description">
                        <p className="text-sm text-gray-600 whitespace-pre-wrap">{project.description}</p>
                    </Card>
                )}
                <Card title="Progress">
                    <div className="mb-2 flex items-center justify-between text-sm text-gray-600">
                        <span>Overall completion</span>
                        <span className="font-medium">{project.progress}%</span>
                    </div>
                    <div className="h-3 w-full rounded-full bg-gray-200">
                        <div className="h-3 rounded-full bg-primary-500 transition-all" style={{ width: `${project.progress}%` }} />
                    </div>
                </Card>
                {/* Quick Stats */}
                <div className="grid gap-4 sm:grid-cols-4">
                    <StatCard label="Tasks" value={project.tasks_count ?? project.tasks?.length ?? 0} />
                    <StatCard label="Milestones" value={project.milestones_count ?? project.milestones?.length ?? 0} />
                    <StatCard label="Documents" value={project.documents_count ?? project.documents?.length ?? 0} />
                    <StatCard label="Events" value={project.calendar_events_count ?? project.calendar_events?.length ?? 0} />
                </div>
            </div>
            <div className="space-y-6">
                <Card title="Details">
                    <div className="space-y-4">
                        {project.client && (
                            <DetailRow icon={HiOutlineUser} label="Client" value={project.client.company_name} />
                        )}
                        {project.manager && (
                            <DetailRow icon={HiOutlineUser} label="Project Manager" value={project.manager.full_name} />
                        )}
                        {(project.start_date || project.end_date) && (
                            <DetailRow icon={HiOutlineCalendar} label="Timeline" value={`${project.start_date || 'TBD'} - ${project.end_date || 'TBD'}`} />
                        )}
                        {project.budget > 0 && (
                            <div className="flex items-start gap-3">
                                <HiOutlineCurrencyDollar className="mt-0.5 h-5 w-5 text-gray-400" />
                                <div>
                                    <p className="text-xs text-gray-500">Budget</p>
                                    <p className="text-sm font-medium text-gray-900">RM {Number(project.budget).toLocaleString('en-MY')}</p>
                                    <p className="text-xs text-gray-500">Spent: RM {Number(project.spent).toLocaleString('en-MY')}</p>
                                </div>
                            </div>
                        )}
                        {project.location && (
                            <DetailRow icon={HiOutlineLocationMarker} label="Location" value={project.location} />
                        )}
                    </div>
                </Card>
                {project.members?.length > 0 && (
                    <Card title="Team">
                        <div className="space-y-3">
                            {project.members.map((member) => (
                                <div key={member.id} className="flex items-center gap-3">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-xs font-medium text-primary-700">
                                        {member.first_name?.[0]}{member.last_name?.[0]}
                                    </div>
                                    <p className="text-sm font-medium text-gray-900">{member.full_name}</p>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </div>
    );
}

// ─── Tasks Tab ─────────────────────────────────────────────────
function TasksTab({ project, canEdit, onRefresh }) {
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ title: '', description: '', priority: 'medium', due_date: '', assigned_to: '' });
    const [saving, setSaving] = useState(false);

    const handleCreate = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            await taskService.create({ ...form, project_id: project.id, assigned_to: form.assigned_to || null });
            toast.success('Task created');
            setShowForm(false);
            setForm({ title: '', description: '', priority: 'medium', due_date: '', assigned_to: '' });
            onRefresh();
        } catch {
            toast.error('Failed to create task');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Card
            title="Tasks"
            action={canEdit && (
                <button onClick={() => setShowForm(!showForm)} className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                    <HiOutlinePlus className="h-4 w-4" /> Add Task
                </button>
            )}
        >
            {showForm && (
                <form onSubmit={handleCreate} className="mb-4 rounded-lg border border-primary-200 bg-primary-50 p-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <input
                            type="text"
                            placeholder="Task title"
                            value={form.title}
                            onChange={(e) => setForm({ ...form, title: e.target.value })}
                            required
                            className="sm:col-span-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        />
                        <select
                            value={form.priority}
                            onChange={(e) => setForm({ ...form, priority: e.target.value })}
                            className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <input type="date" value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <select
                            value={form.assigned_to}
                            onChange={(e) => setForm({ ...form, assigned_to: e.target.value })}
                            className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        >
                            <option value="">Assign to...</option>
                            {project.members?.map((m) => (
                                <option key={m.id} value={m.id}>{m.full_name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="mt-3 flex justify-end gap-2">
                        <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50">
                            {saving ? 'Creating...' : 'Create'}
                        </button>
                    </div>
                </form>
            )}
            {!project.tasks?.length ? (
                <p className="py-6 text-center text-sm text-gray-400">No tasks yet</p>
            ) : (
                <div className="divide-y">
                    {project.tasks.map((task) => (
                        <div key={task.id} className="flex items-center justify-between py-3">
                            <div>
                                <p className="text-sm font-medium text-gray-900">{task.title}</p>
                                <p className="text-xs text-gray-500">
                                    {task.assignee?.first_name ? `${task.assignee.first_name} ${task.assignee.last_name}` : 'Unassigned'}
                                    {task.due_date && <span className="ml-2">Due: {task.due_date}</span>}
                                </p>
                            </div>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[task.status]}`}>
                                {task.status?.replace('_', ' ')}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

// ─── Milestones Tab ────────────────────────────────────────────
function MilestonesTab({ project, canEdit, onRefresh }) {
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ title: '', description: '', due_date: '' });
    const [saving, setSaving] = useState(false);

    const handleCreate = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            await projectService.createMilestone(project.id, form);
            toast.success('Milestone created');
            setShowForm(false);
            setForm({ title: '', description: '', due_date: '' });
            onRefresh();
        } catch {
            toast.error('Failed to create milestone');
        } finally {
            setSaving(false);
        }
    };

    const handleStatusChange = async (milestoneId, status) => {
        try {
            await projectService.updateMilestone(project.id, milestoneId, { status });
            toast.success('Milestone updated');
            onRefresh();
        } catch {
            toast.error('Failed to update');
        }
    };

    const milestones = project.milestones || [];

    return (
        <Card
            title="Milestones"
            action={canEdit && (
                <button onClick={() => setShowForm(!showForm)} className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                    <HiOutlinePlus className="h-4 w-4" /> Add Milestone
                </button>
            )}
        >
            {showForm && (
                <form onSubmit={handleCreate} className="mb-4 rounded-lg border border-primary-200 bg-primary-50 p-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <input type="text" placeholder="Milestone title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required className="sm:col-span-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <input type="date" value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <textarea placeholder="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={2} className="sm:col-span-2 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div className="mt-3 flex justify-end gap-2">
                        <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Creating...' : 'Create'}</button>
                    </div>
                </form>
            )}
            {milestones.length === 0 ? (
                <p className="py-6 text-center text-sm text-gray-400">No milestones yet</p>
            ) : (
                <div className="space-y-3">
                    {milestones.map((ms) => (
                        <div key={ms.id} className="rounded-lg border border-gray-200 p-4">
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <HiOutlineFlag className={`h-4 w-4 ${ms.status === 'completed' ? 'text-green-500' : ms.status === 'overdue' ? 'text-red-500' : 'text-gray-400'}`} />
                                        <h4 className="text-sm font-semibold text-gray-900">{ms.title}</h4>
                                    </div>
                                    {ms.description && <p className="mt-1 text-xs text-gray-500">{ms.description}</p>}
                                    <div className="mt-2 flex items-center gap-3 text-xs text-gray-500">
                                        {ms.due_date && <span>Due: {ms.due_date}</span>}
                                        {ms.completed_date && <span>Completed: {ms.completed_date}</span>}
                                    </div>
                                </div>
                                {canEdit && (
                                    <select
                                        value={ms.status}
                                        onChange={(e) => handleStatusChange(ms.id, e.target.value)}
                                        className="rounded border border-gray-300 px-2 py-1 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                )}
                            </div>
                            <div className="mt-2">
                                <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
                                    <span>Progress</span>
                                    <span>{ms.progress}%</span>
                                </div>
                                <div className="h-1.5 w-full rounded-full bg-gray-200">
                                    <div className={`h-1.5 rounded-full transition-all ${ms.status === 'completed' ? 'bg-green-500' : 'bg-primary-500'}`} style={{ width: `${ms.progress}%` }} />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

// ─── Timeline Tab ──────────────────────────────────────────────
function TimelineTab({ project }) {
    const items = useMemo(() => {
        const all = [];
        // Add tasks
        (project.tasks || []).forEach((t) => {
            if (t.start_date || t.due_date) {
                all.push({ type: 'task', id: t.id, title: t.title, start: t.start_date, end: t.due_date, status: t.status });
            }
        });
        // Add milestones
        (project.milestones || []).forEach((m) => {
            if (m.due_date) {
                all.push({ type: 'milestone', id: m.id, title: m.title, start: m.due_date, end: m.due_date, status: m.status });
            }
        });
        // Add events
        (project.calendar_events || []).forEach((e) => {
            all.push({ type: 'event', id: e.id, title: e.title, start: e.start_datetime?.split('T')[0], end: e.end_datetime?.split('T')[0] || e.start_datetime?.split('T')[0], status: e.status });
        });
        return all.sort((a, b) => (a.start || '').localeCompare(b.start || ''));
    }, [project]);

    // Find the date range for the project
    const projectStart = project.start_date || items[0]?.start;
    const projectEnd = project.end_date || items[items.length - 1]?.end;

    if (!projectStart || !projectEnd || items.length === 0) {
        return <Card title="Timeline"><p className="py-6 text-center text-sm text-gray-400">No timeline data available. Add tasks or milestones with dates.</p></Card>;
    }

    const startDate = new Date(projectStart);
    const endDate = new Date(projectEnd);
    const totalDays = Math.max(1, Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)));
    const todayOffset = Math.ceil((new Date() - startDate) / (1000 * 60 * 60 * 24));
    const todayPercent = Math.min(100, Math.max(0, (todayOffset / totalDays) * 100));

    const typeColors = { task: 'bg-primary-400', milestone: 'bg-amber-400', event: 'bg-purple-400' };
    const typeBadge = { task: 'Task', milestone: 'Milestone', event: 'Event' };

    return (
        <Card title="Timeline">
            {/* Date range header */}
            <div className="mb-4 flex items-center justify-between text-xs text-gray-500">
                <span>{projectStart}</span>
                <span>{projectEnd}</span>
            </div>
            <div className="relative">
                {/* Today marker */}
                {todayPercent > 0 && todayPercent < 100 && (
                    <div className="absolute top-0 bottom-0 z-10 w-0.5 bg-red-400" style={{ left: `${todayPercent}%` }}>
                        <span className="absolute -top-5 -translate-x-1/2 rounded bg-red-500 px-1.5 py-0.5 text-[10px] font-medium text-white">Today</span>
                    </div>
                )}
                {/* Items */}
                <div className="space-y-2">
                    {items.map((item) => {
                        const iStart = new Date(item.start);
                        const iEnd = new Date(item.end || item.start);
                        const left = Math.max(0, ((iStart - startDate) / (1000 * 60 * 60 * 24) / totalDays) * 100);
                        const width = Math.max(1, ((iEnd - iStart) / (1000 * 60 * 60 * 24) / totalDays) * 100 + (1 / totalDays) * 100);

                        return (
                            <div key={`${item.type}-${item.id}`} className="relative h-8 rounded bg-gray-50">
                                <div
                                    className={`absolute top-1 bottom-1 rounded ${typeColors[item.type]} ${item.status === 'completed' ? 'opacity-60' : 'opacity-90'}`}
                                    style={{ left: `${left}%`, width: `${Math.min(width, 100 - left)}%` }}
                                    title={`${item.title} (${item.start}${item.end !== item.start ? ` - ${item.end}` : ''})`}
                                >
                                    <span className="absolute inset-0 flex items-center px-2 text-[11px] font-medium text-white truncate">
                                        {item.title}
                                    </span>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
            {/* Legend */}
            <div className="mt-4 flex gap-4 text-xs text-gray-500">
                {Object.entries(typeColors).map(([type, color]) => (
                    <div key={type} className="flex items-center gap-1.5">
                        <div className={`h-3 w-3 rounded ${color}`} />
                        <span className="capitalize">{typeBadge[type]}</span>
                    </div>
                ))}
            </div>
        </Card>
    );
}

// ─── Site Logs Tab ─────────────────────────────────────────────
const MACHINERY_TYPES = ['Excavator', 'Bulldozer', 'Crane', 'Compactor', 'Loader', 'Dump Truck', 'Generator', 'Other'];
const WEATHER_CONDITIONS = [
    { value: 'rain_start', label: 'Rain Start' },
    { value: 'rain_stop', label: 'Rain Stop' },
    { value: 'overcast', label: 'Overcast (Mendung)' },
    { value: 'clear', label: 'Clear / Sunny (Cerah)' },
];
const weatherConditionLabel = (v) => WEATHER_CONDITIONS.find((c) => c.value === v)?.label || v;
const emptySiteLogForm = () => ({
    log_date: new Date().toISOString().split('T')[0],
    weather: '',
    workers_count: '', work_performed: '', materials_used: '', issues: '', safety_notes: '',
    machinery: [],
    weather_events: [],
});

function SiteLogsTab({ project, canEdit, onRefresh }) {
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState(emptySiteLogForm());
    const [saving, setSaving] = useState(false);
    const [reportMonth, setReportMonth] = useState(new Date().toISOString().slice(0, 7));

    const addMachinery = () => setForm((p) => ({ ...p, machinery: [...p.machinery, { machinery_type: MACHINERY_TYPES[0], quantity: 1 }] }));
    const removeMachinery = (idx) => setForm((p) => ({ ...p, machinery: p.machinery.filter((_, i) => i !== idx) }));
    const updateMachinery = (idx, field, value) => setForm((p) => ({ ...p, machinery: p.machinery.map((m, i) => i === idx ? { ...m, [field]: value } : m) }));

    const addWeatherEvent = () => setForm((p) => ({ ...p, weather_events: [...p.weather_events, { condition: WEATHER_CONDITIONS[0].value, event_time: '' }] }));
    const removeWeatherEvent = (idx) => setForm((p) => ({ ...p, weather_events: p.weather_events.filter((_, i) => i !== idx) }));
    const updateWeatherEvent = (idx, field, value) => setForm((p) => ({ ...p, weather_events: p.weather_events.map((w, i) => i === idx ? { ...w, [field]: value } : w) }));

    const handleCreate = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            await projectService.createSiteLog(project.id, {
                ...form,
                workers_count: form.workers_count ? Number(form.workers_count) : 0,
            });
            toast.success('Site log created');
            setShowForm(false);
            setForm(emptySiteLogForm());
            onRefresh();
        } catch {
            toast.error('Failed to create site log');
        } finally {
            setSaving(false);
        }
    };

    const logs = project.site_logs || [];

    return (
        <Card
            title="Site Logs"
            action={
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-1.5">
                        <input type="month" value={reportMonth} onChange={(e) => setReportMonth(e.target.value)} className="rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <a
                            href={projectService.getSiteLogReportUrl(project.id, reportMonth)}
                            className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                            title="Download monthly report (PDF)"
                        >
                            <HiOutlineDocumentDownload className="h-3.5 w-3.5" /> Monthly Report
                        </a>
                    </div>
                    {canEdit && (
                        <button onClick={() => setShowForm(!showForm)} className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                            <HiOutlinePlus className="h-4 w-4" /> New Entry
                        </button>
                    )}
                </div>
            }
        >
            {showForm && (
                <form onSubmit={handleCreate} className="mb-4 rounded-lg border border-primary-200 bg-primary-50 p-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <input type="date" value={form.log_date} onChange={(e) => setForm({ ...form, log_date: e.target.value })} required className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <select value={form.weather} onChange={(e) => setForm({ ...form, weather: e.target.value })} className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <option value="">Weather</option>
                            <option value="sunny">Sunny</option>
                            <option value="cloudy">Cloudy</option>
                            <option value="rainy">Rainy</option>
                            <option value="stormy">Stormy</option>
                            <option value="windy">Windy</option>
                            <option value="other">Other</option>
                        </select>
                        <input type="number" placeholder="Workers on site" value={form.workers_count} onChange={(e) => setForm({ ...form, workers_count: e.target.value })} min="0" className="sm:col-span-2 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>

                    <div className="mt-3 rounded-lg border border-gray-200 bg-white p-3">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-xs font-semibold uppercase text-gray-500">Weather Times</p>
                            <button type="button" onClick={addWeatherEvent} className="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700">
                                <HiOutlinePlus className="h-3.5 w-3.5" /> Add
                            </button>
                        </div>
                        <p className="mb-2 text-[11px] text-gray-400">Record each time the weather changes — e.g. rain starts, stops, then starts again later.</p>
                        {form.weather_events.length === 0 ? (
                            <p className="text-xs text-gray-400">No weather times recorded</p>
                        ) : (
                            <div className="space-y-2">
                                {form.weather_events.map((w, i) => (
                                    <div key={i} className="flex items-center gap-2">
                                        <input type="time" value={w.event_time} onChange={(e) => updateWeatherEvent(i, 'event_time', e.target.value)} className="w-32 rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                        <select value={w.condition} onChange={(e) => updateWeatherEvent(i, 'condition', e.target.value)} className="flex-1 rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                            {WEATHER_CONDITIONS.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                                        </select>
                                        <button type="button" onClick={() => removeWeatherEvent(i)} className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineX className="h-3.5 w-3.5" /></button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="mt-3 rounded-lg border border-gray-200 bg-white p-3">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-xs font-semibold uppercase text-gray-500">Machinery</p>
                            <button type="button" onClick={addMachinery} className="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700">
                                <HiOutlinePlus className="h-3.5 w-3.5" /> Add
                            </button>
                        </div>
                        {form.machinery.length === 0 ? (
                            <p className="text-xs text-gray-400">No machinery recorded</p>
                        ) : (
                            <div className="space-y-2">
                                {form.machinery.map((m, i) => (
                                    <div key={i} className="flex items-center gap-2">
                                        <select value={m.machinery_type} onChange={(e) => updateMachinery(i, 'machinery_type', e.target.value)} className="flex-1 rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                            {MACHINERY_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
                                        </select>
                                        <input type="number" min="1" value={m.quantity} onChange={(e) => updateMachinery(i, 'quantity', e.target.value)} className="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                        <button type="button" onClick={() => removeMachinery(i)} className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineX className="h-3.5 w-3.5" /></button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <textarea placeholder="Work performed" value={form.work_performed} onChange={(e) => setForm({ ...form, work_performed: e.target.value })} rows={2} className="sm:col-span-2 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <textarea placeholder="Materials used" value={form.materials_used} onChange={(e) => setForm({ ...form, materials_used: e.target.value })} rows={2} className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <textarea placeholder="Issues / Concerns" value={form.issues} onChange={(e) => setForm({ ...form, issues: e.target.value })} rows={2} className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <textarea placeholder="Safety notes" value={form.safety_notes} onChange={(e) => setForm({ ...form, safety_notes: e.target.value })} rows={2} className="sm:col-span-2 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div className="mt-3 flex justify-end gap-2">
                        <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Creating...' : 'Create'}</button>
                    </div>
                </form>
            )}
            {logs.length === 0 ? (
                <p className="py-6 text-center text-sm text-gray-400">No site logs yet</p>
            ) : (
                <div className="space-y-3">
                    {logs.map((log) => (
                        <div key={log.id} className="rounded-lg border border-gray-200 p-4">
                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-3 text-xs text-gray-500">
                                    <span className="text-sm font-semibold text-gray-900">{log.log_date}</span>
                                    {log.weather && <span>{weatherIcons[log.weather] || ''} {log.weather}</span>}
                                    {log.workers_count > 0 && <span>{log.workers_count} workers</span>}
                                </div>
                                <span className="text-xs text-gray-400">
                                    {log.logger?.first_name} {log.logger?.last_name}
                                </span>
                            </div>
                            {log.weather_events?.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {log.weather_events.map((w) => (
                                        <span key={w.id} className="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] text-blue-700">{weatherConditionLabel(w.condition)} {w.event_time}</span>
                                    ))}
                                </div>
                            )}
                            {log.machinery?.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {log.machinery.map((m) => (
                                        <span key={m.id} className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600">{m.machinery_type} x{m.quantity}</span>
                                    ))}
                                </div>
                            )}
                            {log.work_performed && (
                                <div className="mt-2">
                                    <p className="text-xs font-medium text-gray-600">Work Performed:</p>
                                    <p className="text-xs text-gray-500 whitespace-pre-wrap">{log.work_performed}</p>
                                </div>
                            )}
                            {log.issues && (
                                <div className="mt-2">
                                    <p className="text-xs font-medium text-red-600">Issues:</p>
                                    <p className="text-xs text-gray-500 whitespace-pre-wrap">{log.issues}</p>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

// ─── Documents Tab ─────────────────────────────────────────────
function DocumentsTab({ project, canEdit, onRefresh }) {
    const [uploading, setUploading] = useState(false);
    const [showUpload, setShowUpload] = useState(false);
    const [form, setForm] = useState({ title: '', category: 'other', file: null });

    const handleUpload = async (e) => {
        e.preventDefault();
        if (!form.file) return toast.error('Please select a file');
        setUploading(true);
        try {
            const fd = new FormData();
            fd.append('title', form.title || form.file.name);
            fd.append('category', form.category);
            fd.append('file', form.file);
            await projectService.uploadDocument(project.id, fd);
            toast.success('Document uploaded');
            setShowUpload(false);
            setForm({ title: '', category: 'other', file: null });
            onRefresh();
        } catch {
            toast.error('Upload failed');
        } finally {
            setUploading(false);
        }
    };

    const handleDelete = async (docId) => {
        if (!confirm('Delete this document?')) return;
        try {
            await projectService.deleteDocument(project.id, docId);
            toast.success('Document deleted');
            onRefresh();
        } catch {
            toast.error('Failed to delete');
        }
    };

    const docs = project.documents || [];
    const categoryLabels = { drawing: 'Drawing', contract: 'Contract', permit: 'Permit', report: 'Report', photo: 'Photo', specification: 'Spec', invoice: 'Invoice', other: 'Other' };
    const categoryColors = { drawing: 'bg-blue-100 text-blue-700', contract: 'bg-purple-100 text-purple-700', permit: 'bg-green-100 text-green-700', report: 'bg-yellow-100 text-yellow-700', photo: 'bg-pink-100 text-pink-700', specification: 'bg-indigo-100 text-indigo-700', invoice: 'bg-orange-100 text-orange-700', other: 'bg-gray-100 text-gray-700' };

    return (
        <Card
            title="Documents"
            action={canEdit && (
                <button onClick={() => setShowUpload(!showUpload)} className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                    <HiOutlineUpload className="h-4 w-4" /> Upload
                </button>
            )}
        >
            {showUpload && (
                <form onSubmit={handleUpload} className="mb-4 rounded-lg border border-primary-200 bg-primary-50 p-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <input type="text" placeholder="Document title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="sm:col-span-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            {Object.entries(categoryLabels).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                        </select>
                        <input type="file" onChange={(e) => setForm({ ...form, file: e.target.files[0] })} className="rounded-lg border border-gray-300 px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-sm file:font-medium file:text-primary-700" />
                    </div>
                    <div className="mt-3 flex justify-end gap-2">
                        <button type="button" onClick={() => setShowUpload(false)} className="rounded-lg border px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={uploading} className="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50">{uploading ? 'Uploading...' : 'Upload'}</button>
                    </div>
                </form>
            )}
            {docs.length === 0 ? (
                <p className="py-6 text-center text-sm text-gray-400">No documents yet</p>
            ) : (
                <div className="divide-y">
                    {docs.map((doc) => (
                        <div key={doc.id} className="flex items-center justify-between py-3">
                            <div className="flex items-center gap-3 min-w-0">
                                <HiOutlineDocumentText className="h-8 w-8 text-gray-400 shrink-0" />
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-gray-900 truncate">{doc.title}</p>
                                    <div className="flex items-center gap-2 text-xs text-gray-500">
                                        <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-medium ${categoryColors[doc.category]}`}>
                                            {categoryLabels[doc.category]}
                                        </span>
                                        <span>{formatFileSize(doc.file_size)}</span>
                                        <span>{doc.uploader?.first_name} {doc.uploader?.last_name}</span>
                                    </div>
                                </div>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                <a
                                    href={projectService.getDocumentDownloadUrl(project.id, doc.id)}
                                    className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                    title="Download"
                                >
                                    <HiOutlineDocumentDownload className="h-4 w-4" />
                                </a>
                                {canEdit && (
                                    <button onClick={() => handleDelete(doc.id)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                        <HiOutlineTrash className="h-4 w-4" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

// ─── Calendar Tab ──────────────────────────────────────────────
const emptyEventForm = () => ({ title: '', type: 'meeting', start_datetime: '', end_datetime: '', location: '', description: '' });
const toLocalInput = (dt) => (dt ? String(dt).slice(0, 16) : '');

function CalendarTab({ project, canEdit }) {
    const [events, setEvents] = useState(project.calendar_events || []);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [form, setForm] = useState(emptyEventForm());
    const [saving, setSaving] = useState(false);

    const fetchEvents = useCallback(async () => {
        setLoading(true);
        try {
            const params = {};
            if (search) params.search = search;
            const res = await projectService.getEvents(project.id, params);
            setEvents(res.data || []);
        } catch {
            setEvents([]);
        } finally {
            setLoading(false);
        }
    }, [project.id, search]);

    useEffect(() => {
        const t = setTimeout(() => fetchEvents(), 300);
        return () => clearTimeout(t);
    }, [fetchEvents]);

    const openCreate = () => {
        setEditingId(null);
        setForm(emptyEventForm());
        setShowForm(true);
    };

    const openEdit = (event) => {
        setEditingId(event.id);
        setForm({
            title: event.title || '',
            type: event.type || 'meeting',
            start_datetime: toLocalInput(event.start_datetime),
            end_datetime: toLocalInput(event.end_datetime),
            location: event.location || '',
            description: event.description || '',
        });
        setShowForm(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            if (editingId) {
                await projectService.updateEvent(project.id, editingId, form);
                toast.success('Event updated');
            } else {
                await projectService.createEvent(project.id, form);
                toast.success('Event created');
            }
            setShowForm(false);
            setEditingId(null);
            setForm(emptyEventForm());
            fetchEvents();
        } catch {
            toast.error(editingId ? 'Failed to update event' : 'Failed to create event');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (eventId) => {
        try {
            await projectService.deleteEvent(project.id, eventId);
            toast.success('Event deleted');
            fetchEvents();
        } catch {
            toast.error('Failed to delete');
        }
    };

    const typeColors = { meeting: 'bg-blue-100 text-blue-700', inspection: 'bg-orange-100 text-orange-700', deadline: 'bg-red-100 text-red-700', milestone: 'bg-amber-100 text-amber-700', other: 'bg-gray-100 text-gray-700' };

    return (
        <Card
            title="Calendar Events"
            action={canEdit && (
                <button onClick={() => (showForm ? setShowForm(false) : openCreate())} className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700">
                    <HiOutlinePlus className="h-4 w-4" /> New Event
                </button>
            )}
        >
            <div className="relative mb-4 max-w-xs">
                <HiOutlineSearch className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    type="text"
                    placeholder="Search events..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                />
            </div>

            {showForm && (
                <form onSubmit={handleSubmit} className="mb-4 rounded-lg border border-primary-200 bg-primary-50 p-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <input type="text" placeholder="Event title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required className="sm:col-span-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <option value="meeting">Meeting</option>
                            <option value="inspection">Inspection</option>
                            <option value="deadline">Deadline</option>
                            <option value="milestone">Milestone</option>
                            <option value="other">Other</option>
                        </select>
                        <input type="text" placeholder="Location" value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <input type="datetime-local" value={form.start_datetime} onChange={(e) => setForm({ ...form, start_datetime: e.target.value })} required className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <input type="datetime-local" value={form.end_datetime} onChange={(e) => setForm({ ...form, end_datetime: e.target.value })} className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <textarea placeholder="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={2} className="sm:col-span-2 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div className="mt-3 flex justify-end gap-2">
                        <button type="button" onClick={() => { setShowForm(false); setEditingId(null); }} className="rounded-lg border px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : editingId ? 'Update' : 'Create'}</button>
                    </div>
                </form>
            )}
            {loading ? (
                <LoadingSpinner />
            ) : events.length === 0 ? (
                <p className="py-6 text-center text-sm text-gray-400">No events scheduled</p>
            ) : (
                <div className="space-y-3">
                    {events.map((event) => (
                        <div key={event.id} className="flex items-start justify-between rounded-lg border border-gray-200 p-4">
                            <div className="flex gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50">
                                    <HiOutlineCalendar className="h-5 w-5 text-primary-500" />
                                </div>
                                <div>
                                    <h4 className="text-sm font-semibold text-gray-900">{event.title}</h4>
                                    <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                        <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-medium ${typeColors[event.type]}`}>
                                            {event.type}
                                        </span>
                                        <span>{formatDateTime(event.start_datetime)}</span>
                                        {event.end_datetime && <span>- {formatDateTime(event.end_datetime)}</span>}
                                        {event.location && <span>@ {event.location}</span>}
                                    </div>
                                    {event.description && <p className="mt-1 text-xs text-gray-500">{event.description}</p>}
                                </div>
                            </div>
                            {canEdit && (
                                <div className="flex shrink-0 items-center gap-1">
                                    <button onClick={() => openEdit(event)} className="rounded p-1 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Edit">
                                        <HiOutlinePencil className="h-4 w-4" />
                                    </button>
                                    <button onClick={() => handleDelete(event.id)} className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                        <HiOutlineTrash className="h-4 w-4" />
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

// ─── Shared Components ────────────────────────────────────────
function Card({ title, action, children }) {
    return (
        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            {(title || action) && (
                <div className="mb-4 flex items-center justify-between">
                    {title && <h2 className="text-lg font-semibold text-gray-900">{title}</h2>}
                    {action}
                </div>
            )}
            {children}
        </div>
    );
}

function StatCard({ label, value }) {
    return (
        <div className="rounded-xl bg-white p-4 text-center shadow-sm ring-1 ring-gray-200">
            <p className="text-2xl font-bold text-gray-900">{value}</p>
            <p className="text-xs text-gray-500">{label}</p>
        </div>
    );
}

function DetailRow({ icon: Icon, label, value }) {
    return (
        <div className="flex items-start gap-3">
            <Icon className="mt-0.5 h-5 w-5 text-gray-400" />
            <div>
                <p className="text-xs text-gray-500">{label}</p>
                <p className="text-sm font-medium text-gray-900">{value}</p>
            </div>
        </div>
    );
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

function formatDateTime(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
