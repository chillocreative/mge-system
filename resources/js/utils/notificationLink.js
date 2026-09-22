/**
 * Resolve where a notification should navigate to when clicked.
 *
 * New notifications carry an explicit `data.link` (see LeaveStatusNotification,
 * SystemNotification etc). Older rows stored before that existed have no `link`,
 * so this derives a best-effort route from `data.type` / `data.action_type`
 * instead of dumping the user on a blank page.
 */
export function notificationLink(n) {
    const data = n?.data || {};

    if (data.link) return data.link;

    const key = data.action_type || data.type;
    if (typeof key === 'string') {
        if (key.startsWith('leave_awaiting_') || key === 'leave_submitted') {
            return '/hr/leave/approvals';
        }
        if (key.startsWith('leave_')) {
            return '/leave/my';
        }
    }

    if (data.type === 'project' && data.project_id) {
        return `/projects/${data.project_id}`;
    }

    return '/notifications';
}

export default notificationLink;
