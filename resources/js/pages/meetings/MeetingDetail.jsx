import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import meetingService from '@/services/meetingService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePencil,
    HiOutlineTrash,
    HiOutlineDownload,
    HiOutlineCalendar,
    HiOutlineClock,
    HiOutlineLocationMarker,
    HiOutlineDocumentText,
    HiOutlineArrowLeft,
} from 'react-icons/hi';

const statusColors = {
    draft: 'bg-gray-100 text-gray-600',
    published: 'bg-green-100 text-green-700',
    archived: 'bg-yellow-100 text-yellow-700',
};

const actionColors = {
    open: 'bg-gray-100 text-gray-600',
    in_progress: 'bg-blue-100 text-blue-700',
    done: 'bg-green-100 text-green-700',
};

function formatSize(bytes) {
    if (!bytes) return '';
    const kb = bytes / 1024;
    return kb > 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.round(kb)} KB`;
}

export default function MeetingDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { can } = useAuth();
    const [meeting, setMeeting] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchMeeting = async () => {
        setLoading(true);
        try {
            const res = await meetingService.get(id);
            setMeeting(res.data);
        } catch {
            toast.error('Failed to load meeting');
            navigate('/meetings');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchMeeting();
    }, [id]);

    const handleDelete = async () => {
        if (!confirm('Delete this meeting record?')) return;
        try {
            await meetingService.delete(id);
            toast.success('Meeting deleted');
            navigate('/meetings');
        } catch {
            toast.error('Failed to delete meeting');
        }
    };

    if (loading) return <LoadingSpinner />;
    if (!meeting) return null;

    return (
        <div className="mx-auto max-w-4xl">
            <Link to="/meetings" className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary-600">
                <HiOutlineArrowLeft className="h-4 w-4" /> Back to Meetings
            </Link>

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold text-gray-900">{meeting.title}</h1>
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[meeting.status] || statusColors.draft}`}>
                            {meeting.status}
                        </span>
                    </div>
                    <div className="mt-2 flex flex-wrap gap-4 text-sm text-gray-500">
                        <span className="flex items-center gap-1"><HiOutlineCalendar className="h-4 w-4" /> {meeting.meeting_date}</span>
                        {meeting.meeting_time && <span className="flex items-center gap-1"><HiOutlineClock className="h-4 w-4" /> {meeting.meeting_time}</span>}
                        {meeting.location && <span className="flex items-center gap-1"><HiOutlineLocationMarker className="h-4 w-4" /> {meeting.location}</span>}
                        {meeting.project && <span>Project: {meeting.project.name}</span>}
                    </div>
                </div>
                <div className="flex gap-2">
                    {can('meetings.manage') && (
                        <>
                            <button
                                onClick={() => navigate(`/meetings/${id}/edit`)}
                                className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                <HiOutlinePencil className="h-4 w-4" /> Edit
                            </button>
                            <button
                                onClick={handleDelete}
                                className="inline-flex items-center gap-1 rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                            >
                                <HiOutlineTrash className="h-4 w-4" /> Delete
                            </button>
                        </>
                    )}
                </div>
            </div>

            <div className="space-y-6">
                {/* Attendees */}
                {meeting.attendees?.length > 0 && (
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h2 className="mb-3 text-sm font-semibold uppercase text-gray-500">Attendees</h2>
                        <div className="flex flex-wrap gap-2">
                            {meeting.attendees.map((a, i) => (
                                <span key={i} className="rounded-full bg-primary-50 px-3 py-1 text-sm text-primary-700">
                                    {a.name || `Employee #${a.employee_id || a.user_id}`}
                                </span>
                            ))}
                        </div>
                    </div>
                )}

                {/* Agenda */}
                {meeting.agenda && (
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h2 className="mb-3 text-sm font-semibold uppercase text-gray-500">Agenda</h2>
                        <p className="whitespace-pre-wrap text-sm text-gray-700">{meeting.agenda}</p>
                    </div>
                )}

                {/* Notes */}
                {meeting.notes && (
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h2 className="mb-3 text-sm font-semibold uppercase text-gray-500">Minutes / Notes</h2>
                        <p className="whitespace-pre-wrap text-sm text-gray-700">{meeting.notes}</p>
                    </div>
                )}

                {/* Action Items */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-3 text-sm font-semibold uppercase text-gray-500">Action Items</h2>
                    {meeting.action_items?.length ? (
                        <ul className="divide-y divide-gray-100">
                            {meeting.action_items.map((a) => (
                                <li key={a.id} className="flex items-start justify-between gap-3 py-3">
                                    <div>
                                        <p className="text-sm text-gray-800">{a.item}</p>
                                        <p className="mt-0.5 text-xs text-gray-500">
                                            {a.assignee ? `${a.assignee.first_name} ${a.assignee.last_name}` : 'Unassigned'}
                                            {a.due_date ? ` · Due ${a.due_date}` : ''}
                                        </p>
                                    </div>
                                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${actionColors[a.status] || actionColors.open}`}>
                                        {a.status?.replace('_', ' ')}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-gray-400">No action items.</p>
                    )}
                </div>

                {/* Files */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-3 text-sm font-semibold uppercase text-gray-500">Attachments</h2>
                    {meeting.files?.length ? (
                        <ul className="divide-y divide-gray-100">
                            {meeting.files.map((f) => (
                                <li key={f.id} className="flex items-center justify-between gap-3 py-3">
                                    <span className="flex items-center gap-2 text-sm text-gray-700">
                                        <HiOutlineDocumentText className="h-5 w-5 text-gray-400" />
                                        {f.file_name}
                                        {f.file_size ? <span className="text-xs text-gray-400">({formatSize(f.file_size)})</span> : null}
                                    </span>
                                    <a
                                        href={meetingService.getFileDownloadUrl(f.id)}
                                        className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
                                    >
                                        <HiOutlineDownload className="h-4 w-4" /> Download
                                    </a>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-gray-400">No attachments.</p>
                    )}
                </div>
            </div>
        </div>
    );
}
