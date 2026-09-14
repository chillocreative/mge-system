/**
 * Shared date-display formatting for the whole app.
 *
 * Malaysian-standard dd/mm/yyyy — composed manually (not `toLocaleDateString`)
 * so the output doesn't drift with the browser's locale/environment.
 *
 * Use ONLY for read-only display text (`<span>`, `<td>`, etc.). Never use
 * this to populate an `<input type="date">` value — those must stay in the
 * native yyyy-mm-dd format, and the state/form values that feed them must
 * keep using the raw ISO value untouched.
 */

function toDate(value) {
    if (!value) return null;
    if (value instanceof Date) return isNaN(value.getTime()) ? null : value;

    // Plain "yyyy-mm-dd" (and "yyyy-mm-ddTHH:mm:ss...") strings are parsed as
    // UTC by `new Date(...)`, which can shift the displayed day near
    // midnight depending on the viewer's timezone. Parse the date-only part
    // manually so the calendar day never shifts.
    const dateOnlyMatch = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));
    if (dateOnlyMatch) {
        const [, y, m, d] = dateOnlyMatch;
        const parsed = new Date(Number(y), Number(m) - 1, Number(d));

        return isNaN(parsed.getTime()) ? null : parsed;
    }

    const parsed = new Date(value);

    return isNaN(parsed.getTime()) ? null : parsed;
}

/**
 * Format a date value as dd/mm/yyyy for display. Returns `fallback`
 * (default '-') for null/undefined/invalid input.
 */
export function formatDate(value, fallback = '-') {
    const date = toDate(value);
    if (!date) return fallback;

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    return `${day}/${month}/${year}`;
}

/**
 * Format a date value as dd/mm/yyyy plus a separately-supplied HH:mm time
 * string, e.g. formatDate + " · 14:30". Pass the time exactly as already
 * stored (no timezone conversion) since times in this app are plain
 * HH:mm strings, not full timestamps.
 */
export function formatDateTime(value, time, fallback = '-') {
    const datePart = formatDate(value, fallback);
    if (datePart === fallback) return fallback;

    return time ? `${datePart} · ${time}` : datePart;
}
