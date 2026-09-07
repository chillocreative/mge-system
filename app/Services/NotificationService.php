<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Central entry point for raising notifications (plan §26.2, the shared engine).
 *
 * Every send is:
 *  - preference-aware — a recipient can set a category to instant / digest / off,
 *    and opt into email per category;
 *  - channel-gated — in-app (database) is the default; email is added only when
 *    the global switch config('notifications.email_enabled') is on AND the
 *    recipient opted in AND they are under the hourly cap;
 *  - logged — every send, skip and failure is recorded in notification_logs;
 *  - failure-isolated — a notification problem can never break the surrounding
 *    business workflow.
 *
 * Existing callers are unaffected: the new $category argument is optional and
 * defaults to 'general', for which the default preference is instant, in-app.
 */
class NotificationService
{
    public function notify(
        ?User $user,
        string $title,
        string $message,
        string $type = 'info',
        ?string $link = null,
        array $extra = [],
        string $category = 'general',
    ): void {
        if (! $user) {
            return;
        }

        try {
            $channels = $this->resolveChannels($user, $category);

            if ($channels === []) {
                $this->log($user, $category, 'database', $title, 'skipped');

                return;
            }

            $user->notify(new SystemNotification($title, $message, $type, $link, $extra, $channels));

            foreach ($channels as $channel) {
                $this->log($user, $category, $channel, $title, 'sent');
            }
        } catch (\Throwable $e) {
            Log::warning('Notification failed: '.$e->getMessage());
            $this->log($user, $category, 'database', $title, 'failed', $e->getMessage());
        }
    }

    /**
     * Decide which channels a notification should go out on for this recipient.
     *
     * @return array<int, string>
     */
    private function resolveChannels(User $user, string $category): array
    {
        $pref = NotificationPreference::firstWhere(['user_id' => $user->id, 'type' => $category]);

        $mode = $pref->mode ?? 'instant';

        // The recipient switched this category off entirely.
        if ($mode === 'off') {
            return [];
        }

        $channels = ['database'];

        // Email is added only when everything lines up. 'digest' delivers in-app
        // now but is not emailed — the digest batch runner is a later phase, and
        // sending an instant email under a "digest" choice would contradict it.
        $emailWanted = $mode === 'instant'
            && (bool) ($pref->email_enabled ?? false)
            && config('notifications.email_enabled')
            && ! empty($user->email);

        if ($emailWanted && $this->underEmailCap($user)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Hourly per-user email cap — a safety net against a flood, and against
     * cPanel's domain-wide sending limit (27.10.1). In-app is never capped.
     */
    private function underEmailCap(User $user): bool
    {
        $cap = (int) config('notifications.email_max_per_hour', 30);

        if ($cap <= 0) {
            return false;
        }

        $key = 'notif:mail:'.$user->id.':'.now()->format('YmdH');
        $sent = (int) Cache::get($key, 0);

        if ($sent >= $cap) {
            $this->log($user, 'general', 'mail', 'Email cap reached', 'skipped', "hourly cap {$cap} reached");

            return false;
        }

        Cache::put($key, $sent + 1, now()->addHour());

        return true;
    }

    private function log(?User $user, string $category, string $channel, string $title, string $status, ?string $error = null): void
    {
        try {
            NotificationLog::create([
                'user_id' => $user?->id,
                'type' => $category,
                'channel' => $channel,
                'title' => mb_substr($title, 0, 255),
                'status' => $status,
                'error' => $error,
            ]);
        } catch (\Throwable $e) {
            // Logging must never itself break a send.
            Log::warning('Notification log write failed: '.$e->getMessage());
        }
    }

    /**
     * @param  iterable<User>  $users
     */
    public function notifyMany(iterable $users, string $title, string $message, string $type = 'info', ?string $link = null, array $extra = [], string $category = 'general'): void
    {
        foreach ($users as $user) {
            $this->notify($user, $title, $message, $type, $link, $extra, $category);
        }
    }

    /**
     * Notify a set of user ids (e.g. project members just added).
     *
     * @param  array<int>  $ids
     */
    public function notifyUserIds(array $ids, string $title, string $message, string $type = 'info', ?string $link = null, array $extra = [], string $category = 'general'): void
    {
        $ids = array_filter(array_unique($ids));
        if (empty($ids)) {
            return;
        }

        $this->notifyMany(User::whereIn('id', $ids)->get(), $title, $message, $type, $link, $extra, $category);
    }

    /**
     * Notify every active user holding a permission — used to reach approvers
     * (e.g. "users.approve", "training.approve") regardless of their role.
     */
    public function notifyByPermission(string $permission, string $title, string $message, string $type = 'info', ?string $link = null, array $extra = [], string $category = 'general'): void
    {
        try {
            $users = User::permission($permission)->where('status', 'active')->get();
            $this->notifyMany($users, $title, $message, $type, $link, $extra, $category);
        } catch (\Throwable $e) {
            Log::warning('Notification by permission failed: '.$e->getMessage());
        }
    }
}
