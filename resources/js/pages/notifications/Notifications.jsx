import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import notificationService from '@/services/notificationService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineBell, HiOutlineCheck, HiOutlineTrash } from 'react-icons/hi';

function relativeTime(value) {
    if (!value) return '';
    const diff = Math.floor((Date.now() - new Date(value).getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return new Date(value).toLocaleString();
}

export default function Notifications() {
    const navigate = useNavigate();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);

    const load = useCallback(() => {
        setLoading(true);
        notificationService.list({ per_page: 50 })
            .then((r) => setItems(r.data?.data || []))
            .catch(() => setItems([]))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => { load(); }, [load]);

    const openItem = async (n) => {
        if (!n.read_at) {
            try { await notificationService.markAsRead(n.id); } catch { /* ignore */ }
            setItems((prev) => prev.map((x) => (x.id === n.id ? { ...x, read_at: new Date().toISOString() } : x)));
        }
        if (n.data?.link) navigate(n.data.link);
    };

    const remove = async (e, n) => {
        e.stopPropagation();
        try {
            await notificationService.remove(n.id);
            setItems((prev) => prev.filter((x) => x.id !== n.id));
        } catch {
            toast.error('Failed to delete');
        }
    };

    const markAllRead = async () => {
        try {
            await notificationService.markAllAsRead();
            setItems((prev) => prev.map((x) => ({ ...x, read_at: x.read_at || new Date().toISOString() })));
            toast.success('All marked as read');
        } catch {
            toast.error('Failed');
        }
    };

    const clearAll = async () => {
        if (!confirm('Delete all notifications?')) return;
        try {
            await notificationService.clearAll();
            setItems([]);
            toast.success('All cleared');
        } catch {
            toast.error('Failed');
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <HiOutlineBell className="h-7 w-7 text-primary-600" />
                        Notifications
                    </h1>
                    <p className="text-sm text-gray-500">Alerts for approvals, projects and updates</p>
                </div>
                {items.length > 0 && (
                    <div className="flex gap-2">
                        <button onClick={markAllRead} className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <HiOutlineCheck className="h-4 w-4" /> Mark all read
                        </button>
                        <button onClick={clearAll} className="inline-flex items-center gap-2 rounded-lg border border-red-200 px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50">
                            <HiOutlineTrash className="h-4 w-4" /> Clear all
                        </button>
                    </div>
                )}
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : items.length === 0 ? (
                <div className="rounded-xl bg-white py-16 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineBell className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">You're all caught up</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    {items.map((n) => (
                        <div
                            key={n.id}
                            onClick={() => openItem(n)}
                            className={`flex cursor-pointer items-start gap-3 border-b border-gray-50 px-4 py-3.5 hover:bg-gray-50 ${n.read_at ? '' : 'bg-primary-50/40'}`}
                        >
                            {!n.read_at
                                ? <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary-500" />
                                : <span className="mt-1.5 h-2 w-2 shrink-0" />}
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium text-gray-900">{n.data?.title || 'Notification'}</p>
                                {n.data?.message && <p className="text-sm text-gray-600">{n.data.message}</p>}
                                <p className="mt-0.5 text-xs text-gray-400">{relativeTime(n.created_at)}</p>
                            </div>
                            <button onClick={(e) => remove(e, n)} className="rounded p-1.5 text-gray-300 hover:bg-red-50 hover:text-red-600" title="Delete">
                                <HiOutlineTrash className="h-4 w-4" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
