import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import leaveService from '@/services/leaveService';
import { HiOutlineExternalLink } from 'react-icons/hi';

/**
 * Leave balances for one employee, shown on their staff detail page.
 *
 * Saves opening a second screen just to answer "how many days does this person
 * have left?", which is the question HR asks most often while looking at
 * someone's record.
 *
 * Permission note: the staff detail page is gated on `staff.view`, but the
 * balance endpoint requires `leave.view`. Those are different permissions, so
 * this card checks for itself rather than assuming whoever opened the page may
 * also see leave data. If they may not, the card simply is not rendered — the
 * parent decides that, and a 403 never reaches the screen.
 */
export default function StaffLeaveBalanceCard({ employeeId }) {
    const year = new Date().getFullYear();
    const [balances, setBalances] = useState([]);
    const [loading, setLoading] = useState(true);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!employeeId) return;

        let cancelled = false;
        setLoading(true);

        leaveService
            .balance({ employee_id: employeeId, year })
            .then((res) => {
                if (cancelled) return;
                setBalances(res.data || []);
                setFailed(false);
            })
            .catch(() => {
                if (!cancelled) setFailed(true);
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [employeeId, year]);

    // A shared cap (Sick + Hospitalisation) makes one balance move when the
    // other is used. Say so, or the number looks like it changed on its own.
    const pool = balances.find((b) => b.pool)?.pool;

    return (
        <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 lg:col-span-2">
            <div className="mb-3 flex items-center justify-between gap-4">
                <h2 className="text-sm font-semibold uppercase text-gray-500">
                    Leave Balances <span className="ml-1 font-normal normal-case text-gray-400">({year})</span>
                </h2>
                <Link
                    to="/hr/leave/balances"
                    className="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700"
                >
                    View all <HiOutlineExternalLink className="h-3.5 w-3.5" />
                </Link>
            </div>

            {loading && <p className="py-2 text-sm text-gray-400">Loading…</p>}

            {!loading && failed && (
                <p className="py-2 text-sm text-gray-400">Leave balances are unavailable.</p>
            )}

            {!loading && !failed && balances.length === 0 && (
                <p className="py-2 text-sm text-gray-400">No leave balance records for {year}.</p>
            )}

            {!loading && !failed && balances.length > 0 && (
                <>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                                    <th className="py-2 text-left font-medium">Leave Type</th>
                                    <th className="py-2 text-right font-medium">Entitled</th>
                                    <th className="py-2 text-right font-medium">Used</th>
                                    <th className="py-2 text-right font-medium">Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                {balances.map((b) => (
                                    <tr key={b.leave_type_id} className="border-b border-gray-100 last:border-0">
                                        <td className="py-2 text-gray-700">
                                            {b.leave_type?.name || b.leave_type?.code || '-'}
                                        </td>
                                        <td className="py-2 text-right text-gray-500">{Number(b.entitled_days)}</td>
                                        <td className="py-2 text-right text-gray-500">{Number(b.used_days)}</td>
                                        <td className="py-2 text-right font-semibold text-gray-900">
                                            {Number(b.remaining_days)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {pool && (
                        <p className="mt-3 text-xs text-gray-400">
                            {pool.name} share a combined limit of {Number(pool.cap)} days per year.
                        </p>
                    )}
                </>
            )}
        </div>
    );
}
