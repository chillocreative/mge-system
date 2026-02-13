<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatService
{
    /**
     * Get all chat rooms for a user with latest message and unread counts.
     */
    public function getUserRooms(int $userId): Collection
    {
        $rooms = ChatRoom::forUser($userId)
            ->with(['members:id,first_name,last_name', 'latestMessage.sender:id,first_name,last_name', 'project:id,name,code'])
            ->get();

        $user = User::find($userId);

        return $rooms->map(function (ChatRoom $room) use ($user) {
            return [
                'id' => $room->id,
                'name' => $room->getDisplayNameForUser($user),
                'type' => $room->type,
                'project' => $room->project ? ['id' => $room->project->id, 'name' => $room->project->name] : null,
                'members' => $room->members->map(fn ($m) => [
                    'id' => $m->id,
                    'first_name' => $m->first_name,
                    'last_name' => $m->last_name,
                ]),
                'latest_message' => $room->latestMessage ? [
                    'body' => $room->latestMessage->body,
                    'type' => $room->latestMessage->type,
                    'sender' => $room->latestMessage->sender ? [
                        'first_name' => $room->latestMessage->sender->first_name,
                    ] : null,
                    'created_at' => $room->latestMessage->created_at->toISOString(),
                ] : null,
                'unread_count' => $room->unreadCountForUser($user->id),
            ];
        })->sortByDesc(fn ($r) => $r['latest_message']['created_at'] ?? '')->values();
    }

    /**
     * Get or create a private chat room between two users.
     */
    public function getOrCreatePrivateRoom(int $userId, int $otherUserId): ChatRoom
    {
        // Find existing private room with exactly these two users
        $room = ChatRoom::private()
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->whereHas('members', fn ($q) => $q->where('user_id', $otherUserId))
            ->withCount('members')
            ->having('members_count', '=', 2)
            ->first();

        if ($room) {
            return $room;
        }

        return DB::transaction(function () use ($userId, $otherUserId) {
            $room = ChatRoom::create([
                'type' => 'private',
                'created_by' => $userId,
            ]);
            $room->members()->attach([$userId, $otherUserId]);
            return $room;
        });
    }

    /**
     * Create a group chat room.
     */
    public function createGroupRoom(string $name, int $createdBy, array $memberIds, ?int $projectId = null): ChatRoom
    {
        return DB::transaction(function () use ($name, $createdBy, $memberIds, $projectId) {
            $room = ChatRoom::create([
                'name' => $name,
                'type' => $projectId ? 'project' : 'group',
                'project_id' => $projectId,
                'created_by' => $createdBy,
            ]);

            $allMembers = array_unique(array_merge([$createdBy], $memberIds));
            $room->members()->attach($allMembers);

            return $room;
        });
    }

    /**
     * Get messages for a chat room (paginated, newest last).
     */
    public function getMessages(int $roomId, int $perPage = 50, ?int $before = null): LengthAwarePaginator
    {
        $query = ChatMessage::where('chat_room_id', $roomId)
            ->with('sender:id,first_name,last_name')
            ->orderByDesc('created_at');

        if ($before) {
            $query->where('id', '<', $before);
        }

        return $query->paginate($perPage);
    }

    /**
     * Send a text message.
     */
    public function sendMessage(int $roomId, int $userId, string $body): ChatMessage
    {
        $message = ChatMessage::create([
            'chat_room_id' => $roomId,
            'user_id' => $userId,
            'body' => $body,
            'type' => 'text',
        ]);

        $message->load('sender:id,first_name,last_name');

        // Mark as read for sender
        $this->markAsRead($roomId, $userId);

        broadcast(new ChatMessageSent($message))->toOthers();

        return $message;
    }

    /**
     * Send a file message.
     */
    public function sendFileMessage(int $roomId, int $userId, $file, ?string $body = null): ChatMessage
    {
        $path = $file->store('chat-files', 'public');
        $isImage = str_starts_with($file->getMimeType(), 'image/');

        $message = ChatMessage::create([
            'chat_room_id' => $roomId,
            'user_id' => $userId,
            'body' => $body ?: $file->getClientOriginalName(),
            'type' => $isImage ? 'image' : 'file',
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ]);

        $message->load('sender:id,first_name,last_name');

        $this->markAsRead($roomId, $userId);

        broadcast(new ChatMessageSent($message))->toOthers();

        return $message;
    }

    /**
     * Mark all messages in a room as read for a user.
     */
    public function markAsRead(int $roomId, int $userId): void
    {
        DB::table('chat_room_user')
            ->where('chat_room_id', $roomId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }

    /**
     * Add members to a group/project room.
     */
    public function addMembers(int $roomId, array $userIds): void
    {
        $room = ChatRoom::findOrFail($roomId);
        $existing = $room->members()->pluck('user_id')->toArray();
        $new = array_diff($userIds, $existing);

        if (!empty($new)) {
            $room->members()->attach($new);
        }
    }

    /**
     * Get total unread count across all rooms for a user.
     */
    public function getTotalUnreadCount(int $userId): int
    {
        $rooms = ChatRoom::forUser($userId)->get();
        return $rooms->sum(fn ($room) => $room->unreadCountForUser($userId));
    }
}
