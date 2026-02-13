import { useState, useEffect } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import projectService from '@/services/projectService';
import apiClient from '@/services/apiClient';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineArrowLeft } from 'react-icons/hi';

const defaultForm = {
    name: '',
    code: '',
    description: '',
    client_id: '',
    manager_id: '',
    status: 'draft',
    priority: 'medium',
    start_date: '',
    end_date: '',
    budget: '',
    location: '',
    notes: '',
    member_ids: [],
};

export default function ProjectCreate() {
    const { id } = useParams();
    const navigate = useNavigate();
    const isEdit = Boolean(id);

    const [form, setForm] = useState(defaultForm);
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [clients, setClients] = useState([]);
    const [users, setUsers] = useState([]);

    useEffect(() => {
        const fetchOptions = async () => {
            try {
                const [clientsRes, usersRes] = await Promise.all([
                    apiClient.get('/clients', { params: { per_page: 100 } }),
                    apiClient.get('/users', { params: { per_page: 100 } }),
                ]);
                setClients(clientsRes.data?.data?.data || []);
                setUsers(usersRes.data?.data?.data || []);
            } catch {
                // silently fail - lists will be empty
            }
        };
        fetchOptions();
    }, []);

    useEffect(() => {
        if (!isEdit) return;
        setLoading(true);
        projectService
            .get(id)
            .then((res) => {
                const p = res.data;
                setForm({
                    name: p.name || '',
                    code: p.code || '',
                    description: p.description || '',
                    client_id: p.client?.id || '',
                    manager_id: p.manager?.id || '',
                    status: p.status || 'draft',
                    priority: p.priority || 'medium',
                    start_date: p.start_date || '',
                    end_date: p.end_date || '',
                    budget: p.budget || '',
                    location: p.location || '',
                    notes: p.notes || '',
                    member_ids: p.members?.map((m) => m.id) || [],
                });
            })
            .catch(() => toast.error('Failed to load project'))
            .finally(() => setLoading(false));
    }, [id, isEdit]);

    const handleChange = (e) => {
        const { name, value } = e.target;
        setForm((prev) => ({ ...prev, [name]: value }));
        if (errors[name]) setErrors((prev) => ({ ...prev, [name]: null }));
    };

    const handleMemberToggle = (userId) => {
        setForm((prev) => ({
            ...prev,
            member_ids: prev.member_ids.includes(userId)
                ? prev.member_ids.filter((id) => id !== userId)
                : [...prev.member_ids, userId],
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            const payload = {
                ...form,
                budget: form.budget ? Number(form.budget) : null,
                client_id: form.client_id || null,
                manager_id: form.manager_id || null,
            };

            if (isEdit) {
                await projectService.update(id, payload);
                toast.success('Project updated');
                navigate(`/projects/${id}`);
            } else {
                const res = await projectService.create(payload);
                toast.success('Project created');
                navigate(`/projects/${res.data?.id || ''}`);
            }
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data?.errors || {});
                toast.error('Please fix validation errors');
            } else {
                toast.error('Failed to save project');
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;

    return (
        <div className="mx-auto max-w-3xl">
            <div className="mb-6">
                <Link
                    to={isEdit ? `/projects/${id}` : '/projects'}
                    className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
                >
                    <HiOutlineArrowLeft className="h-4 w-4" />
                    {isEdit ? 'Back to Project' : 'Back to Projects'}
                </Link>
                <h1 className="text-2xl font-bold text-gray-900">
                    {isEdit ? 'Edit Project' : 'New Project'}
                </h1>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Basic Info */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Project Name <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="name"
                                value={form.name}
                                onChange={handleChange}
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Project Code <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="code"
                                value={form.code}
                                onChange={handleChange}
                                placeholder="e.g. PRJ-001"
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {errors.code && <p className="mt-1 text-xs text-red-500">{errors.code[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Location</label>
                            <input
                                type="text"
                                name="location"
                                value={form.location}
                                onChange={handleChange}
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                            <textarea
                                name="description"
                                value={form.description}
                                onChange={handleChange}
                                rows={3}
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                    </div>
                </div>

                {/* Status & Priority */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-lg font-semibold text-gray-900">Status & Timeline</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                            <select
                                name="status"
                                value={form.status}
                                onChange={handleChange}
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="draft">Draft</option>
                                <option value="planning">Planning</option>
                                <option value="in_progress">In Progress</option>
                                <option value="on_hold">On Hold</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Priority</label>
                            <select
                                name="priority"
                                value={form.priority}
                                onChange={handleChange}
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Start Date</label>
                            <input
                                type="date"
                                name="start_date"
                                value={form.start_date}
                                onChange={handleChange}
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">End Date</label>
                            <input
                                type="date"
                                name="end_date"
                                value={form.end_date}
                                onChange={handleChange}
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {errors.end_date && <p className="mt-1 text-xs text-red-500">{errors.end_date[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Budget (PKR)</label>
                            <input
                                type="number"
                                name="budget"
                                value={form.budget}
                                onChange={handleChange}
                                min="0"
                                step="0.01"
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                    </div>
                </div>

                {/* Client & Manager */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-lg font-semibold text-gray-900">Assignments</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Client</label>
                            <select
                                name="client_id"
                                value={form.client_id}
                                onChange={handleChange}
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="">Select Client</option>
                                {clients.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.company_name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Project Manager</label>
                            <select
                                name="manager_id"
                                value={form.manager_id}
                                onChange={handleChange}
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="">Select Manager</option>
                                {users.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.first_name} {u.last_name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                {/* Team Members */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-lg font-semibold text-gray-900">Team Members</h2>
                    {users.length === 0 ? (
                        <p className="text-sm text-gray-400">No users available</p>
                    ) : (
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {users.map((u) => (
                                <label
                                    key={u.id}
                                    className={`flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors ${
                                        form.member_ids.includes(u.id)
                                            ? 'border-primary-500 bg-primary-50'
                                            : 'border-gray-200 hover:bg-gray-50'
                                    }`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.member_ids.includes(u.id)}
                                        onChange={() => handleMemberToggle(u.id)}
                                        className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    />
                                    <div className="flex h-7 w-7 items-center justify-center rounded-full bg-primary-100 text-xs font-medium text-primary-700">
                                        {u.first_name?.[0]}{u.last_name?.[0]}
                                    </div>
                                    <span className="text-sm text-gray-900">
                                        {u.first_name} {u.last_name}
                                    </span>
                                </label>
                            ))}
                        </div>
                    )}
                </div>

                {/* Notes */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-lg font-semibold text-gray-900">Notes</h2>
                    <textarea
                        name="notes"
                        value={form.notes}
                        onChange={handleChange}
                        rows={3}
                        placeholder="Internal notes..."
                        className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                </div>

                {/* Submit */}
                <div className="flex justify-end gap-3">
                    <Link
                        to={isEdit ? `/projects/${id}` : '/projects'}
                        className="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={saving}
                        className="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50"
                    >
                        {saving ? 'Saving...' : isEdit ? 'Update Project' : 'Create Project'}
                    </button>
                </div>
            </form>
        </div>
    );
}
