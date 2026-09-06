<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Log;

/**
 * Central entry point for raising in-app notifications. Every send is wrapped
 * so a notification failure can never break the surrounding business workflow.
 */
class NotificationService
{
    public function notify(?User $user, string $title, string $message, string $type = 'info', ?string $link = null, array $extra = []): void
    {
        if (! $user) {
            return;
        }

        try {
            $user->notify(new SystemNotification($title, $message, $type, $link, $extra));
        } catch (\Throwable $e) {
            Log::warning('Notification failed: '.$e->getMessage());
        }
    }

    /**
     * @param  iterable<User>  $users
     */
    public function notifyMany(iterable $users, string $title, string $message, string $type = 'info', ?string $link = null, array $extra = []): void
    {
        foreach ($users as $user) {
            $this->notify($user, $title, $message, $type, $link, $extra);
        }
    }

    /**
     * Notify a set of user ids (e.g. project members just added).
     *
     * @param  array<int>  $ids
     */
    public function notifyUserIds(array $ids, string $title, string $message, string $type = 'info', ?string $link = null, array $extra = []): void
    {
        $ids = array_filter(array_unique($ids));
        if (empty($ids)) {
            return;
        }

        $this->notifyMany(User::whereIn('id', $ids)->get(), $title, $message, $type, $link, $extra);
    }

    /**
     * Notify every active user holding a permission — used to reach approvers
     * (e.g. "users.approve", "training.approve") regardless of their role.
     */
    public function notifyByPermission(string $permission, string $title, string $message, string $type = 'info', ?string $link = null, array $extra = []): void
    {
        try {
            $users = User::permission($permission)->where('status', 'active')->get();
            $this->notifyMany($users, $title, $message, $type, $link, $extra);
        } catch (\Throwable $e) {
            Log::warning('Notification by permission failed: '.$e->getMessage());
        }
    }
}
