import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import meetingService from '@/services/meetingService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlinePlus, HiOutlineTrash, HiOutlineUserAdd } from 'react-icons/hi';

const emptyAction = { item: '', assigned_to: '', due_date: '', status: 'open' };

export default function MeetingForm() {
    const { id } = useParams();
    const navigate = useNavigate();
    const isEdit = !!id;

    const [loading, setLoading] = useState(isEdit);
    const [saving, setSaving] = useState(false);
    const [projects, setProjects] = useState([]);
    const [employees, setEmployees] = useState([]);
    const [errors, setErrors] = useState({});

    const [form, setForm] = useState({
        title: '',
        meeting_date: new Date().toISOString().split('T')[0],
        meeting_time: '',
        location: '',
        project_id: '',
        agenda: '',
        notes: '',
        status: 'draft',
        attendees: [],
        action_items: [],
    });
    const [files, setFiles] = useState([]);

    // Attendee inputs
    const [attendeeEmployee, setAttendeeEmployee] = useState('');
    const [attendeeName, setAttendeeName] = useState('');

    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
        meetingService.listEmployees().then((r) => setEmployees(r.data || [])).catch(() => {});
    }, []);

    useEffect(() => {
        if (!isEdit) return;
        (async () => {
            setLoading(true);
            try {
                const res = await meetingService.get(id);
                const m = res.data;
                setForm({
                    title: m.title || '',
                    meeting_date: m.meeting_date || '',
                    meeting_time: m.meeting_time || '',
                    location: m.location || '',
                    project_id: m.project_id || '',
                    agenda: m.agenda || '',
                    notes: m.notes || '',
                    status: m.status || 'draft',
                    attendees: m.attendees || [],
                    action_items: (m.action_items || []).map((a) => ({
                        item: a.item || '',
                        assigned_to: a.assigned_to || '',
                        due_date: a.due_date || '',
                        status: a.status || 'open',
                    })),
                });
            } catch {
                toast.error('Failed to load meeting');
                navigate('/meetings');
            } finally {
                setLoading(false);
            }
        })();
    }, [id]);

    const updateField = (field, value) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        setErrors((prev) => ({ ...prev, [field]: undefined }));
    };

    // ── Attendees ──
    const addEmployeeAttendee = () => {
        if (!attendeeEmployee) return;
        const emp = employees.find((e) => String(e.id) === String(attendeeEmployee));
        if (!emp) return;
        const name = `${emp.first_name} ${emp.last_name}`.trim();
        setForm((prev) => ({ ...prev, attendees: [...prev.attendees, { employee_id: emp.id, name }] }));
        setAttendeeEmployee('');
    };

    const addNameAttendee = () => {
        const name = attendeeName.trim();
        if (!name) return;
        setForm((prev) => ({ ...prev, attendees: [...prev.attendees, { name }] }));
        setAttendeeName('');
    };

    const removeAttendee = (index) => {
        setForm((prev) => ({ ...prev, attendees: prev.attendees.filter((_, i) => i !== index) }));
    };

    // ── Action items ──
    const addAction = () => setForm((prev) => ({ ...prev, action_items: [...prev.action_items, { ...emptyAction }] }));
    const removeAction = (index) => setForm((prev) => ({ ...prev, action_items: prev.action_items.filter((_, i) => i !== index) }));
    const updateAction = (index, field, value) => {
        setForm((prev) => {
            const action_items = [...prev.action_items];
            action_items[index] = { ...action_items[index], [field]: value };
            return { ...prev, action_items };
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const fd = new FormData();
            fd.append('title', form.title);
            fd.append('meeting_date', form.meeting_date);
            if (form.meeting_time) fd.append('meeting_time', form.meeting_time);
            if (form.location) fd.append('location', form.location);
            if (form.project_id) fd.append('project_id', form.project_id);
            if (form.agenda) fd.append('agenda', form.agenda);
            if (form.notes) fd.append('notes', form.notes);
            fd.append('status', form.status);

            form.attendees.forEach((a, i) => {
                if (a.employee_id) fd.append(`attendees[${i}][employee_id]`, a.employee_id);
                if (a.user_id) fd.append(`attendees[${i}][user_id]`, a.user_id);
                if (a.name) fd.append(`attendees[${i}][name]`, a.name);
            });
            // A multipart request simply omits an "attendees[...]" key when the list is
            // empty, which is indistinguishable on the backend from "field not touched".
            // Send an explicit marker on edit so removing every attendee actually clears
            // the saved list instead of silently leaving the old one in place.
            if (isEdit && form.attendees.length === 0) fd.append('attendees_cleared', '1');

            const actionItemsToSend = form.action_items.filter((a) => a.item.trim());
            actionItemsToSend.forEach((a, i) => {
                fd.append(`action_items[${i}][item]`, a.item);
                if (a.assigned_to) fd.append(`action_items[${i}][assigned_to]`, a.assigned_to);
                if (a.due_date) fd.append(`action_items[${i}][due_date]`, a.due_date);
                fd.append(`action_items[${i}][status]`, a.status || 'open');
            });
            if (isEdit && actionItemsToSend.length === 0) fd.append('action_items_cleared', '1');

            files.forEach((f) => fd.append('files[]', f));

            if (isEdit) {
                await meetingService.update(id, fd);
                toast.success('Meeting updated');
            } else {
                await meetingService.create(fd);
                toast.success('Meeting created');
            }
            navigate('/meetings');
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors || {});
                toast.error('Please fix the validation errors');
            } else {
                toast.error(err.response?.data?.message || 'Failed to save meeting');
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;

    return (
        <div className="mx-auto max-w-4xl">
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">{isEdit ? 'Edit Meeting' : 'New Meeting'}</h1>
                <p className="text-sm text-gray-500">{isEdit ? 'Update meeting minutes' : 'Record meeting minutes and action items'}</p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Details */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Meeting Details</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Title *</label>
                            <input
                                type="text"
                                value={form.title}
                                onChange={(e) => updateField('title', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {errors.title && <p className="mt-1 text-xs text-red-500">{errors.title[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Date *</label>
                            <input
                                type="date"
                                value={form.meeting_date}
                                onChange={(e) => updateField('meeting_date', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {errors.meeting_date && <p className="mt-1 text-xs text-red-500">{errors.meeting_date[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Time</label>
                            <input
                                type="time"
                                value={form.meeting_time}
                                onChange={(e) => updateField('meeting_time', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Location</label>
                            <input
                                type="text"
                                value={form.location}
                                onChange={(e) => updateField('location', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Project</label>
                            <select
                                value={form.project_id}
                                onChange={(e) => updateField('project_id', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="">No Project</option>
                                {projects.map((p) => (
                                    <option key={p.id} value={p.id}>{p.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                            <select
                                value={form.status}
                                onChange={(e) => updateField('status', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Attendees */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Attendees</h2>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <div className="flex flex-1 gap-2">
                            <select
                                value={attendeeEmployee}
                                onChange={(e) => setAttendeeEmployee(e.target.value)}
                                className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="">Select employee...</option>
                                {employees.map((e) => (
                                    <option key={e.id} value={e.id}>{e.first_name} {e.last_name}</option>
                                ))}
                            </select>
                            <button
                                type="button"
                                onClick={addEmployeeAttendee}
                                className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50"
                            >
                                <HiOutlineUserAdd className="h-4 w-4" /> Add
                            </button>
                        </div>
                        <div className="flex flex-1 gap-2">
                            <input
                                type="text"
                                placeholder="Or type a name..."
                                value={attendeeName}
                                onChange={(e) => setAttendeeName(e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addNameAttendee(); } }}
                                className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            <button
                                type="button"
                                onClick={addNameAttendee}
                                className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50"
                            >
                                <HiOutlinePlus className="h-4 w-4" /> Add
                            </button>
                        </div>
                    </div>
                    {form.attendees.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {form.attendees.map((a, i) => (
                                <span key={i} className="inline-flex items-center gap-1 rounded-full bg-primary-50 px-3 py-1 text-sm text-primary-700">
                                    {a.name || `Employee #${a.employee_id}`}
                                    <button type="button" onClick={() => removeAttendee(i)} className="text-primary-400 hover:text-red-500">
                                        <HiOutlineTrash className="h-3.5 w-3.5" />
                                    </button>
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                {/* Agenda & Notes */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Agenda & Notes</h2>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Agenda</label>
                            <textarea
                                rows={3}
                                value={form.agenda}
                                onChange={(e) => updateField('agenda', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Minutes / Notes</label>
                            <textarea
                                rows={5}
                                value={form.notes}
                                onChange={(e) => updateField('notes', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                    </div>
                </div>

                {/* Action Items */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase text-gray-500">Action Items</h2>
                        <button
                            type="button"
                            onClick={addAction}
                            className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
                        >
                            <HiOutlinePlus className="h-4 w-4" /> Add Item
                        </button>
                    </div>
                    {form.action_items.length === 0 ? (
                        <p className="text-sm text-gray-400">No action items added.</p>
                    ) : (
                        <div className="space-y-3">
                            {form.action_items.map((a, index) => (
                                <div key={index} className="grid gap-2 rounded-lg border border-gray-100 p-3 sm:grid-cols-12 sm:gap-3">
                                    <input
                                        placeholder="Action item"
                                        value={a.item}
                                        onChange={(e) => updateAction(index, 'item', e.target.value)}
                                        className="col-span-12 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:col-span-5"
                                    />
                                    <select
                                        value={a.assigned_to}
                                        onChange={(e) => updateAction(index, 'assigned_to', e.target.value)}
                                        className="col-span-6 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:col-span-3"
                                    >
                                        <option value="">Unassigned</option>
                                        {employees.map((e) => (
                                            <option key={e.id} value={e.id}>{e.first_name} {e.last_name}</option>
                                        ))}
                                    </select>
                                    <input
                                        type="date"
                                        value={a.due_date}
                                        onChange={(e) => updateAction(index, 'due_date', e.target.value)}
                                        className="col-span-4 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:col-span-2"
                                    />
                                    <select
                                        value={a.status}
                                        onChange={(e) => updateAction(index, 'status', e.target.value)}
                                        className="col-span-6 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:col-span-1"
                                    >
                                        <option value="open">Open</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="done">Done</option>
                                    </select>
                                    <div className="col-span-2 flex items-center justify-end sm:col-span-1">
                                        <button
                                            type="button"
                                            onClick={() => removeAction(index)}
                                            className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-500"
                                        >
                                            <HiOutlineTrash className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Files */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Attachments (PDF / DOC / DOCX)</h2>
                    <input
                        type="file"
                        multiple
                        accept=".pdf,.doc,.docx"
                        onChange={(e) => setFiles(Array.from(e.target.files || []))}
                        className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100"
                    />
                    {files.length > 0 && (
                        <ul className="mt-2 list-inside list-disc text-xs text-gray-500">
                            {files.map((f, i) => <li key={i}>{f.name}</li>)}
                        </ul>
                    )}
                    {isEdit && <p className="mt-2 text-xs text-gray-400">New files are added to existing attachments.</p>}
                </div>

                {/* Actions */}
                <div className="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={() => navigate('/meetings')}
                        className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={saving}
                        className="rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50"
                    >
                        {saving ? 'Saving...' : isEdit ? 'Update Meeting' : 'Create Meeting'}
                    </button>
                </div>
            </form>
        </div>
    );
}
