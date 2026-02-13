import { useState, useEffect, useRef, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import chatService from '@/services/chatService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlinePaperAirplane,
    HiOutlinePhotograph,
    HiOutlinePaperClip,
    HiOutlineUserGroup,
    HiOutlineChat,
    HiOutlineX,
    HiOutlineUsers,
} from 'react-icons/hi';

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60) return 'now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function formatTime(dateStr) {
    return new Date(dateStr).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
}

export default function Chat() {
    const { user } = useAuth();
    const [rooms, setRooms] = useState([]);
    const [activeRoom, setActiveRoom] = useState(null);
    const [messages, setMessages] = useState([]);
    const [loading, setLoading] = useState(true);
    const [messagesLoading, setMessagesLoading] = useState(false);
    const [message, setMessage] = useState('');
    const [sending, setSending] = useState(false);
    const [search, setSearch] = useState('');
    const [showNewChat, setShowNewChat] = useState(false);
    const [showGroupForm, setShowGroupForm] = useState(false);
    const [allUsers, setAllUsers] = useState([]);
    const [groupName, setGroupName] = useState('');
    const [selectedMembers, setSelectedMembers] = useState([]);
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const messagesEndRef = useRef(null);
    const fileInputRef = useRef(null);
    const pollRef = useRef(null);

    // Fetch rooms
    const fetchRooms = useCallback(async () => {
        try {
            const res = await chatService.getRooms();
            setRooms(res.data || []);
        } catch { /* ignore */ }
    }, []);

    // Fetch messages for active room
    const fetchMessages = useCallback(async (roomId) => {
        setMessagesLoading(true);
        try {
            const res = await chatService.getMessages(roomId, { per_page: 100 });
            const msgs = res.data?.data || [];
            setMessages(msgs.reverse());
            await chatService.markRead(roomId);
        } catch { /* ignore */ }
        setMessagesLoading(false);
    }, []);

    // Initial load
    useEffect(() => {
        (async () => {
            await fetchRooms();
            // Load all users for new chat
            try {
                const res = await fetch('/api/users?per_page=200', { credentials: 'include', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                setAllUsers(data.data?.data || []);
            } catch { /* ignore */ }
            setLoading(false);
        })();
    }, []);

    // When active room changes
    useEffect(() => {
        if (activeRoom) {
            fetchMessages(activeRoom.id);
        }
    }, [activeRoom?.id]);

    // Scroll to bottom on new messages
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length]);

    // Polling for new messages (fallback if broadcasting not configured)
    useEffect(() => {
        pollRef.current = setInterval(() => {
            fetchRooms();
            if (activeRoom) {
                (async () => {
                    try {
                        const res = await chatService.getMessages(activeRoom.id, { per_page: 100 });
                        const msgs = res.data?.data || [];
                        setMessages(msgs.reverse());
                    } catch { /* ignore */ }
                })();
            }
        }, 5000);
        return () => clearInterval(pollRef.current);
    }, [activeRoom?.id]);

    // Try to set up real-time Echo listener
    useEffect(() => {
        if (!activeRoom) return;
        let channel;
        try {
            const echo = require('@/echo').default;
            channel = echo.private(`chat.room.${activeRoom.id}`);
            channel.listen('.message.sent', (e) => {
                setMessages((prev) => [...prev, e]);
                fetchRooms();
            });
        } catch { /* Echo not configured, polling used instead */ }
        return () => {
            try { channel?.stopListening('.message.sent'); } catch {}
        };
    }, [activeRoom?.id]);

    const handleSend = async (e) => {
        e.preventDefault();
        if (!message.trim() || !activeRoom || sending) return;
        setSending(true);
        try {
            const res = await chatService.sendMessage(activeRoom.id, message.trim());
            setMessages((prev) => [...prev, res.data]);
            setMessage('');
            fetchRooms();
        } catch {
            toast.error('Failed to send message');
        }
        setSending(false);
    };

    const handleFileUpload = async (e) => {
        const file = e.target.files[0];
        if (!file || !activeRoom) return;
        const formData = new FormData();
        formData.append('file', file);
        try {
            const res = await chatService.sendFile(activeRoom.id, formData);
            setMessages((prev) => [...prev, res.data]);
            fetchRooms();
        } catch {
            toast.error('Failed to upload file');
        }
        e.target.value = '';
    };

    const handleStartPrivateChat = async (otherUser) => {
        try {
            const res = await chatService.getOrCreatePrivateRoom(otherUser.id);
            const room = res.data;
            setActiveRoom({
                id: room.id,
                name: `${otherUser.first_name} ${otherUser.last_name}`,
                type: 'private',
                members: room.members,
            });
            setShowNewChat(false);
            fetchRooms();
        } catch {
            toast.error('Failed to start chat');
        }
    };

    const handleCreateGroup = async (e) => {
        e.preventDefault();
        if (!groupName.trim() || selectedMembers.length === 0) return;
        try {
            const res = await chatService.createGroupRoom({
                name: groupName.trim(),
                member_ids: selectedMembers,
            });
            setActiveRoom({
                id: res.data.id,
                name: groupName,
                type: 'group',
                members: res.data.members,
            });
            setShowGroupForm(false);
            setGroupName('');
            setSelectedMembers([]);
            fetchRooms();
            toast.success('Group created');
        } catch {
            toast.error('Failed to create group');
        }
    };

    const filteredRooms = search
        ? rooms.filter((r) => r.name?.toLowerCase().includes(search.toLowerCase()))
        : rooms;

    const otherUsers = allUsers.filter((u) => u.id !== user?.id);

    // Group messages by date
    const groupedMessages = messages.reduce((groups, msg) => {
        const date = new Date(msg.created_at).toDateString();
        if (!groups[date]) groups[date] = [];
        groups[date].push(msg);
        return groups;
    }, {});

    if (loading) return <LoadingSpinner />;

    return (
        <div className="flex h-[calc(100vh-7rem)] overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            {/* Sidebar */}
            <div className={`${sidebarOpen ? 'w-80' : 'w-0 overflow-hidden'} flex flex-col border-r transition-all duration-200`}>
                {/* Sidebar Header */}
                <div className="flex h-14 items-center justify-between border-b px-4">
                    <h2 className="text-lg font-bold text-gray-900">Messages</h2>
                    <div className="flex gap-1">
                        <button
                            onClick={() => setShowGroupForm(true)}
                            className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            title="New Group"
                        >
                            <HiOutlineUsers className="h-5 w-5" />
                        </button>
                        <button
                            onClick={() => setShowNewChat(true)}
                            className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            title="New Chat"
                        >
                            <HiOutlinePlus className="h-5 w-5" />
                        </button>
                    </div>
                </div>

                {/* Search */}
                <div className="p-3">
                    <div className="relative">
                        <HiOutlineSearch className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search conversations..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500"
                        />
                    </div>
                </div>

                {/* Room List */}
                <div className="flex-1 overflow-y-auto">
                    {filteredRooms.length === 0 ? (
                        <div className="px-4 py-8 text-center text-sm text-gray-400">No conversations yet</div>
                    ) : (
                        filteredRooms.map((room) => (
                            <button
                                key={room.id}
                                onClick={() => {
                                    setActiveRoom(room);
                                    setSidebarOpen(window.innerWidth >= 768);
                                }}
                                className={`flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50 ${
                                    activeRoom?.id === room.id ? 'bg-primary-50 border-r-2 border-primary-500' : ''
                                }`}
                            >
                                <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white ${
                                    room.type === 'project' ? 'bg-green-500' : room.type === 'group' ? 'bg-purple-500' : 'bg-primary-500'
                                }`}>
                                    {room.type === 'private'
                                        ? room.name?.[0]?.toUpperCase()
                                        : room.type === 'project' ? 'P' : 'G'}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center justify-between">
                                        <p className="truncate text-sm font-semibold text-gray-900">{room.name}</p>
                                        {room.latest_message && (
                                            <span className="ml-1 text-xs text-gray-400">{timeAgo(room.latest_message.created_at)}</span>
                                        )}
                                    </div>
                                    {room.latest_message && (
                                        <p className="truncate text-xs text-gray-500">
                                            {room.latest_message.sender?.first_name}: {room.latest_message.type !== 'text' ? '(file)' : room.latest_message.body}
                                        </p>
                                    )}
                                </div>
                                {room.unread_count > 0 && (
                                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-primary-500 text-xs font-bold text-white">
                                        {room.unread_count}
                                    </span>
                                )}
                            </button>
                        ))
                    )}
                </div>
            </div>

            {/* Chat Area */}
            <div className="flex flex-1 flex-col">
                {!activeRoom ? (
                    <div className="flex flex-1 items-center justify-center">
                        <div className="text-center">
                            <HiOutlineChat className="mx-auto h-16 w-16 text-gray-200" />
                            <p className="mt-4 text-lg font-medium text-gray-400">Select a conversation</p>
                            <p className="text-sm text-gray-300">Choose from the sidebar or start a new chat</p>
                        </div>
                    </div>
                ) : (
                    <>
                        {/* Chat Header */}
                        <div className="flex h-14 items-center justify-between border-b px-4">
                            <div className="flex items-center gap-3">
                                <button
                                    onClick={() => setSidebarOpen(!sidebarOpen)}
                                    className="rounded-lg p-1 text-gray-400 hover:text-gray-600 md:hidden"
                                >
                                    <HiOutlineChat className="h-5 w-5" />
                                </button>
                                <div className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white ${
                                    activeRoom.type === 'project' ? 'bg-green-500' : activeRoom.type === 'group' ? 'bg-purple-500' : 'bg-primary-500'
                                }`}>
                                    {activeRoom.type === 'private' ? activeRoom.name?.[0]?.toUpperCase() : activeRoom.type === 'project' ? 'P' : 'G'}
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-gray-900">{activeRoom.name}</p>
                                    <p className="text-xs text-gray-400">
                                        {activeRoom.type === 'private' ? 'Private chat' : `${activeRoom.members?.length || 0} members`}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Messages */}
                        <div className="flex-1 overflow-y-auto px-4 py-4">
                            {messagesLoading ? (
                                <LoadingSpinner size="sm" />
                            ) : messages.length === 0 ? (
                                <div className="flex h-full items-center justify-center">
                                    <p className="text-sm text-gray-400">No messages yet. Say hello!</p>
                                </div>
                            ) : (
                                Object.entries(groupedMessages).map(([date, msgs]) => (
                                    <div key={date}>
                                        <div className="my-4 flex items-center gap-3">
                                            <div className="h-px flex-1 bg-gray-200" />
                                            <span className="text-xs font-medium text-gray-400">{formatDate(msgs[0].created_at)}</span>
                                            <div className="h-px flex-1 bg-gray-200" />
                                        </div>
                                        {msgs.map((msg, i) => {
                                            const isMe = msg.user_id === user?.id || msg.sender?.id === user?.id;
                                            const showAvatar = i === 0 || msgs[i - 1]?.user_id !== msg.user_id;
                                            return (
                                                <div key={msg.id} className={`flex gap-2.5 ${isMe ? 'flex-row-reverse' : ''} ${showAvatar ? 'mt-3' : 'mt-0.5'}`}>
                                                    {showAvatar ? (
                                                        <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white ${isMe ? 'bg-primary-500' : 'bg-gray-400'}`}>
                                                            {(msg.sender?.first_name || '?')[0]}
                                                        </div>
                                                    ) : (
                                                        <div className="w-8 shrink-0" />
                                                    )}
                                                    <div className={`max-w-[70%] ${isMe ? 'text-right' : ''}`}>
                                                        {showAvatar && (
                                                            <p className="mb-0.5 text-xs font-medium text-gray-500">
                                                                {isMe ? 'You' : `${msg.sender?.first_name} ${msg.sender?.last_name}`}
                                                                <span className="ml-2 font-normal text-gray-300">{formatTime(msg.created_at)}</span>
                                                            </p>
                                                        )}
                                                        {msg.type === 'image' ? (
                                                            <div className={`inline-block rounded-xl ${isMe ? 'bg-primary-500' : 'bg-gray-100'} p-1`}>
                                                                <img src={`/storage/${msg.file_path}`} alt={msg.file_name} className="max-h-48 rounded-lg" />
                                                            </div>
                                                        ) : msg.type === 'file' ? (
                                                            <div className={`inline-flex items-center gap-2 rounded-xl px-3 py-2 ${isMe ? 'bg-primary-500 text-white' : 'bg-gray-100 text-gray-700'}`}>
                                                                <HiOutlinePaperClip className="h-4 w-4 shrink-0" />
                                                                <a href={`/storage/${msg.file_path}`} target="_blank" rel="noreferrer" className="text-sm underline">{msg.file_name}</a>
                                                            </div>
                                                        ) : (
                                                            <p className={`inline-block rounded-2xl px-3.5 py-2 text-sm ${isMe ? 'bg-primary-500 text-white' : 'bg-gray-100 text-gray-800'}`}>
                                                                {msg.body}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ))
                            )}
                            <div ref={messagesEndRef} />
                        </div>

                        {/* Input */}
                        <div className="border-t px-4 py-3">
                            <form onSubmit={handleSend} className="flex items-center gap-2">
                                <input type="file" ref={fileInputRef} onChange={handleFileUpload} className="hidden" />
                                <button
                                    type="button"
                                    onClick={() => fileInputRef.current?.click()}
                                    className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                >
                                    <HiOutlinePaperClip className="h-5 w-5" />
                                </button>
                                <input
                                    type="text"
                                    value={message}
                                    onChange={(e) => setMessage(e.target.value)}
                                    placeholder="Type a message..."
                                    className="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                                <button
                                    type="submit"
                                    disabled={!message.trim() || sending}
                                    className="rounded-xl bg-primary-500 p-2.5 text-white hover:bg-primary-600 disabled:opacity-40"
                                >
                                    <HiOutlinePaperAirplane className="h-5 w-5 rotate-90" />
                                </button>
                            </form>
                        </div>
                    </>
                )}
            </div>

            {/* New Private Chat Modal */}
            {showNewChat && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowNewChat(false)}>
                    <div className="mx-4 w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">New Chat</h3>
                            <button onClick={() => setShowNewChat(false)} className="text-gray-400 hover:text-gray-600">
                                <HiOutlineX className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="max-h-72 space-y-1 overflow-y-auto">
                            {otherUsers.map((u) => (
                                <button
                                    key={u.id}
                                    onClick={() => handleStartPrivateChat(u)}
                                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-gray-50"
                                >
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700">
                                        {u.first_name?.[0]}{u.last_name?.[0]}
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{u.first_name} {u.last_name}</p>
                                        <p className="text-xs text-gray-500">{u.email}</p>
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* Create Group Modal */}
            {showGroupForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowGroupForm(false)}>
                    <div className="mx-4 w-full max-w-md rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">Create Group Chat</h3>
                            <button onClick={() => setShowGroupForm(false)} className="text-gray-400 hover:text-gray-600">
                                <HiOutlineX className="h-5 w-5" />
                            </button>
                        </div>
                        <form onSubmit={handleCreateGroup} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Group Name</label>
                                <input
                                    type="text"
                                    value={groupName}
                                    onChange={(e) => setGroupName(e.target.value)}
                                    required
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Members</label>
                                <div className="max-h-48 space-y-1 overflow-y-auto rounded-lg border p-2">
                                    {otherUsers.map((u) => (
                                        <label key={u.id} className="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50">
                                            <input
                                                type="checkbox"
                                                checked={selectedMembers.includes(u.id)}
                                                onChange={(e) => {
                                                    setSelectedMembers((prev) =>
                                                        e.target.checked
                                                            ? [...prev, u.id]
                                                            : prev.filter((id) => id !== u.id)
                                                    );
                                                }}
                                                className="rounded border-gray-300 text-primary-600"
                                            />
                                            <span className="text-sm text-gray-700">{u.first_name} {u.last_name}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                            <div className="flex justify-end gap-2">
                                <button type="button" onClick={() => setShowGroupForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={!groupName.trim() || selectedMembers.length === 0} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">Create</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
