<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    /**
     * List all chat rooms for the authenticated user.
     */
    public function rooms(Request $request): JsonResponse
    {
        $rooms = $this->chatService->getUserRooms($request->user()->id);

        return $this->success($rooms);
    }

    /**
     * Get or create a private chat with another user.
     */
    public function privateRoom(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $room = $this->chatService->getOrCreatePrivateRoom(
            $request->user()->id,
            $request->user_id
        );

        $room->load('members:id,first_name,last_name');

        return $this->success($room);
    }

    /**
     * Create a group chat room.
     */
    public function createGroup(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['exists:users,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);

        $room = $this->chatService->createGroupRoom(
            $request->name,
            $request->user()->id,
            $request->member_ids,
            $request->project_id
        );

        $room->load('members:id,first_name,last_name');

        return $this->created($room, 'Chat room created.');
    }

    /**
     * Get messages for a room.
     */
    public function messages(int $roomId, Request $request): JsonResponse
    {
        $messages = $this->chatService->getMessages(
            $roomId,
            $request->integer('per_page', 50),
            $request->integer('before') ?: null
        );

        return $this->success($messages);
    }

    /**
     * Send a text message.
     */
    public function sendMessage(int $roomId, Request $request): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $this->chatService->sendMessage(
            $roomId,
            $request->user()->id,
            $request->body
        );

        return $this->created($message);
    }

    /**
     * Send a file/image message.
     */
    public function sendFile(int $roomId, Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $message = $this->chatService->sendFileMessage(
            $roomId,
            $request->user()->id,
            $request->file('file'),
            $request->body
        );

        return $this->created($message);
    }

    /**
     * Mark room as read.
     */
    public function markRead(int $roomId, Request $request): JsonResponse
    {
        $this->chatService->markAsRead($roomId, $request->user()->id);

        return $this->success(null, 'Marked as read.');
    }

    /**
     * Get total unread message count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->chatService->getTotalUnreadCount($request->user()->id);

        return $this->success(['count' => $count]);
    }
}
