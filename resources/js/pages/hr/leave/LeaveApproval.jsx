import { useState, useEffect } from 'react';
import leaveService from '@/services/leaveService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlineCheck,
    HiOutlineX,
    HiOutlineClipboardCheck,
} from 'react-icons/hi';

const stageLabel = (req) => (req.current_approval_level === 'director' ? 'Awaiting Director' : 'Awaiting Manager');

export default function LeaveApproval() {
    const [requests, setRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [pagination, setPagination] = useState({});
    const [rejectTarget, setRejectTarget] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const fetchPending = async (page = 1) => {
        setLoading(true);
        try {
            const res = await leaveService.pendingApprovals({ page });
            setRequests(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setRequests([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchPending();
    }, []);

    const handleApprove = async (id) => {
        try {
            await leaveService.approve(id);
            toast.success('Leave approved');
            fetchPending();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to approve');
        }
    };

    const handleReject = async () => {
        if (!rejectTarget) return;
        setProcessing(true);
        try {
            await leaveService.reject(rejectTarget.id, { rejection_reason: rejectReason });
            toast.success('Leave rejected');
            setRejectTarget(null);
            setRejectReason('');
            fetchPending();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to reject');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Leave Approvals</h1>
                <p className="text-sm text-gray-500">Pending leave requests awaiting your decision</p>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : requests.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineClipboardCheck className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No pending requests</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Employee</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Stage</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Dates</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Days</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Reason</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {requests.map((req) => (
                                    <tr key={req.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="text-sm font-medium text-gray-900">{req.employee?.full_name || `${req.employee?.first_name ?? ''} ${req.employee?.last_name ?? ''}`}</p>
                                            {req.employee?.employee_no && <p className="text-xs text-gray-500">{req.employee.employee_no}</p>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                {req.leave_type?.name || '-'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${req.current_approval_level === 'director' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}`}>
                                                {stageLabel(req)}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                            {req.start_date} &rarr; {req.end_date}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900">{req.days_count}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{req.reason || '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    onClick={() => handleApprove(req.id)}
                                                    className="rounded p-1.5 text-gray-400 hover:bg-green-50 hover:text-green-600"
                                                    title="Approve"
                                                >
                                                    <HiOutlineCheck className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={() => { setRejectTarget(req); setRejectReason(''); }}
                                                    className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                    title="Reject"
                                                >
                                                    <HiOutlineX className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {pagination.last_page > 1 && (
                        <div className="flex items-center justify-between border-t px-4 py-3">
                            <p className="text-sm text-gray-500">
                                Showing {pagination.from}-{pagination.to} of {pagination.total}
                            </p>
                            <div className="flex gap-1">
                                {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                                    <button
                                        key={page}
                                        onClick={() => fetchPending(page)}
                                        className={`rounded px-3 py-1 text-sm ${
                                            page === pagination.current_page
                                                ? 'bg-primary-600 text-white'
                                                : 'text-gray-600 hover:bg-gray-100'
                                        }`}
                                    >
                                        {page}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Reject reason modal */}
            {rejectTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setRejectTarget(null)}>
                    <div className="mx-4 w-full max-w-md rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Reject Leave Request</h3>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Reason</label>
                        <textarea
                            rows={3}
                            value={rejectReason}
                            onChange={(e) => setRejectReason(e.target.value)}
                            placeholder="Provide a reason for rejection (optional)"
                            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        />
                        <div className="mt-4 flex justify-end gap-2">
                            <button
                                onClick={() => setRejectTarget(null)}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleReject}
                                disabled={processing}
                                className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                            >
                                {processing ? 'Rejecting...' : 'Reject'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
