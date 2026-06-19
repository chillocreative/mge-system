import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import taskService from '@/services/taskService';
import projectService from '@/services/projectService';
import apiClient from '@/services/apiClient';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus, HiOutlineClipboardList, HiOutlineSearch, HiOutlineX,
    HiOutlineEye, HiOutlinePaperClip, HiOutlineDownload,
} from 'react-icons/hi';

const statusColors = {
    pending: 'bg-gray-100 text-gray-700',
    in_progress: 'bg-yellow-100 text-yellow-700',
    in_review: 'bg-purple-100 text-purple-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};
const priorityColors = {
    low: 'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-600',
    high: 'bg-orange-100 text-orange-600',
    critical: 'bg-red-100 text-red-600',
};
const emptyForm = { title: '', project_id: '', description: '', priority: 'medium', status: 'pending', start_date: '', due_date: '' };
const initials = (name = '') => name.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();

export default function Tasks() {
    const { can } = useAuth();
    const [tasks, setTasks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('');
    const [projects, setProjects] = useState([]);
    const [users, setUsers] = useState([]);

    // create form
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState(emptyForm);
    const [assigneeIds, setAssigneeIds] = useState([]);
    const [files, setFiles] = useState([]);
    const [userSearch, setUserSearch] = useState('');
    const [showUserList, setShowUserList] = useState(false);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    // detail
    const [detail, setDetail] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);

    const fetchTasks = async () => {
        setLoading(true);
        try {
            const params = {};
            if (statusFilter) params.status = statusFilter;
            const response = await taskService.list(params);
            setTasks(response.data?.data || []);
        } catch {
            setTasks([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchTasks(); }, [statusFilter]);

    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
        apiClient.get('/users', { params: { per_page: 200, status: 'active' } })
            .then((r) => setUsers(r.data?.data?.data || [])).catch(() => {});
    }, []);

    const openCreate = () => {
        setForm(emptyForm); setAssigneeIds([]); setFiles([]); setUserSearch(''); setErrors({}); setShowForm(true);
    };

    const toggleAssignee = (id) => {
        setAssigneeIds((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
    };

    const addFiles = (e) => {
        const picked = Array.from(e.target.files);
        setFiles((prev) => [...prev, ...picked]);
        e.target.value = ''; // let the same file be re-picked / add more in another go
    };
    const removeFile = (idx) => setFiles((prev) => prev.filter((_, i) => i !== idx));

    const filteredUsers = users.filter((u) => u.full_name?.toLowerCase().includes(userSearch.toLowerCase()));
    const selectedUsers = users.filter((u) => assigneeIds.includes(u.id));

    const submit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const fd = new FormData();
            fd.append('title', form.title);
            fd.append('project_id', form.project_id);
            if (form.description) fd.append('description', form.description);
            fd.append('priority', form.priority);
            fd.append('status', form.status);
            if (form.start_date) fd.append('start_date', form.start_date);
            if (form.due_date) fd.append('due_date', form.due_date);
            assigneeIds.forEach((id) => fd.append('assignee_ids[]', id));
            files.forEach((f) => fd.append('attachments[]', f));
            await taskService.create(fd);
            toast.success('Task created');
            setShowForm(false);
            fetchTasks();
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors || {});
                toast.error('Please fix the highlighted fields');
            } else {
                toast.error(err.response?.data?.message || 'Failed to create task');
            }
        } finally {
            setSaving(false);
        }
    };

    const openDetail = async (id) => {
        setDetailLoading(true);
        setDetail({ loading: true });
        try {
            const res = await taskService.get(id);
            setDetail(res.data);
        } catch {
            toast.error('Failed to load task');
            setDetail(null);
        } finally {
            setDetailLoading(false);
        }
    };

    const fieldClass = (name) =>
        `w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1 ${errors[name] ? 'border-red-300 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500'}`;

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Tasks</h1>
                    <p className="text-sm text-gray-500">Project tasks and assignments</p>
                </div>
                {can('tasks.create') && (
                    <button onClick={openCreate} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                        <HiOutlinePlus className="h-5 w-5" /> New Task
                    </button>
                )}
            </div>

            <div className="mb-6">
                <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="in_review">In Review</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : tasks.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineClipboardList className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No tasks found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Task</th>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Project</th>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Assignees</th>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Priority</th>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                    <th className="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Created By</th>
                                    <th className="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {tasks.map((task) => (
                                    <tr key={task.id} className="hover:bg-gray-50">
                                        <td className="px-5 py-4"><p className="text-sm font-medium text-gray-900">{task.title}</p></td>
                                        <td className="px-5 py-4"><p className="text-sm text-gray-600">{task.project?.name || '-'}</p></td>
                                        <td className="px-5 py-4">
                                            {task.assignees?.length ? (
                                                <div className="flex -space-x-2">
                                                    {task.assignees.slice(0, 4).map((a) => (
                                                        <span key={a.id} title={a.full_name} className="flex h-7 w-7 items-center justify-center rounded-full bg-primary-100 text-[10px] font-bold text-primary-700 ring-2 ring-white">
                                                            {initials(a.full_name)}
                                                        </span>
                                                    ))}
                                                    {task.assignees.length > 4 && <span className="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-500 ring-2 ring-white">+{task.assignees.length - 4}</span>}
                                                </div>
                                            ) : <span className="text-xs text-gray-400">Unassigned</span>}
                                        </td>
                                        <td className="px-5 py-4"><span className={`rounded-full px-2 py-0.5 text-xs font-medium ${priorityColors[task.priority]}`}>{task.priority}</span></td>
                                        <td className="px-5 py-4"><span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[task.status]}`}>{task.status.replace('_', ' ')}</span></td>
                                        <td className="px-5 py-4 text-sm text-gray-600">{task.creator?.full_name || '-'}</td>
                                        <td className="px-5 py-4 text-right">
                                            <button onClick={() => openDetail(task.id)} title="View" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><HiOutlineEye className="h-4 w-4" /></button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* New Task modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowForm(false)}>
                    <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">New Task</h3>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Title *</label>
                                <input type="text" value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} required className={fieldClass('title')} placeholder="Task title" />
                                {errors.title && <p className="mt-1 text-xs text-red-500">{errors.title[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Project *</label>
                                <select value={form.project_id} onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))} required className={fieldClass('project_id')}>
                                    <option value="">Select project...</option>
                                    {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                </select>
                                {errors.project_id && <p className="mt-1 text-xs text-red-500">{errors.project_id[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                <textarea rows={2} value={form.description} onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))} className={fieldClass('description')} />
                            </div>

                            {/* Searchable multi-select assignees */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Assigned To</label>
                                {selectedUsers.length > 0 && (
                                    <div className="mb-2 flex flex-wrap gap-1.5">
                                        {selectedUsers.map((u) => (
                                            <span key={u.id} className="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">
                                                {u.full_name}
                                                <button type="button" onClick={() => toggleAssignee(u.id)} className="hover:text-primary-900"><HiOutlineX className="h-3 w-3" /></button>
                                            </span>
                                        ))}
                                    </div>
                                )}
                                <div className="relative">
                                    <HiOutlineSearch className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <input type="text" value={userSearch} onChange={(e) => setUserSearch(e.target.value)}
                                        onFocus={() => setShowUserList(true)}
                                        placeholder="Search users to assign..."
                                        className="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                {showUserList && (
                                    <div className="mt-1 max-h-44 overflow-y-auto rounded-lg border border-gray-200">
                                        {filteredUsers.length === 0 ? (
                                            <p className="px-3 py-2 text-xs text-gray-400">No users</p>
                                        ) : filteredUsers.map((u) => (
                                            <label key={u.id} className="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50">
                                                <input type="checkbox" checked={assigneeIds.includes(u.id)} onChange={() => toggleAssignee(u.id)} className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                <span className="text-gray-700">{u.full_name}</span>
                                                <span className="ml-auto text-xs text-gray-400">{u.roles?.[0]}</span>
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Priority</label>
                                    <select value={form.priority} onChange={(e) => setForm((p) => ({ ...p, priority: e.target.value }))} className={fieldClass('priority')}>
                                        <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="critical">Critical</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Start Date</label>
                                    <input type="date" value={form.start_date} onChange={(e) => setForm((p) => ({ ...p, start_date: e.target.value }))} className={fieldClass('start_date')} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Due Date</label>
                                    <input type="date" value={form.due_date} onChange={(e) => setForm((p) => ({ ...p, due_date: e.target.value }))} className={fieldClass('due_date')} />
                                    {errors.due_date && <p className="mt-1 text-xs text-red-500">{errors.due_date[0]}</p>}
                                </div>
                            </div>

                            {/* Multiple attachments */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Attachments <span className="font-normal text-gray-400">(add multiple — click again to add more)</span></label>
                                <input type="file" multiple onChange={addFiles}
                                    className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100" />
                                {files.length > 0 && (
                                    <ul className="mt-2 space-y-1">
                                        {files.map((f, i) => (
                                            <li key={i} className="flex items-center justify-between gap-2 rounded bg-gray-50 px-2 py-1 text-xs text-gray-600">
                                                <span className="flex min-w-0 items-center gap-2"><HiOutlinePaperClip className="h-3.5 w-3.5 shrink-0" /><span className="truncate">{f.name}</span></span>
                                                <button type="button" onClick={() => removeFile(i)} className="shrink-0 text-gray-400 hover:text-red-600"><HiOutlineX className="h-3.5 w-3.5" /></button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {errors['attachments.0'] && <p className="mt-1 text-xs text-red-500">{errors['attachments.0'][0]}</p>}
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Creating...' : 'Create Task'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Detail modal */}
            {detail && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setDetail(null)}>
                    <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        {detailLoading || detail.loading ? <LoadingSpinner /> : (
                            <>
                                <div className="mb-4 flex items-start justify-between">
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900">{detail.title}</h3>
                                        <p className="text-sm text-gray-500">{detail.project?.name}</p>
                                    </div>
                                    <button onClick={() => setDetail(null)} className="rounded p-1 text-gray-400 hover:bg-gray-100"><HiOutlineX className="h-5 w-5" /></button>
                                </div>

                                <div className="mb-4 flex flex-wrap gap-2">
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[detail.status]}`}>{detail.status?.replace('_', ' ')}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${priorityColors[detail.priority]}`}>{detail.priority}</span>
                                    {detail.due_date && <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Due {detail.due_date}</span>}
                                </div>

                                {detail.description && <p className="mb-4 text-sm text-gray-600">{detail.description}</p>}

                                <div className="mb-4">
                                    <h4 className="mb-1.5 text-xs font-bold uppercase text-gray-400">Assignees</h4>
                                    {detail.assignees?.length ? (
                                        <div className="flex flex-wrap gap-1.5">
                                            {detail.assignees.map((a) => (
                                                <span key={a.id} className="rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700">{a.full_name}</span>
                                            ))}
                                        </div>
                                    ) : <p className="text-sm text-gray-400">Unassigned</p>}
                                    <p className="mt-2 text-xs text-gray-500">Created by <span className="font-medium text-gray-700">{detail.creator?.full_name || '-'}</span></p>
                                </div>

                                {detail.attachments?.length > 0 && (
                                    <div className="mb-4">
                                        <h4 className="mb-1.5 text-xs font-bold uppercase text-gray-400">Attachments</h4>
                                        <ul className="space-y-1">
                                            {detail.attachments.map((a) => (
                                                <li key={a.id}>
                                                    <a href={a.download_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700">
                                                        <HiOutlineDownload className="h-4 w-4" /> {a.file_name}
                                                    </a>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                <div>
                                    <h4 className="mb-2 text-xs font-bold uppercase text-gray-400">Activity Log</h4>
                                    {detail.activities?.length ? (
                                        <ol className="space-y-3 border-l border-gray-200 pl-4">
                                            {detail.activities.map((act) => (
                                                <li key={act.id} className="relative">
                                                    <span className="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-primary-400 ring-2 ring-white" />
                                                    <p className="text-sm text-gray-700"><span className="font-medium">{act.user}</span> {act.description}</p>
                                                    <p className="text-[11px] text-gray-400">{new Date(act.created_at).toLocaleString()}</p>
                                                </li>
                                            ))}
                                        </ol>
                                    ) : <p className="text-sm text-gray-400">No activity yet</p>}
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
