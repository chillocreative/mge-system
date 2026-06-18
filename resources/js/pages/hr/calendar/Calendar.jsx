import { useState, useEffect, useMemo, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import apiClient from '@/services/apiClient';
import calendarService from '@/services/calendarService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineChevronLeft,
    HiOutlineChevronRight,
    HiOutlineRefresh,
    HiOutlineTrash,
    HiOutlineCalendar,
    HiOutlineLink,
    HiOutlineX,
} from 'react-icons/hi';

const TYPE_OPTIONS = ['meeting', 'holiday', 'leave', 'deadline', 'training', 'other'];

const TYPE_COLORS = {
    meeting: 'bg-blue-100 text-blue-700 ring-blue-200',
    holiday: 'bg-red-100 text-red-700 ring-red-200',
    leave: 'bg-amber-100 text-amber-700 ring-amber-200',
    deadline: 'bg-rose-100 text-rose-700 ring-rose-200',
    training: 'bg-indigo-100 text-indigo-700 ring-indigo-200',
    other: 'bg-primary-100 text-primary-700 ring-primary-200',
};

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function toDateKey(d) {
    // Local YYYY-MM-DD key.
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function startOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function buildWeeks(viewDate) {
    // First grid cell is the Sunday on/before the 1st; render 6 weeks (42 cells).
    const first = startOfMonth(viewDate);
    const gridStart = new Date(first);
    gridStart.setDate(first.getDate() - first.getDay());

    const weeks = [];
    let cursor = new Date(gridStart);
    for (let w = 0; w < 6; w++) {
        const days = [];
        for (let d = 0; d < 7; d++) {
            days.push(new Date(cursor));
            cursor.setDate(cursor.getDate() + 1);
        }
        weeks.push(days);
    }
    return weeks;
}

function emptyForm(dateKey) {
    const base = dateKey || toDateKey(new Date());
    return {
        id: null,
        title: '',
        description: '',
        type: 'meeting',
        start_datetime: `${base}T09:00`,
        end_datetime: '',
        all_day: false,
        location: '',
        employee_id: '',
        project_id: '',
    };
}

export default function Calendar() {
    const { can } = useAuth();
    const canManage = can('calendar.manage');

    const [viewDate, setViewDate] = useState(startOfMonth(new Date()));
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    const [projects, setProjects] = useState([]);
    const [employees, setEmployees] = useState([]);

    const [google, setGoogle] = useState({ configured: false, connected: false });
    const [syncing, setSyncing] = useState(false);

    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState(emptyForm());

    const weeks = useMemo(() => buildWeeks(viewDate), [viewDate]);
    const rangeStart = weeks[0][0];
    const rangeEnd = weeks[weeks.length - 1][6];

    const fetchEvents = useCallback(async () => {
        setLoading(true);
        try {
            const res = await calendarService.listEvents({
                start: `${toDateKey(rangeStart)} 00:00:00`,
                end: `${toDateKey(rangeEnd)} 23:59:59`,
            });
            setEvents(res.data || []);
        } catch {
            setEvents([]);
        } finally {
            setLoading(false);
        }
    }, [rangeStart, rangeEnd]);

    useEffect(() => {
        fetchEvents();
    }, [fetchEvents]);

    useEffect(() => {
        calendarService.googleStatus().then((r) => setGoogle(r.data || { configured: false, connected: false })).catch(() => {});
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
        // Employees endpoint is optional; fail silently if not present.
        apiClient.get('/employees', { params: { per_page: 200 } })
            .then((r) => setEmployees(r.data?.data?.data || r.data?.data || []))
            .catch(() => {});
    }, []);

    // Group events by local day key for chip rendering.
    const eventsByDay = useMemo(() => {
        const map = {};
        for (const ev of events) {
            if (!ev.start_datetime) continue;
            const key = toDateKey(new Date(ev.start_datetime.replace(' ', 'T')));
            (map[key] ||= []).push(ev);
        }
        return map;
    }, [events]);

    const monthLabel = viewDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    const todayKey = toDateKey(new Date());

    const prevMonth = () => setViewDate((d) => new Date(d.getFullYear(), d.getMonth() - 1, 1));
    const nextMonth = () => setViewDate((d) => new Date(d.getFullYear(), d.getMonth() + 1, 1));
    const goToday = () => setViewDate(startOfMonth(new Date()));

    const openCreate = (dateKey) => {
        if (!canManage) return;
        setForm(emptyForm(dateKey));
        setShowForm(true);
    };

    const openEdit = (ev) => {
        setForm({
            id: ev.id,
            title: ev.title || '',
            description: ev.description || '',
            type: ev.type || 'other',
            start_datetime: (ev.start_datetime || '').replace(' ', 'T').slice(0, 16),
            end_datetime: ev.end_datetime ? ev.end_datetime.replace(' ', 'T').slice(0, 16) : '',
            all_day: !!ev.all_day,
            location: ev.location || '',
            employee_id: ev.employee_id || '',
            project_id: ev.project_id || '',
        });
        setShowForm(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!canManage) return;
        setSaving(true);
        try {
            const payload = {
                title: form.title,
                description: form.description || null,
                type: form.type,
                start_datetime: form.start_datetime.replace('T', ' ') + ':00',
                end_datetime: form.end_datetime ? form.end_datetime.replace('T', ' ') + ':00' : null,
                all_day: form.all_day,
                location: form.location || null,
                employee_id: form.employee_id || null,
                project_id: form.project_id || null,
            };
            if (form.id) {
                await calendarService.updateEvent(form.id, payload);
                toast.success('Event updated');
            } else {
                await calendarService.createEvent(payload);
                toast.success('Event created');
            }
            setShowForm(false);
            fetchEvents();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save event');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!form.id || !confirm('Delete this event?')) return;
        try {
            await calendarService.deleteEvent(form.id);
            toast.success('Event deleted');
            setShowForm(false);
            fetchEvents();
        } catch {
            toast.error('Failed to delete event');
        }
    };

    const handleSync = async () => {
        setSyncing(true);
        try {
            const res = await calendarService.googleSync();
            toast.success(res.message || 'Synced');
            fetchEvents();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Sync failed');
        } finally {
            setSyncing(false);
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Calendar</h1>
                    <p className="text-sm text-gray-500">Company events, holidays, leave and deadlines</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {/* Google Calendar */}
                    {!google.configured ? (
                        <span className="group relative">
                            <button
                                disabled
                                className="inline-flex cursor-not-allowed items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-400"
                            >
                                <HiOutlineLink className="h-5 w-5" />
                                Connect Google Calendar
                            </button>
                            <span className="pointer-events-none absolute left-1/2 top-full z-10 mt-1 -translate-x-1/2 whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100">
                                Add Google credentials to enable
                            </span>
                        </span>
                    ) : google.connected ? (
                        <button
                            onClick={handleSync}
                            disabled={syncing}
                            className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        >
                            <HiOutlineRefresh className={`h-5 w-5 ${syncing ? 'animate-spin' : ''}`} />
                            {syncing ? 'Syncing...' : 'Sync Google'}
                        </button>
                    ) : (
                        <a
                            href={calendarService.getConnectUrl()}
                            className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <HiOutlineLink className="h-5 w-5" />
                            Connect Google Calendar
                        </a>
                    )}

                    {canManage && (
                        <button
                            onClick={() => openCreate()}
                            className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                        >
                            <HiOutlinePlus className="h-5 w-5" />
                            New Event
                        </button>
                    )}
                </div>
            </div>

            {/* Month navigation */}
            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <button onClick={prevMonth} className="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50">
                        <HiOutlineChevronLeft className="h-5 w-5" />
                    </button>
                    <button onClick={nextMonth} className="rounded-lg border border-gray-300 p-2 text-gray-600 hover:bg-gray-50">
                        <HiOutlineChevronRight className="h-5 w-5" />
                    </button>
                    <button onClick={goToday} className="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Today
                    </button>
                </div>
                <h2 className="text-lg font-semibold text-gray-900">{monthLabel}</h2>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    {/* Weekday header */}
                    <div className="grid grid-cols-7 border-b bg-gray-50">
                        {WEEKDAYS.map((d) => (
                            <div key={d} className="px-2 py-2 text-center text-xs font-semibold uppercase text-gray-500">
                                {d}
                            </div>
                        ))}
                    </div>

                    {/* Weeks */}
                    {weeks.map((week, wi) => (
                        <div key={wi} className="grid grid-cols-7 border-b last:border-b-0">
                            {week.map((day) => {
                                const key = toDateKey(day);
                                const inMonth = day.getMonth() === viewDate.getMonth();
                                const dayEvents = eventsByDay[key] || [];
                                return (
                                    <div
                                        key={key}
                                        onClick={() => openCreate(key)}
                                        className={`min-h-[96px] border-r p-1.5 last:border-r-0 ${inMonth ? 'bg-white' : 'bg-gray-50'} ${canManage ? 'cursor-pointer hover:bg-primary-50/40' : ''}`}
                                    >
                                        <div className="mb-1 flex justify-end">
                                            <span
                                                className={`flex h-6 w-6 items-center justify-center rounded-full text-xs ${
                                                    key === todayKey
                                                        ? 'bg-primary-600 font-semibold text-white'
                                                        : inMonth
                                                            ? 'text-gray-700'
                                                            : 'text-gray-400'
                                                }`}
                                            >
                                                {day.getDate()}
                                            </span>
                                        </div>
                                        <div className="space-y-1">
                                            {dayEvents.slice(0, 3).map((ev) => (
                                                <button
                                                    key={ev.id}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        openEdit(ev);
                                                    }}
                                                    title={ev.title}
                                                    className={`block w-full truncate rounded px-1.5 py-0.5 text-left text-xs font-medium ring-1 ${TYPE_COLORS[ev.type] || TYPE_COLORS.other}`}
                                                >
                                                    {ev.title}
                                                </button>
                                            ))}
                                            {dayEvents.length > 3 && (
                                                <p className="px-1 text-xs text-gray-400">+{dayEvents.length - 3} more</p>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ))}
                </div>
            )}

            {/* Event Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowForm(false)}>
                    <div className="mx-4 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">
                                {form.id ? 'Edit Event' : 'New Event'}
                            </h3>
                            <button onClick={() => setShowForm(false)} className="rounded p-1 text-gray-400 hover:bg-gray-100">
                                <HiOutlineX className="h-5 w-5" />
                            </button>
                        </div>

                        {!canManage ? (
                            <div className="space-y-2 text-sm text-gray-600">
                                <p className="font-semibold text-gray-900">{form.title}</p>
                                {form.location && <p>Location: {form.location}</p>}
                                {form.description && <p>{form.description}</p>}
                            </div>
                        ) : (
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Title *</label>
                                    <input
                                        type="text"
                                        value={form.title}
                                        onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))}
                                        required
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Type</label>
                                        <select
                                            value={form.type}
                                            onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))}
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                        >
                                            {TYPE_OPTIONS.map((t) => (
                                                <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="flex items-end">
                                        <label className="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                            <input
                                                type="checkbox"
                                                checked={form.all_day}
                                                onChange={(e) => setForm((p) => ({ ...p, all_day: e.target.checked }))}
                                                className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                            />
                                            All day
                                        </label>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Start *</label>
                                        <input
                                            type="datetime-local"
                                            value={form.start_datetime}
                                            onChange={(e) => setForm((p) => ({ ...p, start_datetime: e.target.value }))}
                                            required
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">End</label>
                                        <input
                                            type="datetime-local"
                                            value={form.end_datetime}
                                            onChange={(e) => setForm((p) => ({ ...p, end_datetime: e.target.value }))}
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Location</label>
                                    <input
                                        type="text"
                                        value={form.location}
                                        onChange={(e) => setForm((p) => ({ ...p, location: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Employee</label>
                                        <select
                                            value={form.employee_id}
                                            onChange={(e) => setForm((p) => ({ ...p, employee_id: e.target.value }))}
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                        >
                                            <option value="">None</option>
                                            {employees.map((emp) => (
                                                <option key={emp.id} value={emp.id}>
                                                    {emp.full_name || `${emp.first_name || ''} ${emp.last_name || ''}`.trim()}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Project</label>
                                        <select
                                            value={form.project_id}
                                            onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))}
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                        >
                                            <option value="">None</option>
                                            {projects.map((p) => (
                                                <option key={p.id} value={p.id}>{p.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                    <textarea
                                        rows={2}
                                        value={form.description}
                                        onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div className="flex items-center justify-between pt-2">
                                    {form.id ? (
                                        <button
                                            type="button"
                                            onClick={handleDelete}
                                            className="inline-flex items-center gap-1 rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                                        >
                                            <HiOutlineTrash className="h-4 w-4" />
                                            Delete
                                        </button>
                                    ) : <span />}
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setShowForm(false)}
                                            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={saving}
                                            className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                                        >
                                            {saving ? 'Saving...' : form.id ? 'Update' : 'Create'}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
