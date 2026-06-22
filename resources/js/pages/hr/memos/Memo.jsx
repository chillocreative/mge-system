import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import apiClient from '@/services/apiClient';
import memoService from '@/services/memoService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineDocumentDuplicate, HiOutlinePlus, HiOutlineX, HiOutlinePaperClip, HiOutlineDownload } from 'react-icons/hi';

const audienceLabel = {
    all_users: 'All staff',
    selected_users: 'Selected staff',
    project_members: 'Project members',
};

function fmt(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export default function Memo() {
    const { can, user } = useAuth();
    const canHr = can('memos.send-hr');
    const canProj = can('memos.send-project');
    const canCompose = canHr || canProj;

    const [tab, setTab] = useState('inbox');
    const [memos, setMemos] = useState([]);
    const [loading, setLoading] = useState(true);
    const [reading, setReading] = useState(null);

    // Compose
    const [composeOpen, setComposeOpen] = useState(false);
    const [users, setUsers] = useState([]);
    const [projects, setProjects] = useState([]);
    const [sending, setSending] = useState(false);
    const audiences = [
        ...(canHr ? [{ value: 'all_users', label: 'All staff' }, { value: 'selected_users', label: 'Selected staff' }] : []),
        ...(canProj ? [{ value: 'project_members', label: 'Project members' }] : []),
    ];
    const [form, setForm] = useState({ title: '', body: '', audience: '', project_id: '', userIds: new Set() });
    const [files, setFiles] = useState([]);
    const [userSearch, setUserSearch] = useState('');

    const load = useCallback(() => {
        setLoading(true);
        memoService.list({ folder: tab, per_page: 50 })
            .then((r) => setMemos(r.data?.data || []))
            .catch(() => setMemos([]))
            .finally(() => setLoading(false));
    }, [tab]);

    useEffect(() => { load(); }, [load]);

    useEffect(() => {
        if (!canCompose) return;
        const unwrap = (r) => r.data?.data?.data || r.data?.data || [];
        apiClient.get('/users', { params: { per_page: 200 } }).then((r) => setUsers(unwrap(r))).catch(() => {});
        apiClient.get('/projects', { params: { per_page: 200 } }).then((r) => setProjects(unwrap(r))).catch(() => {});
    }, [canCompose]);

    const openMemo = async (m) => {
        try {
            const r = await memoService.get(m.id);
            setReading(r.data);
            if (tab === 'inbox') {
                setMemos((prev) => prev.map((x) => x.id === m.id ? { ...x, recipients: [{ read_at: new Date().toISOString() }] } : x));
            }
        } catch {
            toast.error('Failed to open memo');
        }
    };

    const openCompose = () => {
        setForm({ title: '', body: '', audience: audiences[0]?.value || '', project_id: '', userIds: new Set() });
        setFiles([]);
        setUserSearch('');
        setComposeOpen(true);
    };

    const addFiles = (e) => {
        const picked = Array.from(e.target.files);
        setFiles((prev) => [...prev, ...picked]);
        e.target.value = '';
    };
    const removeFile = (idx) => setFiles((prev) => prev.filter((_, i) => i !== idx));

    const toggleUser = (id) => setForm((p) => {
        const n = new Set(p.userIds); n.has(id) ? n.delete(id) : n.add(id); return { ...p, userIds: n };
    });

    const send = async (e) => {
        e.preventDefault();
        setSending(true);
        try {
            const fd = new FormData();
            fd.append('title', form.title);
            fd.append('body', form.body);
            fd.append('audience', form.audience);
            if (form.audience === 'selected_users') [...form.userIds].forEach((id) => fd.append('user_ids[]', id));
            if (form.audience === 'project_members') fd.append('project_id', form.project_id);
            files.forEach((f) => fd.append('attachments[]', f));
            await memoService.send(fd);
            toast.success('Memo sent');
            setComposeOpen(false);
            setTab('sent');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to send memo');
        } finally {
            setSending(false);
        }
    };

    // Projects selectable for a project memo: HR sees all; PMs see only theirs.
    const myProjects = canHr ? projects : projects.filter((p) => String(p.manager?.id || p.manager_id) === String(user?.id));

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <HiOutlineDocumentDuplicate className="h-7 w-7 text-primary-600" /> Memos
                    </h1>
                    <p className="text-sm text-gray-500">Internal memos to staff and project teams</p>
                </div>
                {canCompose && (
                    <button onClick={openCompose} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                        <HiOutlinePlus className="h-5 w-5" /> New Memo
                    </button>
                )}
            </div>

            <div className="mb-5 border-b border-gray-200">
                <nav className="-mb-px flex gap-6">
                    {[{ id: 'inbox', label: 'Inbox' }, ...(canCompose ? [{ id: 'sent', label: 'Sent' }] : [])].map((t) => (
                        <button key={t.id} onClick={() => setTab(t.id)}
                            className={`whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium ${tab === t.id ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : memos.length === 0 ? (
                <div className="rounded-xl bg-white py-16 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineDocumentDuplicate className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">{tab === 'inbox' ? 'No memos received' : 'No memos sent'}</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    {memos.map((m) => {
                        const unread = tab === 'inbox' && !m.recipients?.[0]?.read_at;
                        return (
                            <button key={m.id} onClick={() => openMemo(m)}
                                className={`flex w-full items-start gap-3 border-b border-gray-50 px-4 py-3.5 text-left hover:bg-gray-50 ${unread ? 'bg-primary-50/40' : ''}`}>
                                {unread ? <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary-500" /> : <span className="mt-1.5 h-2 w-2 shrink-0" />}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className={`truncate text-sm ${unread ? 'font-bold' : 'font-medium'} text-gray-900`}>{m.title}</p>
                                        <span className="shrink-0 text-xs text-gray-400">{fmt(m.sent_at || m.created_at)}</span>
                                    </div>
                                    <p className="truncate text-xs text-gray-500">
                                        {tab === 'inbox'
                                            ? `From ${m.sender?.first_name ?? ''} ${m.sender?.last_name ?? ''}`
                                            : `${audienceLabel[m.audience]}${m.project?.name ? ' · ' + m.project.name : ''} · ${m.recipients_count ?? 0} recipients`}
                                    </p>
                                </div>
                            </button>
                        );
                    })}
                </div>
            )}

            {/* Read memo */}
            {reading && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setReading(null)}>
                    <div className="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-3 flex items-start justify-between gap-2">
                            <h3 className="text-xl font-bold text-gray-900">{reading.title}</h3>
                            <button onClick={() => setReading(null)} className="rounded p-1 text-gray-400 hover:bg-gray-100"><HiOutlineX className="h-5 w-5" /></button>
                        </div>
                        <p className="mb-4 text-xs text-gray-400">
                            From {reading.sender?.first_name} {reading.sender?.last_name} · {fmt(reading.sent_at || reading.created_at)}
                            {reading.project?.name ? ` · ${reading.project.name}` : ''}
                        </p>
                        <div className="whitespace-pre-wrap break-words text-sm text-gray-700">{reading.body}</div>
                        {reading.attachments?.length > 0 && (
                            <div className="mt-5 border-t border-gray-100 pt-4">
                                <p className="mb-2 text-xs font-semibold uppercase text-gray-500">Attachments</p>
                                <ul className="space-y-1.5">
                                    {reading.attachments.map((a) => (
                                        <li key={a.id}>
                                            <a href={memoService.attachmentUrl(reading.id, a.id)} target="_blank" rel="noreferrer"
                                                className="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                <span className="flex min-w-0 items-center gap-2"><HiOutlinePaperClip className="h-4 w-4 shrink-0 text-gray-400" /><span className="truncate">{a.file_name}</span></span>
                                                <span className="flex shrink-0 items-center gap-2 text-xs text-gray-400">{a.human_size}<HiOutlineDownload className="h-4 w-4 text-primary-600" /></span>
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Compose */}
            {composeOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setComposeOpen(false)}>
                    <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">New Memo</h3>
                        <form onSubmit={send} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Send to *</label>
                                <select required value={form.audience} onChange={(e) => setForm((p) => ({ ...p, audience: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                    {audiences.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
                                </select>
                            </div>

                            {form.audience === 'project_members' && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Project *</label>
                                    <select required value={form.project_id} onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="">Select project</option>
                                        {myProjects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </select>
                                    {myProjects.length === 0 && <p className="mt-1 text-xs text-amber-600">You don't manage any projects.</p>}
                                </div>
                            )}

                            {form.audience === 'selected_users' && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Recipients * ({form.userIds.size} selected)</label>
                                    <input type="text" value={userSearch} onChange={(e) => setUserSearch(e.target.value)} placeholder="Search staff…"
                                        className="mb-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                    <div className="max-h-44 overflow-y-auto rounded-lg border border-gray-200">
                                        {users.filter((u) => u.id !== user?.id && (u.full_name || '').toLowerCase().includes(userSearch.toLowerCase())).map((u) => (
                                            <label key={u.id} className="flex cursor-pointer items-center gap-2 border-b border-gray-50 px-3 py-2 text-sm hover:bg-gray-50">
                                                <input type="checkbox" checked={form.userIds.has(u.id)} onChange={() => toggleUser(u.id)}
                                                    className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                <span className="text-gray-900">{u.full_name}</span>
                                                <span className="text-xs text-gray-400">{u.email}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Title *</label>
                                <input type="text" required value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Message *</label>
                                <textarea required rows={6} value={form.body} onChange={(e) => setForm((p) => ({ ...p, body: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Attachments</label>
                                <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:border-primary-400 hover:text-primary-700">
                                    <HiOutlinePaperClip className="h-4 w-4" />
                                    Add files
                                    <input type="file" multiple className="hidden" onChange={addFiles}
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.gif,.zip,.txt,.csv" />
                                </label>
                                {files.length > 0 && (
                                    <ul className="mt-2 space-y-1">
                                        {files.map((f, i) => (
                                            <li key={i} className="flex items-center justify-between gap-2 rounded bg-gray-50 px-3 py-1.5 text-sm text-gray-600">
                                                <span className="flex min-w-0 items-center gap-2"><HiOutlinePaperClip className="h-3.5 w-3.5 shrink-0" /><span className="truncate">{f.name}</span></span>
                                                <button type="button" onClick={() => removeFile(i)} className="shrink-0 text-gray-400 hover:text-red-600"><HiOutlineX className="h-4 w-4" /></button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <p className="mt-1 text-xs text-gray-400">Up to 10 files, max 10MB each.</p>
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setComposeOpen(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={sending || (form.audience === 'selected_users' && form.userIds.size === 0)}
                                    className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                    {sending ? 'Sending...' : 'Send Memo'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
