import { useState, useEffect } from 'react';
import { useConfirm } from '@/context/ConfirmContext';
import leaveService from '@/services/leaveService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import useDragScroll from '@/hooks/useDragScroll';
import toast from 'react-hot-toast';
import { HiOutlineBadgeCheck } from 'react-icons/hi';

const PER_PAGE = 15;

const personName = (p) => `${p?.first_name ?? ''} ${p?.last_name ?? ''}`.trim();
const employeeName = (e) => e?.full_name || personName(e);
const days = (n) => String(Number(n));

// How many days this request goes over the balance (0 when it fits).
const excessDays = (req) => {
    const after = req.balance_after;
    if (after !== null && after !== undefined && Number(after) < 0) return -Number(after);
    return Number(req.shortfall_days) || 0;
};

export default function LeaveRecord() {
    const confirm = useConfirm();
    const dragScrollRef = useDragScroll();
    const [requests, setRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [modal, setModal] = useState(null); // { req, unpaid }
    const [processing, setProcessing] = useState(false);

    const fetchPage = async (page = 1) => {
        setLoading(true);
        try {
            const res = await leaveService.toRecord({ per_page: PER_PAGE, page });
            const rows = res.data?.data || [];
            // Recording the last row of a later page leaves it empty; step back.
            if (rows.length === 0 && page > 1) {
                await fetchPage(page - 1);
                return;
            }
            setRequests(rows);
            setPagination({
                current_page: res.data?.current_page || 1,
                last_page: res.data?.last_page || 1,
                total: res.data?.total || rows.length,
            });
        } catch (err) {
            setRequests([]);
            toast.error(err.response?.data?.message || 'Failed to load leave to record');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchPage();
    }, []);

    const submitRecord = async (req, unpaid) => {
        setProcessing(true);
        try {
            await leaveService.record(req.id, { unpaid });
            toast.success('Leave recorded');
            setModal(null);
            fetchPage(pagination.current_page);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to record leave');
        } finally {
            setProcessing(false);
        }
    };

    const handleRecord = async (req) => {
        if (excessDays(req) > 0) {
            setModal({ req, unpaid: 'none' });
            return;
        }
        const type = req.leave_type || req.leaveType;
        const ok = await confirm({
            title: 'Record leave?',
            message: `Record ${employeeName(req.employee)}'s ${type?.name || 'leave'} (${formatDate(req.start_date)} to ${formatDate(req.end_date)}, ${days(req.days_count)} day(s)) and settle the balance?`,
            confirmText: 'Record',
            danger: false,
        });
        if (ok) submitRecord(req, 'none');
    };

    const modalExcess = modal ? excessDays(modal.req) : 0;

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Leave to Record</h1>
                <p className="text-sm text-gray-500">Approved leave waiting for HR to record and settle the balance</p>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : requests.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineBadgeCheck className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">Nothing waiting to be recorded</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div ref={dragScrollRef} className="overflow-x-auto cursor-grab active:cursor-grabbing">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Employee</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Dates</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Days</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Balance after</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Filed / approved by</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {requests.map((req) => {
                                    const type = req.leave_type || req.leaveType;
                                    const after = req.balance_after;
                                    const hasBalance = after !== null && after !== undefined;
                                    return (
                                        <tr key={req.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <p className="text-sm font-medium text-gray-900">{employeeName(req.employee)}</p>
                                                {req.employee?.employee_no && <p className="text-xs text-gray-500">{req.employee.employee_no}</p>}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                    {type?.name || '-'}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                                {formatDate(req.start_date)} &rarr; {formatDate(req.end_date)}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900">{req.days_count}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right text-sm">
                                                {!hasBalance ? (
                                                    <span className="text-gray-400">-</span>
                                                ) : Number(after) < 0 ? (
                                                    <span className="font-semibold text-red-600">Over by {days(-Number(after))}</span>
                                                ) : (
                                                    <span className="text-gray-900">{days(after)}</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600">
                                                {req.creator && <p>Filed by {personName(req.creator)}</p>}
                                                {req.approved_offline ? (
                                                    <>
                                                        <p className="text-xs text-gray-500">
                                                            Approved outside the system by {req.offline_approver_name || '-'}
                                                        </p>
                                                        {req.offline_approval_note && (
                                                            <p className="text-xs text-gray-400">{req.offline_approval_note}</p>
                                                        )}
                                                        <a
                                                            href={leaveService.approvalProofUrl(req.id)}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-xs font-medium text-primary-600 hover:text-primary-700 hover:underline"
                                                        >
                                                            View proof
                                                        </a>
                                                    </>
                                                ) : (
                                                    req.approver && <p className="text-xs text-gray-500">Approved by {personName(req.approver)}</p>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right">
                                                <button
                                                    onClick={() => handleRecord(req)}
                                                    disabled={processing}
                                                    className="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                                                >
                                                    Record
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {pagination.last_page > 1 && (
                        <div className="flex items-center justify-between border-t px-4 py-3">
                            <p className="text-sm text-gray-500">
                                Page {pagination.current_page} of {pagination.last_page} &middot; {pagination.total} waiting
                            </p>
                            <div className="flex gap-2">
                                <button
                                    onClick={() => fetchPage(pagination.current_page - 1)}
                                    disabled={pagination.current_page <= 1}
                                    className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    Previous
                                </button>
                                <button
                                    onClick={() => fetchPage(pagination.current_page + 1)}
                                    disabled={pagination.current_page >= pagination.last_page}
                                    className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Over-balance choice */}
            {modal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => !processing && setModal(null)}>
                    <div className="mx-4 max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-1 text-lg font-semibold text-gray-900">Record leave</h3>
                        <p className="mb-4 text-sm text-gray-500">
                            {employeeName(modal.req.employee)} &middot; {formatDate(modal.req.start_date)} &rarr; {formatDate(modal.req.end_date)} &middot; {days(modal.req.days_count)} day(s).
                            {' '}This request goes {days(modalExcess)} day(s) over the balance.
                        </p>

                        <div className="space-y-2">
                            {[
                                { value: 'none', label: 'Record as is' },
                                { value: 'all', label: 'Declare the whole request as unpaid leave' },
                                { value: 'excess', label: `Declare only the ${days(modalExcess)} day(s) over the balance as unpaid leave` },
                            ].map((opt) => (
                                <label
                                    key={opt.value}
                                    className={`flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2.5 text-sm text-gray-700 ${
                                        modal.unpaid === opt.value ? 'border-primary-500 bg-primary-50' : 'border-gray-200 hover:bg-gray-50'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="unpaid"
                                        value={opt.value}
                                        checked={modal.unpaid === opt.value}
                                        onChange={() => setModal((m) => ({ ...m, unpaid: opt.value }))}
                                        className="mt-0.5 h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500"
                                    />
                                    <span>{opt.label}</span>
                                </label>
                            ))}
                        </div>

                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                onClick={() => setModal(null)}
                                disabled={processing}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={() => submitRecord(modal.req, modal.unpaid)}
                                disabled={processing}
                                className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                            >
                                {processing ? 'Recording...' : 'Confirm'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
