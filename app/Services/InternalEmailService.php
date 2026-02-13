<?php

namespace App\Services;

use App\Events\NewInternalEmail;
use App\Models\EmailAttachment;
use App\Models\EmailRecipient;
use App\Models\InternalEmail;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InternalEmailService
{
    /**
     * Get inbox for a user (received emails, not trashed).
     */
    public function getInbox(int $userId, int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->getEmailsForUser($userId, 'inbox', $perPage, $search);
    }

    /**
     * Get sent emails.
     */
    public function getSent(int $userId, int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        $query = InternalEmail::where('from_user_id', $userId)
            ->sent()
            ->with(['sender:id,first_name,last_name', 'recipients.user:id,first_name,last_name', 'attachments'])
            ->orderByDesc('sent_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get drafts.
     */
    public function getDrafts(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return InternalEmail::where('from_user_id', $userId)
            ->drafts()
            ->with(['recipients.user:id,first_name,last_name', 'attachments'])
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /**
     * Get starred emails.
     */
    public function getStarred(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->getEmailsForUser($userId, 'starred', $perPage);
    }

    /**
     * Get trashed emails.
     */
    public function getTrashed(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->getEmailsForUser($userId, 'trash', $perPage);
    }

    /**
     * Send an email.
     */
    public function sendEmail(array $data, int $fromUserId, array $files = []): InternalEmail
    {
        return DB::transaction(function () use ($data, $fromUserId, $files) {
            $email = InternalEmail::create([
                'from_user_id' => $fromUserId,
                'subject' => $data['subject'],
                'body' => $data['body'],
                'parent_id' => $data['parent_id'] ?? null,
                'thread_id' => $data['thread_id'] ?? null,
                'is_draft' => false,
                'sent_at' => now(),
            ]);

            // Set thread_id to self if this is a new thread
            if (!$email->thread_id) {
                $email->update(['thread_id' => $email->id]);
            }

            // Add recipients
            $recipientUserIds = [];
            foreach (['to', 'cc', 'bcc'] as $type) {
                if (!empty($data[$type])) {
                    foreach ($data[$type] as $userId) {
                        EmailRecipient::create([
                            'email_id' => $email->id,
                            'user_id' => $userId,
                            'type' => $type,
                        ]);
                        $recipientUserIds[] = $userId;
                    }
                }
            }

            // Handle attachments
            foreach ($files as $file) {
                $path = $file->store('email-attachments', 'public');
                EmailAttachment::create([
                    'email_id' => $email->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }

            $email->load(['sender:id,first_name,last_name', 'recipients.user:id,first_name,last_name', 'attachments']);

            // Broadcast to each recipient
            foreach (array_unique($recipientUserIds) as $recipientId) {
                broadcast(new NewInternalEmail($email, $recipientId));
            }

            return $email;
        });
    }

    /**
     * Save as draft.
     */
    public function saveDraft(array $data, int $fromUserId, ?int $draftId = null): InternalEmail
    {
        return DB::transaction(function () use ($data, $fromUserId, $draftId) {
            $email = $draftId
                ? InternalEmail::where('id', $draftId)->where('from_user_id', $fromUserId)->where('is_draft', true)->firstOrFail()
                : new InternalEmail();

            $email->fill([
                'from_user_id' => $fromUserId,
                'subject' => $data['subject'] ?? '(No Subject)',
                'body' => $data['body'] ?? '',
                'is_draft' => true,
            ]);
            $email->save();

            // Update recipients
            $email->recipients()->delete();
            foreach (['to', 'cc', 'bcc'] as $type) {
                if (!empty($data[$type])) {
                    foreach ($data[$type] as $userId) {
                        EmailRecipient::create([
                            'email_id' => $email->id,
                            'user_id' => $userId,
                            'type' => $type,
                        ]);
                    }
                }
            }

            return $email->load(['recipients.user:id,first_name,last_name']);
        });
    }

    /**
     * Get a single email with thread.
     */
    public function getEmail(int $emailId, int $userId): array
    {
        $email = InternalEmail::with([
            'sender:id,first_name,last_name',
            'recipients.user:id,first_name,last_name',
            'attachments',
        ])->findOrFail($emailId);

        // Mark as read for recipient
        EmailRecipient::where('email_id', $emailId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Get full thread
        $thread = [];
        if ($email->thread_id) {
            $thread = InternalEmail::where('thread_id', $email->thread_id)
                ->with(['sender:id,first_name,last_name', 'recipients.user:id,first_name,last_name', 'attachments'])
                ->orderBy('created_at')
                ->get();
        }

        return [
            'email' => $email,
            'thread' => $thread,
        ];
    }

    /**
     * Toggle star.
     */
    public function toggleStar(int $emailId, int $userId): bool
    {
        $recipient = EmailRecipient::where('email_id', $emailId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $recipient->update(['starred' => !$recipient->starred]);

        return $recipient->fresh()->starred;
    }

    /**
     * Move to trash.
     */
    public function moveToTrash(int $emailId, int $userId): void
    {
        EmailRecipient::where('email_id', $emailId)
            ->where('user_id', $userId)
            ->update(['trashed_at' => now()]);
    }

    /**
     * Restore from trash.
     */
    public function restoreFromTrash(int $emailId, int $userId): void
    {
        EmailRecipient::where('email_id', $emailId)
            ->where('user_id', $userId)
            ->update(['trashed_at' => null]);
    }

    /**
     * Delete a draft.
     */
    public function deleteDraft(int $draftId, int $userId): void
    {
        $email = InternalEmail::where('id', $draftId)
            ->where('from_user_id', $userId)
            ->where('is_draft', true)
            ->firstOrFail();

        $email->attachments()->each(function ($att) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($att->file_path);
        });

        $email->delete();
    }

    /**
     * Get unread count for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        return EmailRecipient::where('user_id', $userId)
            ->unread()
            ->notTrashed()
            ->whereHas('email', fn ($q) => $q->sent())
            ->count();
    }

    /**
     * Helper to get emails for a user based on folder.
     */
    private function getEmailsForUser(int $userId, string $folder, int $perPage, ?string $search = null): LengthAwarePaginator
    {
        $query = InternalEmail::sent()
            ->whereHas('recipients', function ($q) use ($userId, $folder) {
                $q->where('user_id', $userId);
                match ($folder) {
                    'inbox' => $q->notTrashed(),
                    'starred' => $q->starred()->notTrashed(),
                    'trash' => $q->trashed(),
                    default => $q,
                };
            })
            ->with([
                'sender:id,first_name,last_name',
                'recipients' => fn ($q) => $q->where('user_id', $userId),
                'recipients.user:id,first_name,last_name',
                'attachments',
            ])
            ->orderByDesc('sent_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        // Group by thread - show only latest per thread
        $query->whereIn('id', function ($sub) use ($userId, $folder) {
            $sub->select(DB::raw('MAX(id)'))
                ->from('internal_emails')
                ->whereNotNull('sent_at')
                ->where('is_draft', false)
                ->whereExists(function ($q2) use ($userId, $folder) {
                    $q2->select(DB::raw(1))
                        ->from('email_recipients')
                        ->whereColumn('email_recipients.email_id', 'internal_emails.id')
                        ->where('email_recipients.user_id', $userId);
                    match ($folder) {
                        'inbox' => $q2->whereNull('email_recipients.trashed_at'),
                        'starred' => $q2->where('email_recipients.starred', true)->whereNull('email_recipients.trashed_at'),
                        'trash' => $q2->whereNotNull('email_recipients.trashed_at'),
                        default => null,
                    };
                })
                ->groupBy('thread_id');
        });

        return $query->paginate($perPage);
    }
}
