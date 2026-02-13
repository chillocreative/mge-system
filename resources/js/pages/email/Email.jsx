import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import emailService from '@/services/emailService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlineInbox,
    HiOutlinePaperAirplane,
    HiOutlineDocumentText,
    HiOutlineStar,
    HiOutlineTrash,
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineReply,
    HiOutlinePaperClip,
    HiOutlineX,
    HiOutlineArrowLeft,
    HiOutlineDownload,
} from 'react-icons/hi';

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return d.toLocaleDateString('en-US', { weekday: 'short' });
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function formatDateTime(dateStr) {
    return new Date(dateStr).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const folders = [
    { key: 'inbox', label: 'Inbox', icon: HiOutlineInbox },
    { key: 'sent', label: 'Sent', icon: HiOutlinePaperAirplane },
    { key: 'drafts', label: 'Drafts', icon: HiOutlineDocumentText },
    { key: 'starred', label: 'Starred', icon: HiOutlineStar },
    { key: 'trash', label: 'Trash', icon: HiOutlineTrash },
];

export default function Email() {
    const { user } = useAuth();
    const [activeFolder, setActiveFolder] = useState('inbox');
    const [emails, setEmails] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [selectedEmail, setSelectedEmail] = useState(null);
    const [thread, setThread] = useState([]);
    const [showCompose, setShowCompose] = useState(false);
    const [showReply, setShowReply] = useState(false);
    const [allUsers, setAllUsers] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [pagination, setPagination] = useState({});

    // Compose form
    const [composeForm, setComposeForm] = useState({
        subject: '',
        body: '',
        to: [],
        cc: [],
        attachments: [],
    });
    const [composeSending, setComposeSending] = useState(false);

    // Reply form
    const [replyForm, setReplyForm] = useState({ body: '', to: [], attachments: [] });
    const [replySending, setReplySending] = useState(false);

    const fetchEmails = useCallback(async (page = 1) => {
        setLoading(true);
        try {
            const params = { folder: activeFolder, page };
            if (search) params.search = search;
            const res = await emailService.getEmails(params);
            setEmails(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setEmails([]);
        }
        setLoading(false);
    }, [activeFolder, search]);

    const fetchUnread = async () => {
        try {
            const res = await emailService.getUnreadCount();
            setUnreadCount(res.data?.count || 0);
        } catch { /* ignore */ }
    };

    useEffect(() => {
        fetchEmails();
        fetchUnread();
    }, [activeFolder]);

    useEffect(() => {
        const timer = setTimeout(() => fetchEmails(), 400);
        return () => clearTimeout(timer);
    }, [search]);

    // Load users for compose
    useEffect(() => {
        (async () => {
            try {
                const res = await fetch('/api/users?per_page=200', { credentials: 'include', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                setAllUsers((data.data?.data || []).filter((u) => u.id !== user?.id));
            } catch { /* ignore */ }
        })();
    }, []);

    // Polling for new emails
    useEffect(() => {
        const interval = setInterval(() => {
            fetchUnread();
            if (activeFolder === 'inbox') fetchEmails();
        }, 15000);
        return () => clearInterval(interval);
    }, [activeFolder]);

    const handleOpenEmail = async (email) => {
        try {
            const res = await emailService.getEmail(email.id);
            setSelectedEmail(res.data?.email || res.data);
            setThread(res.data?.thread || []);
            fetchUnread();
        } catch {
            toast.error('Failed to load email');
        }
    };

    const handleToggleStar = async (emailId, e) => {
        e?.stopPropagation();
        try {
            await emailService.toggleStar(emailId);
            fetchEmails();
        } catch { /* ignore */ }
    };

    const handleTrash = async (emailId) => {
        try {
            await emailService.moveToTrash(emailId);
            toast.success('Moved to trash');
            setSelectedEmail(null);
            fetchEmails();
        } catch { /* ignore */ }
    };

    const handleRestore = async (emailId) => {
        try {
            await emailService.restoreFromTrash(emailId);
            toast.success('Restored');
            setSelectedEmail(null);
            fetchEmails();
        } catch { /* ignore */ }
    };

    const handleSendEmail = async (e) => {
        e.preventDefault();
        if (!composeForm.to.length || !composeForm.subject) {
            toast.error('Subject and at least one recipient required');
            return;
        }
        setComposeSending(true);
        try {
            const formData = new FormData();
            formData.append('subject', composeForm.subject);
            formData.append('body', composeForm.body);
            composeForm.to.forEach((id) => formData.append('to[]', id));
            composeForm.cc.forEach((id) => formData.append('cc[]', id));
            composeForm.attachments.forEach((f) => formData.append('attachments[]', f));
            await emailService.sendEmail(formData);
            toast.success('Email sent');
            setShowCompose(false);
            setComposeForm({ subject: '', body: '', to: [], cc: [], attachments: [] });
            if (activeFolder === 'sent') fetchEmails();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to send');
        }
        setComposeSending(false);
    };

    const handleSendReply = async (e) => {
        e.preventDefault();
        if (!replyForm.body.trim()) return;
        setReplySending(true);
        try {
            const formData = new FormData();
            formData.append('body', replyForm.body);
            replyForm.to.forEach((id) => formData.append('to[]', id));
            replyForm.attachments.forEach((f) => formData.append('attachments[]', f));
            await emailService.replyToEmail(selectedEmail.id, formData);
            toast.success('Reply sent');
            setShowReply(false);
            setReplyForm({ body: '', to: [], attachments: [] });
            handleOpenEmail(selectedEmail);
        } catch {
            toast.error('Failed to send reply');
        }
        setReplySending(false);
    };

    const handleStartReply = () => {
        setReplyForm({
            body: '',
            to: [selectedEmail.from_user_id],
            attachments: [],
        });
        setShowReply(true);
    };

    // ── User Selector Component ──
    const UserSelector = ({ selected, onChange, label }) => {
        const [userSearch, setUserSearch] = useState('');
        const filtered = allUsers.filter((u) =>
            !selected.includes(u.id) &&
            (`${u.first_name} ${u.last_name}`).toLowerCase().includes(userSearch.toLowerCase())
        );
        return (
            <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
                <div className="rounded-lg border border-gray-300 p-2">
                    <div className="mb-2 flex flex-wrap gap-1">
                        {selected.map((id) => {
                            const u = allUsers.find((u) => u.id === id);
                            return u ? (
                                <span key={id} className="inline-flex items-center gap-1 rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700">
                                    {u.first_name} {u.last_name}
                                    <button type="button" onClick={() => onChange(selected.filter((i) => i !== id))} className="hover:text-primary-900">
                                        <HiOutlineX className="h-3 w-3" />
                                    </button>
                                </span>
                            ) : null;
                        })}
                    </div>
                    <input
                        type="text"
                        placeholder="Search users..."
                        value={userSearch}
                        onChange={(e) => setUserSearch(e.target.value)}
                        className="w-full border-0 p-0 text-sm focus:outline-none focus:ring-0"
                    />
                    {userSearch && filtered.length > 0 && (
                        <div className="mt-1 max-h-32 overflow-y-auto border-t pt-1">
                            {filtered.slice(0, 8).map((u) => (
                                <button
                                    key={u.id}
                                    type="button"
                                    onClick={() => { onChange([...selected, u.id]); setUserSearch(''); }}
                                    className="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-gray-50"
                                >
                                    <span className="font-medium">{u.first_name} {u.last_name}</span>
                                    <span className="text-xs text-gray-400">{u.email}</span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        );
    };

    return (
        <div className="flex h-[calc(100vh-7rem)] overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            {/* Sidebar */}
            <div className="w-56 flex-shrink-0 border-r bg-gray-50">
                <div className="p-3">
                    <button
                        onClick={() => { setShowCompose(true); setComposeForm({ subject: '', body: '', to: [], cc: [], attachments: [] }); }}
                        className="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-4 w-4" />
                        Compose
                    </button>
                </div>
                <nav className="space-y-0.5 px-2">
                    {folders.map((f) => (
                        <button
                            key={f.key}
                            onClick={() => { setActiveFolder(f.key); setSelectedEmail(null); }}
                            className={`flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                                activeFolder === f.key ? 'bg-primary-100 text-primary-700' : 'text-gray-600 hover:bg-gray-100'
                            }`}
                        >
                            <f.icon className="h-4 w-4" />
                            <span className="flex-1 text-left">{f.label}</span>
                            {f.key === 'inbox' && unreadCount > 0 && (
                                <span className="rounded-full bg-primary-500 px-1.5 py-0.5 text-xs font-bold text-white">{unreadCount}</span>
                            )}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Email List or Detail */}
            <div className="flex flex-1 flex-col overflow-hidden">
                {selectedEmail ? (
                    /* Email Detail View */
                    <div className="flex flex-1 flex-col overflow-hidden">
                        {/* Header */}
                        <div className="flex items-center gap-2 border-b px-4 py-2.5">
                            <button onClick={() => setSelectedEmail(null)} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                <HiOutlineArrowLeft className="h-5 w-5" />
                            </button>
                            <h2 className="flex-1 truncate text-sm font-semibold text-gray-900">{selectedEmail.subject}</h2>
                            <button onClick={handleStartReply} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Reply">
                                <HiOutlineReply className="h-5 w-5" />
                            </button>
                            {activeFolder === 'trash' ? (
                                <button onClick={() => handleRestore(selectedEmail.id)} className="rounded-lg p-1.5 text-gray-400 hover:bg-green-50 hover:text-green-600" title="Restore">
                                    <HiOutlineInbox className="h-5 w-5" />
                                </button>
                            ) : (
                                <button onClick={() => handleTrash(selectedEmail.id)} className="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Trash">
                                    <HiOutlineTrash className="h-5 w-5" />
                                </button>
                            )}
                        </div>

                        {/* Thread */}
                        <div className="flex-1 overflow-y-auto px-6 py-4">
                            {(thread.length > 0 ? thread : [selectedEmail]).map((email) => (
                                <div key={email.id} className="mb-6 rounded-lg border border-gray-100 p-5">
                                    <div className="mb-3 flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700">
                                                {email.sender?.first_name?.[0]}{email.sender?.last_name?.[0]}
                                            </div>
                                            <div>
                                                <p className="text-sm font-semibold text-gray-900">
                                                    {email.sender?.first_name} {email.sender?.last_name}
                                                </p>
                                                <p className="text-xs text-gray-400">
                                                    to {email.recipients?.filter((r) => r.type === 'to').map((r) => `${r.user?.first_name} ${r.user?.last_name}`).join(', ')}
                                                    {email.recipients?.some((r) => r.type === 'cc') && (
                                                        <span>, cc: {email.recipients.filter((r) => r.type === 'cc').map((r) => `${r.user?.first_name} ${r.user?.last_name}`).join(', ')}</span>
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <span className="text-xs text-gray-400">{formatDateTime(email.sent_at || email.created_at)}</span>
                                    </div>
                                    <div className="prose prose-sm max-w-none text-sm text-gray-700 whitespace-pre-wrap">{email.body}</div>
                                    {email.attachments?.length > 0 && (
                                        <div className="mt-4 flex flex-wrap gap-2 border-t pt-3">
                                            {email.attachments.map((att) => (
                                                <a
                                                    key={att.id}
                                                    href={emailService.getAttachmentDownloadUrl(att.id)}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50"
                                                >
                                                    <HiOutlineDownload className="h-3.5 w-3.5" />
                                                    {att.file_name}
                                                    <span className="text-gray-400">({att.human_size || Math.round(att.file_size / 1024) + ' KB'})</span>
                                                </a>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}

                            {/* Inline Reply */}
                            {showReply && (
                                <div className="mb-4 rounded-lg border border-primary-200 bg-primary-50/30 p-4">
                                    <form onSubmit={handleSendReply} className="space-y-3">
                                        <div className="text-xs text-gray-500">
                                            Replying to {selectedEmail.sender?.first_name} {selectedEmail.sender?.last_name}
                                        </div>
                                        <textarea
                                            rows={4}
                                            value={replyForm.body}
                                            onChange={(e) => setReplyForm((p) => ({ ...p, body: e.target.value }))}
                                            placeholder="Write your reply..."
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                        />
                                        <div className="flex justify-end gap-2">
                                            <button type="button" onClick={() => setShowReply(false)} className="rounded-lg border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                                            <button type="submit" disabled={replySending || !replyForm.body.trim()} className="rounded-lg bg-primary-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                                {replySending ? 'Sending...' : 'Send Reply'}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            )}
                        </div>
                    </div>
                ) : (
                    /* Email List */
                    <>
                        {/* Search */}
                        <div className="border-b px-4 py-2.5">
                            <div className="relative">
                                <HiOutlineSearch className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Search emails..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                        </div>

                        {/* List */}
                        <div className="flex-1 overflow-y-auto">
                            {loading ? (
                                <LoadingSpinner />
                            ) : emails.length === 0 ? (
                                <div className="flex h-full items-center justify-center">
                                    <div className="text-center">
                                        <HiOutlineInbox className="mx-auto h-12 w-12 text-gray-200" />
                                        <p className="mt-2 text-sm text-gray-400">No emails in {activeFolder}</p>
                                    </div>
                                </div>
                            ) : (
                                emails.map((email) => {
                                    const isUnread = email.recipients?.some((r) => r.user_id === user?.id && !r.read_at);
                                    const isStarred = email.recipients?.some((r) => r.user_id === user?.id && r.starred);
                                    return (
                                        <button
                                            key={email.id}
                                            onClick={() => handleOpenEmail(email)}
                                            className={`flex w-full items-center gap-3 border-b px-4 py-3 text-left transition-colors hover:bg-gray-50 ${isUnread ? 'bg-blue-50/50' : ''}`}
                                        >
                                            <button
                                                type="button"
                                                onClick={(e) => handleToggleStar(email.id, e)}
                                                className={`shrink-0 ${isStarred ? 'text-yellow-400' : 'text-gray-300'} hover:text-yellow-400`}
                                            >
                                                <HiOutlineStar className="h-4 w-4" fill={isStarred ? 'currentColor' : 'none'} />
                                            </button>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between">
                                                    <p className={`truncate text-sm ${isUnread ? 'font-bold text-gray-900' : 'font-medium text-gray-700'}`}>
                                                        {activeFolder === 'sent'
                                                            ? `To: ${email.recipients?.filter((r) => r.type === 'to').map((r) => r.user?.first_name).join(', ')}`
                                                            : `${email.sender?.first_name} ${email.sender?.last_name}`}
                                                    </p>
                                                    <span className="ml-2 shrink-0 text-xs text-gray-400">{timeAgo(email.sent_at || email.created_at)}</span>
                                                </div>
                                                <p className={`truncate text-sm ${isUnread ? 'font-semibold text-gray-800' : 'text-gray-600'}`}>{email.subject}</p>
                                                <p className="truncate text-xs text-gray-400">{email.body?.substring(0, 100)}</p>
                                            </div>
                                            {email.attachments?.length > 0 && (
                                                <HiOutlinePaperClip className="h-4 w-4 shrink-0 text-gray-400" />
                                            )}
                                        </button>
                                    );
                                })
                            )}
                        </div>

                        {/* Pagination */}
                        {pagination.last_page > 1 && (
                            <div className="flex items-center justify-between border-t px-4 py-2">
                                <p className="text-xs text-gray-500">Page {pagination.current_page} of {pagination.last_page}</p>
                                <div className="flex gap-1">
                                    {Array.from({ length: Math.min(pagination.last_page, 5) }, (_, i) => i + 1).map((page) => (
                                        <button
                                            key={page}
                                            onClick={() => fetchEmails(page)}
                                            className={`rounded px-2 py-1 text-xs ${
                                                page === pagination.current_page ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'
                                            }`}
                                        >
                                            {page}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            {/* Compose Modal */}
            {showCompose && (
                <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center" onClick={() => setShowCompose(false)}>
                    <div className="fixed inset-0 bg-black/50" />
                    <div className="relative mx-4 w-full max-w-2xl rounded-t-xl sm:rounded-xl bg-white shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between border-b px-5 py-3">
                            <h3 className="text-base font-semibold text-gray-900">New Email</h3>
                            <button onClick={() => setShowCompose(false)} className="text-gray-400 hover:text-gray-600">
                                <HiOutlineX className="h-5 w-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSendEmail} className="p-5 space-y-4">
                            <UserSelector selected={composeForm.to} onChange={(to) => setComposeForm((p) => ({ ...p, to }))} label="To *" />
                            <UserSelector selected={composeForm.cc} onChange={(cc) => setComposeForm((p) => ({ ...p, cc }))} label="CC" />
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Subject *</label>
                                <input
                                    type="text"
                                    value={composeForm.subject}
                                    onChange={(e) => setComposeForm((p) => ({ ...p, subject: e.target.value }))}
                                    required
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Message</label>
                                <textarea
                                    rows={8}
                                    value={composeForm.body}
                                    onChange={(e) => setComposeForm((p) => ({ ...p, body: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Attachments</label>
                                <input
                                    type="file"
                                    multiple
                                    onChange={(e) => setComposeForm((p) => ({ ...p, attachments: [...p.attachments, ...Array.from(e.target.files)] }))}
                                    className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100"
                                />
                                {composeForm.attachments.length > 0 && (
                                    <div className="mt-2 flex flex-wrap gap-1">
                                        {composeForm.attachments.map((f, i) => (
                                            <span key={i} className="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                                {f.name}
                                                <button type="button" onClick={() => setComposeForm((p) => ({ ...p, attachments: p.attachments.filter((_, j) => j !== i) }))}>
                                                    <HiOutlineX className="h-3 w-3" />
                                                </button>
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </div>
                            <div className="flex justify-end gap-2 border-t pt-4">
                                <button type="button" onClick={() => setShowCompose(false)} className="rounded-lg border px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Discard</button>
                                <button type="submit" disabled={composeSending} className="rounded-lg bg-primary-600 px-6 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                    {composeSending ? 'Sending...' : 'Send'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
