import { useState, useEffect } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import projectService from '@/services/projectService';
import apiClient from '@/services/apiClient';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineArrowLeft, HiOutlinePaperClip, HiOutlineX, HiOutlinePlus } from 'react-icons/hi';

const SUPER_ADMIN_ROLE = 'Admin & HR';
const emptyClientForm = { company_name: '', contact_person: '', email: '', phone: '' };
const emptyManagerForm = { full_name: '', email: '', password: '', phone: '', role: '' };
const userLabel = (u) => `${u.first_name ?? ''} ${u.last_name ?? ''}`.trim() || u.full_name || u.email;

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
    const { can, user } = useAuth();
    const canGrantSuper = !!user?.is_protected; // only the System Administrator

    const [form, setForm] = useState(defaultForm);
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [clients, setClients] = useState([]);
    const [users, setUsers] = useState([]);
    const [roles, setRoles] = useState([]);
    const [attachFiles, setAttachFiles] = useState([]);

    // Quick-add Client modal
    const [clientModalOpen, setClientModalOpen] = useState(false);
    const [clientForm, setClientForm] = useState(emptyClientForm);
    const [clientErrors, setClientErrors] = useState({});
    const [clientSaving, setClientSaving] = useState(false);

    // Quick-add Project Manager (user) modal
    const [managerModalOpen, setManagerModalOpen] = useState(false);
    const [managerForm, setManagerForm] = useState(emptyManagerForm);
    const [managerErrors, setManagerErrors] = useState({});
    const [managerSaving, setManagerSaving] = useState(false);

    // Team Members dropdown selection (before "Add")
    const [memberSelect, setMemberSelect] = useState('');

    const addFiles = (e) => {
        setAttachFiles((prev) => [...prev, ...Array.from(e.target.files)]);
        e.target.value = '';
    };
    const removeFile = (idx) => setAttachFiles((prev) => prev.filter((_, i) => i !== idx));

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
        // Roles are only needed for the quick-add manager modal.
        if (can('users.create')) {
            apiClient.get('/roles').then((r) => setRoles(r.data?.data || [])).catch(() => setRoles([]));
        }
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

    const addMember = (userId) => {
        const uid = Number(userId);
        if (!uid || form.member_ids.includes(uid)) return;
        setForm((prev) => ({ ...prev, member_ids: [...prev.member_ids, uid] }));
        setMemberSelect('');
    };
    const removeMember = (userId) => {
        setForm((prev) => ({ ...prev, member_ids: prev.member_ids.filter((id) => id !== userId) }));
    };

    // ── Quick-add Client ──
    const submitClient = async (e) => {
        e.preventDefault();
        setClientSaving(true);
        setClientErrors({});
        try {
            const res = await apiClient.post('/clients', {
                company_name: clientForm.company_name,
                contact_person: clientForm.contact_person,
                email: clientForm.email,
                phone: clientForm.phone || null,
            });
            const created = res.data?.data || res.data;
            setClients((prev) => [...prev, created]);
            setForm((prev) => ({ ...prev, client_id: created.id }));
            toast.success('Client added');
            setClientModalOpen(false);
            setClientForm(emptyClientForm);
        } catch (err) {
            if (err.response?.status === 422) setClientErrors(err.response.data?.errors || {});
            else toast.error(err.response?.data?.message || 'Failed to add client');
        } finally {
            setClientSaving(false);
        }
    };

    // ── Quick-add Project Manager (creates an active user) ──
    const submitManager = async (e) => {
        e.preventDefault();
        setManagerSaving(true);
        setManagerErrors({});
        try {
            const res = await apiClient.post('/users', {
                full_name: managerForm.full_name,
                email: managerForm.email,
                password: managerForm.password,
                phone: managerForm.phone || null,
                role: managerForm.role,
            });
            const created = res.data?.data || res.data;
            setUsers((prev) => [...prev, created]);
            setForm((prev) => ({ ...prev, manager_id: created.id }));
            toast.success('Project manager added');
            setManagerModalOpen(false);
            setManagerForm(emptyManagerForm);
        } catch (err) {
            if (err.response?.status === 422) setManagerErrors(err.response.data?.errors || {});
            else toast.error(err.response?.data?.message || 'Failed to add manager');
        } finally {
            setManagerSaving(false);
        }
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

            let targetId = id;
            if (isEdit) {
                await projectService.update(id, payload);
            } else {
                const res = await projectService.create(payload);
                targetId = res.data?.id;
            }

            if (attachFiles.length && targetId) {
                try {
                    await projectService.uploadDocumentsBulk(targetId, attachFiles);
                } catch {
                    toast.error('Project saved, but some attachments failed to upload');
                }
            }

            toast.success(isEdit ? 'Project updated' : 'Project created');
            navigate(`/projects/${targetId || ''}`);
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
                            <label className="mb-1 block text-sm font-medium text-gray-700">Budget (RM)</label>
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
                            <div className="mb-1 flex items-center justify-between">
                                <label className="block text-sm font-medium text-gray-700">Client</label>
                                {can('clients.create') && (
                                    <button
                                        type="button"
                                        onClick={() => { setClientErrors({}); setClientForm(emptyClientForm); setClientModalOpen(true); }}
                                        className="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700"
                                    >
                                        <HiOutlinePlus className="h-3.5 w-3.5" /> New
                                    </button>
                                )}
                            </div>
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
                            <div className="mb-1 flex items-center justify-between">
                                <label className="block text-sm font-medium text-gray-700">Project Manager</label>
                                {can('users.create') && (
                                    <button
                                        type="button"
                                        onClick={() => { setManagerErrors({}); setManagerForm(emptyManagerForm); setManagerModalOpen(true); }}
                                        className="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700"
                                    >
                                        <HiOutlinePlus className="h-3.5 w-3.5" /> New
                                    </button>
                                )}
                            </div>
                            <select
                                name="manager_id"
                                value={form.manager_id}
                                onChange={handleChange}
                                className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="">Select Manager</option>
                                {users.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {userLabel(u)}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                {/* Team Members */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-lg font-semibold text-gray-900">Team Members</h2>
                    {(() => {
                        const available = users.filter((u) => !form.member_ids.includes(u.id));
                        const selectedMembers = form.member_ids
                            .map((mid) => users.find((u) => u.id === mid))
                            .filter(Boolean);
                        return (
                            <>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <select
                                        value={memberSelect}
                                        onChange={(e) => setMemberSelect(e.target.value)}
                                        className="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        <option value="">
                                            {available.length ? 'Select a user to add...' : 'All users added'}
                                        </option>
                                        {available.map((u) => (
                                            <option key={u.id} value={u.id}>{userLabel(u)}</option>
                                        ))}
                                    </select>
                                    <button
                                        type="button"
                                        onClick={() => addMember(memberSelect)}
                                        disabled={!memberSelect}
                                        className="inline-flex shrink-0 items-center justify-center gap-1 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50"
                                    >
                                        <HiOutlinePlus className="h-4 w-4" /> Add
                                    </button>
                                </div>

                                {selectedMembers.length > 0 ? (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {selectedMembers.map((u) => (
                                            <span
                                                key={u.id}
                                                className="inline-flex items-center gap-2 rounded-full border border-primary-200 bg-primary-50 py-1 pl-1 pr-2 text-sm text-primary-800"
                                            >
                                                <span className="flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 text-xs font-medium text-primary-700">
                                                    {u.first_name?.[0]}{u.last_name?.[0]}
                                                </span>
                                                {userLabel(u)}
                                                <button
                                                    type="button"
                                                    onClick={() => removeMember(u.id)}
                                                    className="rounded-full p-0.5 text-primary-400 hover:bg-primary-100 hover:text-primary-700"
                                                    title="Remove"
                                                >
                                                    <HiOutlineX className="h-3.5 w-3.5" />
                                                </button>
                                            </span>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="mt-3 text-sm text-gray-400">No team members added yet</p>
                                )}
                            </>
                        );
                    })()}
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

                {/* Attachments */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-1 text-lg font-semibold text-gray-900">Attachments</h2>
                    <p className="mb-4 text-sm text-gray-500">Upload multiple files (drawings, contracts, etc.). These become the project's shared files, available across related modules.</p>
                    <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:border-primary-400 hover:text-primary-700">
                        <HiOutlinePaperClip className="h-4 w-4" />
                        Choose files (add multiple)
                        <input type="file" multiple className="hidden" onChange={addFiles} />
                    </label>
                    {attachFiles.length > 0 && (
                        <ul className="mt-3 space-y-1">
                            {attachFiles.map((f, i) => (
                                <li key={i} className="flex items-center justify-between gap-2 rounded bg-gray-50 px-3 py-1.5 text-sm text-gray-600">
                                    <span className="flex min-w-0 items-center gap-2"><HiOutlinePaperClip className="h-3.5 w-3.5 shrink-0" /><span className="truncate">{f.name}</span></span>
                                    <button type="button" onClick={() => removeFile(i)} className="shrink-0 text-gray-400 hover:text-red-600"><HiOutlineX className="h-4 w-4" /></button>
                                </li>
                            ))}
                        </ul>
                    )}
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

            {/* Quick-add Client modal */}
            {clientModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setClientModalOpen(false)}>
                    <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">New Client</h3>
                        <form onSubmit={submitClient} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Company Name *</label>
                                <input
                                    type="text" required value={clientForm.company_name}
                                    onChange={(e) => setClientForm((p) => ({ ...p, company_name: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                                {clientErrors.company_name && <p className="mt-1 text-xs text-red-500">{clientErrors.company_name[0]}</p>}
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Contact Person *</label>
                                    <input
                                        type="text" required value={clientForm.contact_person}
                                        onChange={(e) => setClientForm((p) => ({ ...p, contact_person: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                    {clientErrors.contact_person && <p className="mt-1 text-xs text-red-500">{clientErrors.contact_person[0]}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Phone</label>
                                    <input
                                        type="tel" value={clientForm.phone}
                                        onChange={(e) => setClientForm((p) => ({ ...p, phone: e.target.value }))}
                                        placeholder="+60 ..."
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Email *</label>
                                <input
                                    type="email" required value={clientForm.email}
                                    onChange={(e) => setClientForm((p) => ({ ...p, email: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                                {clientErrors.email && <p className="mt-1 text-xs text-red-500">{clientErrors.email[0]}</p>}
                            </div>
                            <p className="text-xs text-gray-400">Need more details? Add the full record later from the Clients page.</p>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setClientModalOpen(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={clientSaving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                    {clientSaving ? 'Saving...' : 'Add Client'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Quick-add Project Manager modal */}
            {managerModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setManagerModalOpen(false)}>
                    <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">New Project Manager</h3>
                        <form onSubmit={submitManager} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Full Name *</label>
                                <input
                                    type="text" required value={managerForm.full_name}
                                    onChange={(e) => setManagerForm((p) => ({ ...p, full_name: e.target.value }))}
                                    placeholder="Ahmad Razif"
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                                {managerErrors.full_name && <p className="mt-1 text-xs text-red-500">{managerErrors.full_name[0]}</p>}
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Email *</label>
                                    <input
                                        type="email" required value={managerForm.email}
                                        onChange={(e) => setManagerForm((p) => ({ ...p, email: e.target.value }))}
                                        placeholder="name@mge-eng.com"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                    {managerErrors.email && <p className="mt-1 text-xs text-red-500">{managerErrors.email[0]}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Password *</label>
                                    <input
                                        type="password" required minLength={8} value={managerForm.password}
                                        onChange={(e) => setManagerForm((p) => ({ ...p, password: e.target.value }))}
                                        placeholder="Min. 8 characters"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                    {managerErrors.password && <p className="mt-1 text-xs text-red-500">{managerErrors.password[0]}</p>}
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Phone</label>
                                    <input
                                        type="tel" value={managerForm.phone}
                                        onChange={(e) => setManagerForm((p) => ({ ...p, phone: e.target.value }))}
                                        placeholder="+60 1x-xxx xxxx"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Role *</label>
                                    <select
                                        required value={managerForm.role}
                                        onChange={(e) => setManagerForm((p) => ({ ...p, role: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    >
                                        <option value="">Select role...</option>
                                        {roles
                                            .filter((r) => r.name !== SUPER_ADMIN_ROLE || canGrantSuper)
                                            .map((r) => <option key={r.id} value={r.name}>{r.name}</option>)}
                                    </select>
                                    {managerErrors.role && <p className="mt-1 text-xs text-red-500">{managerErrors.role[0]}</p>}
                                </div>
                            </div>
                            <p className="text-xs text-gray-400">The new user is created as active and immediately selectable. Manage full details later from the Users page.</p>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setManagerModalOpen(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={managerSaving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                    {managerSaving ? 'Saving...' : 'Add Manager'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
