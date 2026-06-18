import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import meetingService from '@/services/meetingService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineCalendar,
    HiOutlineDocumentText,
    HiOutlineLocationMarker,
    HiOutlineTrash,
} from 'react-icons/hi';

const statusColors = {
    draft: 'bg-gray-100 text-gray-600',
    published: 'bg-green-100 text-green-700',
    archived: 'bg-yellow-100 text-yellow-700',
};

export default function Meetings() {
    const { can } = useAuth();
    const navigate = useNavigate();
    const [meetings, setMeetings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [pagination, setPagination] = useState({});

    const fetchMeetings = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            if (dateFrom) params.date_from = dateFrom;
            if (dateTo) params.date_to = dateTo;
            const res = await meetingService.list(params);
            setMeetings(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setMeetings([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchMeetings(), 400);
        return () => clearTimeout(timer);
    }, [search, statusFilter, dateFrom, dateTo]);

    const handleDelete = async (id) => {
        if (!confirm('Delete this meeting record?')) return;
        try {
            await meetingService.delete(id);
            toast.success('Meeting deleted');
            fetchMeetings();
        } catch {
            toast.error('Failed to delete meeting');
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Meeting Minutes</h1>
                    <p className="text-sm text-gray-500">Record and track meeting minutes and action items</p>
                </div>
                {can('meetings.create') && (
                    <button
                        onClick={() => navigate('/meetings/create')}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        New Meeting
                    </button>
                )}
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search meetings..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                </div>
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                </select>
                <input
                    type="date"
                    value={dateFrom}
                    onChange={(e) => setDateFrom(e.target.value)}
                    title="From date"
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                />
                <input
                    type="date"
                    value={dateTo}
                    onChange={(e) => setDateTo(e.target.value)}
                    title="To date"
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                />
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : meetings.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineDocumentText className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No meetings found</p>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {meetings.map((m) => (
                        <Link
                            key={m.id}
                            to={`/meetings/${m.id}`}
                            className="group relative flex flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:shadow-md"
                        >
                            <div className="mb-2 flex items-start justify-between gap-2">
                                <h3 className="text-base font-semibold text-gray-900 group-hover:text-primary-700">{m.title}</h3>
                                <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[m.status] || statusColors.draft}`}>
                                    {m.status}
                                </span>
                            </div>
                            <div className="mt-1 space-y-1 text-sm text-gray-500">
                                <p className="flex items-center gap-2">
                                    <HiOutlineCalendar className="h-4 w-4 text-gray-400" />
                                    {m.meeting_date}{m.meeting_time ? ` · ${m.meeting_time}` : ''}
                                </p>
                                {m.location && (
                                    <p className="flex items-center gap-2">
                                        <HiOutlineLocationMarker className="h-4 w-4 text-gray-400" />
                                        {m.location}
                                    </p>
                                )}
                                {m.project && (
                                    <p className="text-xs text-gray-400">Project: {m.project.name}</p>
                                )}
                            </div>
                            <div className="mt-3 flex items-center gap-3 border-t pt-3 text-xs text-gray-500">
                                <span>{m.files?.length || 0} file(s)</span>
                                <span>{m.action_items?.length || 0} action(s)</span>
                                {m.attendees?.length ? <span>{m.attendees.length} attendee(s)</span> : null}
                            </div>
                            {can('meetings.manage') && (
                                <button
                                    onClick={(e) => { e.preventDefault(); handleDelete(m.id); }}
                                    className="absolute bottom-3 right-3 rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                    title="Delete"
                                >
                                    <HiOutlineTrash className="h-4 w-4" />
                                </button>
                            )}
                        </Link>
                    ))}
                </div>
            )}

            {pagination.last_page > 1 && (
                <div className="mt-6 flex items-center justify-between">
                    <p className="text-sm text-gray-500">
                        Showing {pagination.from}-{pagination.to} of {pagination.total}
                    </p>
                    <div className="flex gap-1">
                        {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                            <button
                                key={page}
                                onClick={() => fetchMeetings(page)}
                                className={`rounded px-3 py-1 text-sm ${
                                    page === pagination.current_page
                                        ? 'bg-primary-600 text-white'
                                        : 'text-gray-600 hover:bg-gray-100'
                                }`}
                            >
                                {page}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
