import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import leaveService from '@/services/leaveService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineCalendar,
    HiOutlineBan,
    HiOutlineScale,
} from 'react-icons/hi';

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-600',
};

const statusLabel = (req) => {
    if (req.status === 'pending') {
        return req.current_approval_level === 'director' ? 'Awaiting Director' : 'Awaiting Manager';
    }
    return req.status;
};

export default function MyLeave() {
    const currentYear = new Date().getFullYear();
    const [year, setYear] = useState(currentYear);
    const [employee, setEmployee] = useState(null);
    const [types, setTypes] = useState([]);
    const [balances, setBalances] = useState([]);
    const [requests, setRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [noProfile, setNoProfile] = useState(false);

    const fetchRequests = useCallback(async (employeeId) => {
        try {
            const res = await leaveService.list({ employee_id: employeeId, per_page: 100 });
            setRequests(res.data?.data || []);
        } catch {
            setRequests([]);
        }
    }, []);

    // Resolve linked employee + leave types once.
    useEffect(() => {
        Promise.all([leaveService.myEmployee(), leaveService.listTypes()])
            .then(([meRes, typesRes]) => {
                const me = meRes.data || null;
                setTypes(typesRes.data || []);
                if (!me) {
                    setNoProfile(true);
                    return;
                }
                setEmployee(me);
                return fetchRequests(me.id);
            })
            .catch(() => setNoProfile(true))
            .finally(() => setLoading(false));
    }, [fetchRequests]);

    // (Re)load balances whenever the employee or year changes.
    useEffect(() => {
        if (!employee) return;
        leaveService.balance({ employee_id: employee.id, year })
            .then((r) => setBalances(r.data || []))
            .catch(() => setBalances([]));
    }, [employee, year]);

    const handleCancel = async (id) => {
        if (!confirm('Cancel this leave request?')) return;
        try {
            await leaveService.cancel(id);
            toast.success('Leave request cancelled');
            fetchRequests(employee.id);
            leaveService.balance({ employee_id: employee.id, year })
                .then((r) => setBalances(r.data || []))
                .catch(() => {});
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to cancel');
        }
    };

    // Merge active leave types with any existing balance rows so entitlement
    // shows even before a leave has ever been approved (balance rows are only
    // created on first approval).
    const balanceRows = types
        .filter((t) => t.is_active)
        .map((t) => {
            const row = balances.find((b) => String(b.leave_type_id) === String(t.id));
            const entitled = row?.entitled_days ?? t.default_days_per_year ?? 0;
            const used = row?.used_days ?? 0;
            return {
                id: t.id,
                name: t.name,
                entitled: Number(entitled),
                used: Number(used),
                remaining: Number(entitled) - Number(used),
            };
        });

    const years = Array.from({ length: 5 }, (_, i) => currentYear - i);

    if (loading) return <LoadingSpinner />;

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">My Leave</h1>
                    <p className="text-sm text-gray-500">Your leave balance and applications</p>
                </div>
                {!noProfile && (
                    <Link
                        to="/hr/leave/apply"
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        Apply Leave
                    </Link>
                )}
            </div>

            {noProfile ? (
                <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700 ring-1 ring-amber-200">
                    No employee profile is linked to your account. Please ask HR to link your staff record before applying for leave.
                </div>
            ) : (
                <>
                    {/* Balance */}
                    <div className="mb-8">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-gray-900">Leave Balance</h2>
                            <select
                                value={year}
                                onChange={(e) => setYear(Number(e.target.value))}
                                className="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                {years.map((y) => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        </div>

                        {balanceRows.length === 0 ? (
                            <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                                <HiOutlineScale className="mx-auto h-12 w-12 text-gray-300" />
                                <p className="mt-2 text-sm text-gray-500">No leave types configured</p>
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Leave Type</th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Entitled</th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Used</th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Remaining</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {balanceRows.map((bal) => (
                                                <tr key={bal.id} className="hover:bg-gray-50">
                                                    <td className="px-4 py-3 text-sm font-medium text-gray-900">{bal.name}</td>
                                                    <td className="px-4 py-3 text-right text-sm text-gray-600">{bal.entitled}</td>
                                                    <td className="px-4 py-3 text-right text-sm text-gray-600">{bal.used}</td>
                                                    <td className="px-4 py-3 text-right text-sm font-semibold text-green-600">{bal.remaining}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* My requests */}
                    <div>
                        <h2 className="mb-3 text-lg font-semibold text-gray-900">My Requests</h2>
                        {requests.length === 0 ? (
                            <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                                <HiOutlineCalendar className="mx-auto h-12 w-12 text-gray-300" />
                                <p className="mt-2 text-sm text-gray-500">No leave requests yet</p>
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Dates</th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Days</th>
                                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {requests.map((req) => (
                                                <tr key={req.id} className="hover:bg-gray-50">
                                                    <td className="px-4 py-3">
                                                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                            {req.leave_type?.name || '-'}
                                                        </span>
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                                        {req.start_date} &rarr; {req.end_date}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900">{req.days_count}</td>
                                                    <td className="px-4 py-3">
                                                        <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[req.status]}`}>
                                                            {statusLabel(req)}
                                                        </span>
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                                        {(req.status === 'pending' || req.status === 'approved') && (
                                                            <button
                                                                onClick={() => handleCancel(req.id)}
                                                                className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                                title="Cancel"
                                                            >
                                                                <HiOutlineBan className="h-4 w-4" />
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}
