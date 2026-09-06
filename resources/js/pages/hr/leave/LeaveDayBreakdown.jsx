import { HiOutlineInformationCircle } from 'react-icons/hi';

/**
 * Shows the working behind a leave request's day count.
 *
 * Work at MGE runs seven days a week, so rest days differ per employee — office
 * staff rest Sat + Sun, site crews rest Sunday only. Two people requesting the
 * same dates are therefore legitimately deducted different amounts (plan 27.6b).
 *
 * That is correct, but it looks like a bug to whoever is looking at it. Showing
 * the day-by-day working settles "why is his 4 days and mine 5?" before it turns
 * into a question for HR.
 */

const REASON_LABEL = {
    rest_day: 'Rest day',
    public_holiday: 'Public holiday',
};

function formatDate(iso) {
    const date = new Date(`${iso}T00:00:00`);
    return date.toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

export default function LeaveDayBreakdown({ preview, loading, error, leaveTypeName }) {
    if (loading) {
        return (
            <div className="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 ring-1 ring-gray-200">
                Calculating…
            </div>
        );
    }

    if (error) {
        return (
            <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
                {error}
            </div>
        );
    }

    // The policy engine is off, so the server is still counting raw calendar
    // days. Say nothing rather than showing a breakdown that will not match.
    if (!preview || preview.engine_enabled === false) return null;

    const { calendar_days, deducted_days, exclusions = [], balances = {}, spans_multiple_years } = preview;

    const years = Object.keys(balances);
    const shortfall = Object.entries(preview.deducted_by_year || {}).filter(
        ([year, days]) => balances[year] && days > balances[year].available,
    );

    return (
        <div className="rounded-lg bg-gray-50 p-4 text-sm ring-1 ring-gray-200">
            <div className="flex items-start justify-between gap-4">
                <div className="space-y-1 text-gray-600">
                    <p>
                        <span className="font-medium text-gray-900">{calendar_days}</span> calendar day
                        {calendar_days === 1 ? '' : 's'} selected
                    </p>

                    {exclusions.length === 0 ? (
                        <p className="text-gray-400">No rest days or public holidays in this range</p>
                    ) : (
                        <ul className="space-y-0.5">
                            {exclusions.map((item) => (
                                <li key={item.date} className="flex gap-2">
                                    <span className="text-gray-400">−</span>
                                    <span>
                                        {formatDate(item.date)}
                                        <span className="text-gray-400">
                                            {' '}
                                            · {REASON_LABEL[item.reason] || item.reason}
                                            {item.label ? ` (${item.label})` : ''}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="shrink-0 text-right">
                    <p className="text-xs uppercase tracking-wide text-gray-400">Deducted</p>
                    <p className="text-2xl font-semibold text-gray-900">{Number(deducted_days).toFixed(1)}</p>
                    <p className="text-xs text-gray-400">day{Number(deducted_days) === 1 ? '' : 's'}</p>
                </div>
            </div>

            {spans_multiple_years && (
                <p className="mt-3 flex items-start gap-1.5 border-t border-gray-200 pt-3 text-xs text-gray-500">
                    <HiOutlineInformationCircle className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                    <span>
                        This request crosses a leave year. Each year is charged its own days:{' '}
                        {Object.entries(preview.deducted_by_year)
                            .map(([year, days]) => `${year}: ${Number(days).toFixed(1)}`)
                            .join(' · ')}
                    </span>
                </p>
            )}

            {years.length > 0 && (
                <div className="mt-3 border-t border-gray-200 pt-3">
                    {years.map((year) => {
                        const balance = balances[year];
                        return (
                            <div key={year} className="flex items-baseline justify-between text-xs text-gray-500">
                                <span>
                                    {leaveTypeName || 'Balance'} remaining
                                    {years.length > 1 ? ` in ${year}` : ''}
                                </span>
                                <span className="font-medium text-gray-700">
                                    {Number(balance.available).toFixed(1)} / {Number(balance.entitled).toFixed(1)}
                                </span>
                            </div>
                        );
                    })}

                    {/* Where two leave types share a cap, say so — otherwise the
                        number appears to move on its own (plan 7.3.7). */}
                    {balances[years[0]]?.pool && (
                        <p className="mt-1.5 text-xs text-gray-400">
                            Shares a combined limit of {Number(balances[years[0]].pool.cap).toFixed(0)} days
                            {' '}({balances[years[0]].pool.name}).
                        </p>
                    )}
                </div>
            )}

            {shortfall.length > 0 && (
                <p className="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700 ring-1 ring-red-200">
                    This request needs more days than remain
                    {shortfall.length === 1 && years.length > 1 ? ` in ${shortfall[0][0]}` : ''}. It will be
                    rejected on submit.
                </p>
            )}
        </div>
    );
}
