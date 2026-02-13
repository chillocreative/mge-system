<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InternalEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InternalEmailController extends Controller
{
    public function __construct(private InternalEmailService $emailService) {}

    /**
     * Get emails by folder.
     */
    public function index(Request $request): JsonResponse
    {
        $folder = $request->get('folder', 'inbox');
        $search = $request->get('search');
        $perPage = $request->integer('per_page', 20);
        $userId = $request->user()->id;

        $emails = match ($folder) {
            'sent' => $this->emailService->getSent($userId, $perPage, $search),
            'drafts' => $this->emailService->getDrafts($userId, $perPage),
            'starred' => $this->emailService->getStarred($userId, $perPage, $search),
            'trash' => $this->emailService->getTrashed($userId, $perPage),
            default => $this->emailService->getInbox($userId, $perPage, $search),
        };

        return $this->success($emails);
    }

    /**
     * Get a single email with its thread.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $data = $this->emailService->getEmail($id, $request->user()->id);

        return $this->success($data);
    }

    /**
     * Send an email.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['exists:users,id'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['exists:users,id'],
            'bcc' => ['nullable', 'array'],
            'bcc.*' => ['exists:users,id'],
            'parent_id' => ['nullable', 'exists:internal_emails,id'],
            'thread_id' => ['nullable', 'exists:internal_emails,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $email = $this->emailService->sendEmail(
            $validated,
            $request->user()->id,
            $request->file('attachments', [])
        );

        return $this->created($email, 'Email sent.');
    }

    /**
     * Reply to an email.
     */
    public function reply(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['exists:users,id'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['exists:users,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $original = \App\Models\InternalEmail::findOrFail($id);

        $validated['parent_id'] = $id;
        $validated['thread_id'] = $original->thread_id ?: $original->id;
        $validated['subject'] = str_starts_with($original->subject, 'Re: ')
            ? $original->subject
            : 'Re: ' . $original->subject;

        $email = $this->emailService->sendEmail(
            $validated,
            $request->user()->id,
            $request->file('attachments', [])
        );

        return $this->created($email, 'Reply sent.');
    }

    /**
     * Save as draft.
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'to' => ['nullable', 'array'],
            'to.*' => ['exists:users,id'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['exists:users,id'],
            'draft_id' => ['nullable', 'exists:internal_emails,id'],
        ]);

        $draft = $this->emailService->saveDraft(
            $validated,
            $request->user()->id,
            $validated['draft_id'] ?? null
        );

        return $this->success($draft, 'Draft saved.');
    }

    /**
     * Toggle star.
     */
    public function toggleStar(int $id, Request $request): JsonResponse
    {
        $starred = $this->emailService->toggleStar($id, $request->user()->id);

        return $this->success(['starred' => $starred]);
    }

    /**
     * Move to trash.
     */
    public function trash(int $id, Request $request): JsonResponse
    {
        $this->emailService->moveToTrash($id, $request->user()->id);

        return $this->success(null, 'Moved to trash.');
    }

    /**
     * Restore from trash.
     */
    public function restore(int $id, Request $request): JsonResponse
    {
        $this->emailService->restoreFromTrash($id, $request->user()->id);

        return $this->success(null, 'Restored from trash.');
    }

    /**
     * Delete a draft.
     */
    public function deleteDraft(int $id, Request $request): JsonResponse
    {
        $this->emailService->deleteDraft($id, $request->user()->id);

        return $this->success(null, 'Draft deleted.');
    }

    /**
     * Get unread count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->emailService->getUnreadCount($request->user()->id);

        return $this->success(['count' => $count]);
    }

    /**
     * Download an attachment.
     */
    public function downloadAttachment(int $attachmentId): mixed
    {
        $attachment = \App\Models\EmailAttachment::findOrFail($attachmentId);

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }
}
