import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import notificationService from '@/services/notificationService';
import { HiOutlineBell, HiOutlineCheck, HiOutlineTrash } from 'react-icons/hi';

function relativeTime(value) {
    if (!value) return '';
    const diff = Math.floor((Date.now() - new Date(value).getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return new Date(value).toLocaleDateString();
}

export default function NotificationBell() {
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);
    const [count, setCount] = useState(0);
    const [items, setItems] = useState([]);
    const pollRef = useRef(null);

    const loadCount = useCallback(() => {
        notificationService.unreadCount()
            .then((r) => setCount(r.data?.count ?? 0))
            .catch(() => {});
    }, []);

    const loadList = useCallback(() => {
        notificationService.list({ per_page: 8 })
            .then((r) => setItems(r.data?.data || []))
            .catch(() => {});
    }, []);

    // Poll unread count (works with or without Pusher) + refresh on focus.
    useEffect(() => {
        loadCount();
        pollRef.current = setInterval(loadCount, 30000);
        const onFocus = () => loadCount();
        window.addEventListener('focus', onFocus);
        return () => {
            clearInterval(pollRef.current);
            window.removeEventListener('focus', onFocus);
        };
    }, [loadCount]);

    const toggle = () => {
        const next = !open;
        setOpen(next);
        if (next) loadList();
    };

    const openItem = async (n) => {
        if (!n.read_at) {
            try { await notificationService.markAsRead(n.id); } catch { /* ignore */ }
            loadCount();
        }
        setOpen(false);
        const link = n.data?.link;
        if (link) navigate(link);
    };

    const markAllRead = async () => {
        try {
            await notificationService.markAllAsRead();
            loadCount();
            loadList();
        } catch { /* ignore */ }
    };

    const clearAll = async () => {
        try {
            await notificationService.clearAll();
            setItems([]);
            setCount(0);
        } catch { /* ignore */ }
    };

    return (
        <div className="relative">
            <button
                onClick={toggle}
                className="relative rounded-lg p-2 text-white/80 transition-colors hover:bg-white/10 hover:text-white"
                title="Notifications"
            >
                <HiOutlineBell className="h-6 w-6" />
                {count > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                        {count > 99 ? '99+' : count}
                    </span>
                )}
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
                    <div className="absolute right-0 z-50 mt-1 w-80 overflow-hidden rounded-lg border border-primary-200 bg-white shadow-lg sm:w-96">
                        <div className="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
                            <p className="text-sm font-semibold text-gray-900">Notifications</p>
                            <div className="flex items-center gap-1">
                                <button onClick={markAllRead} title="Mark all read" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-primary-600">
                                    <HiOutlineCheck className="h-4 w-4" />
                                </button>
                                <button onClick={clearAll} title="Clear all" className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                    <HiOutlineTrash className="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div className="max-h-96 overflow-y-auto">
                            {items.length === 0 ? (
                                <div className="px-4 py-10 text-center">
                                    <HiOutlineBell className="mx-auto h-8 w-8 text-gray-300" />
                                    <p className="mt-2 text-sm text-gray-400">No notifications</p>
                                </div>
                            ) : (
                                items.map((n) => (
                                    <button
                                        key={n.id}
                                        onClick={() => openItem(n)}
                                        className={`flex w-full items-start gap-3 border-b border-gray-50 px-4 py-3 text-left hover:bg-gray-50 ${n.read_at ? '' : 'bg-primary-50/40'}`}
                                    >
                                        {!n.read_at && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary-500" />}
                                        <div className={`min-w-0 flex-1 ${n.read_at ? 'pl-5' : ''}`}>
                                            <p className="text-sm font-medium text-gray-900">{n.data?.title || 'Notification'}</p>
                                            {n.data?.message && <p className="text-xs text-gray-600">{n.data.message}</p>}
                                            <p className="mt-0.5 text-[11px] text-gray-400">{relativeTime(n.created_at)}</p>
                                        </div>
                                    </button>
                                ))
                            )}
                        </div>

                        <button
                            onClick={() => { setOpen(false); navigate('/notifications'); }}
                            className="block w-full border-t border-gray-100 px-4 py-2.5 text-center text-sm font-medium text-primary-600 hover:bg-gray-50"
                        >
                            View all notifications
                        </button>
                    </div>
                </>
            )}
        </div>
    );
}
