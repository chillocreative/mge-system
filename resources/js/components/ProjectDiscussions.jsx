import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import discussionService from '@/services/discussionService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlineChatAlt2,
    HiOutlinePaperAirplane,
    HiOutlineTrash,
    HiOutlineReply,
} from 'react-icons/hi';

function initials(author) {
    if (!author) return '?';
    const f = author.first_name?.[0] || '';
    const l = author.last_name?.[0] || '';
    return (f + l).toUpperCase() || '?';
}

function fullName(author) {
    if (!author) return 'Unknown';
    return `${author.first_name || ''} ${author.last_name || ''}`.trim() || 'Unknown';
}

function relativeTime(value) {
    if (!value) return '';
    const date = new Date(value);
    const diff = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return date.toLocaleDateString();
}

function Avatar({ author }) {
    return (
        <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">
            {initials(author)}
        </div>
    );
}

/**
 * Project-scoped discussion feed (composer + posts + replies).
 * Shared by the standalone Discussions page and the project detail "Discussions" tab.
 */
export default function ProjectDiscussions({ projectId }) {
    const { can, user } = useAuth();
    const [posts, setPosts] = useState([]);
    const [loading, setLoading] = useState(false);
    const [body, setBody] = useState('');
    const [posting, setPosting] = useState(false);
    const [replyTo, setReplyTo] = useState(null);
    const [replyBody, setReplyBody] = useState('');

    const fetchPosts = useCallback(async () => {
        if (!projectId) {
            setPosts([]);
            return;
        }
        setLoading(true);
        try {
            const res = await discussionService.list(projectId);
            setPosts(res.data?.data || []);
        } catch {
            setPosts([]);
        } finally {
            setLoading(false);
        }
    }, [projectId]);

    useEffect(() => {
        fetchPosts();
        setReplyTo(null);
    }, [fetchPosts]);

    const canDelete = (post) => post.posted_by === user?.id || can('projects.edit');

    const handlePost = async (e) => {
        e.preventDefault();
        if (!body.trim()) return;
        setPosting(true);
        try {
            await discussionService.post({ project_id: projectId, body });
            setBody('');
            toast.success('Update posted');
            fetchPosts();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to post');
        } finally {
            setPosting(false);
        }
    };

    const handleReply = async (parentId) => {
        if (!replyBody.trim()) return;
        try {
            await discussionService.post({
                project_id: projectId,
                body: replyBody,
                parent_id: parentId,
            });
            setReplyBody('');
            setReplyTo(null);
            toast.success('Reply posted');
            fetchPosts();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to reply');
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this post?')) return;
        try {
            await discussionService.remove(id);
            toast.success('Deleted');
            fetchPosts();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete');
        }
    };

    return (
        <div className="space-y-6">
            {/* Composer */}
            {can('projects.view') && (
                <form onSubmit={handlePost} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div className="flex gap-3">
                        <Avatar author={user} />
                        <div className="flex-1">
                            <textarea
                                rows={3}
                                value={body}
                                onChange={(e) => setBody(e.target.value)}
                                placeholder="Share a project update..."
                                className="w-full resize-none rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            <div className="mt-2 flex justify-end">
                                <button
                                    type="submit"
                                    disabled={posting || !body.trim()}
                                    className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                                >
                                    <HiOutlinePaperAirplane className="h-4 w-4 rotate-90" />
                                    {posting ? 'Posting...' : 'Post'}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            )}

            {/* Feed */}
            {loading ? (
                <LoadingSpinner />
            ) : posts.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineChatAlt2 className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No updates yet. Be the first to post.</p>
                </div>
            ) : (
                <div className="space-y-4">
                    {posts.map((post) => (
                        <div key={post.id} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                            <div className="flex gap-3">
                                <Avatar author={post.author} />
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex flex-wrap items-baseline gap-2">
                                            <span className="text-sm font-semibold text-gray-900">{fullName(post.author)}</span>
                                            <span className="text-xs text-gray-400">{relativeTime(post.created_at)}</span>
                                        </div>
                                        {canDelete(post) && (
                                            <button
                                                onClick={() => handleDelete(post.id)}
                                                className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                title="Delete"
                                            >
                                                <HiOutlineTrash className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                    <p className="mt-1 whitespace-pre-wrap break-words text-sm text-gray-700">{post.body}</p>

                                    <button
                                        onClick={() => {
                                            setReplyTo(replyTo === post.id ? null : post.id);
                                            setReplyBody('');
                                        }}
                                        className="mt-2 inline-flex items-center gap-1 text-xs font-medium text-gray-400 hover:text-primary-600"
                                    >
                                        <HiOutlineReply className="h-4 w-4" />
                                        Reply
                                    </button>

                                    {/* Replies */}
                                    {post.replies?.length > 0 && (
                                        <div className="mt-3 space-y-3 border-l-2 border-gray-100 pl-3">
                                            {post.replies.map((reply) => (
                                                <div key={reply.id} className="flex gap-2">
                                                    <Avatar author={reply.author} />
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                                            <div className="flex flex-wrap items-baseline gap-2">
                                                                <span className="text-sm font-semibold text-gray-900">{fullName(reply.author)}</span>
                                                                <span className="text-xs text-gray-400">{relativeTime(reply.created_at)}</span>
                                                            </div>
                                                            {canDelete(reply) && (
                                                                <button
                                                                    onClick={() => handleDelete(reply.id)}
                                                                    className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                                    title="Delete"
                                                                >
                                                                    <HiOutlineTrash className="h-3.5 w-3.5" />
                                                                </button>
                                                            )}
                                                        </div>
                                                        <p className="mt-0.5 whitespace-pre-wrap break-words text-sm text-gray-700">{reply.body}</p>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {/* Reply composer */}
                                    {replyTo === post.id && (
                                        <div className="mt-3 flex gap-2">
                                            <Avatar author={user} />
                                            <div className="flex-1">
                                                <textarea
                                                    rows={2}
                                                    value={replyBody}
                                                    onChange={(e) => setReplyBody(e.target.value)}
                                                    placeholder="Write a reply..."
                                                    className="w-full resize-none rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                                />
                                                <div className="mt-2 flex justify-end gap-2">
                                                    <button
                                                        onClick={() => setReplyTo(null)}
                                                        className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                                    >
                                                        Cancel
                                                    </button>
                                                    <button
                                                        onClick={() => handleReply(post.id)}
                                                        disabled={!replyBody.trim()}
                                                        className="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                                                    >
                                                        Reply
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
