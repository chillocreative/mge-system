import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import financeService from '@/services/financeService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineDocumentText,
    HiOutlineEye,
    HiOutlineDownload,
    HiOutlineTrash,
    HiOutlinePaperAirplane,
} from 'react-icons/hi';

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    sent: 'bg-blue-100 text-blue-700',
    paid: 'bg-green-100 text-green-700',
    partially_paid: 'bg-yellow-100 text-yellow-700',
    overdue: 'bg-red-100 text-red-700',
    cancelled: 'bg-red-100 text-red-600',
};

function formatCurrency(val, currency = 'PKR') {
    return `${currency} ${Number(val || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;
}

export default function Invoices() {
    const { can } = useAuth();
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [pagination, setPagination] = useState({});

    const fetchInvoices = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            const res = await financeService.listInvoices(params);
            setInvoices(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setInvoices([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchInvoices(), 400);
        return () => clearTimeout(timer);
    }, [search, statusFilter]);

    useEffect(() => { fetchInvoices(); }, []);

    const handleMarkSent = async (id) => {
        try {
            await financeService.markAsSent(id);
            toast.success('Invoice marked as sent');
            fetchInvoices();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to mark as sent');
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this invoice?')) return;
        try {
            await financeService.deleteInvoice(id);
            toast.success('Invoice deleted');
            fetchInvoices();
        } catch {
            toast.error('Failed to delete invoice');
        }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Invoices</h1>
                    <p className="text-sm text-gray-500">Create and manage client invoices</p>
                </div>
                {can('finance.manage-budgets') && (
                    <Link
                        to="/finance/invoices/create"
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                    >
                        <HiOutlinePlus className="h-5 w-5" />
                        New Invoice
                    </Link>
                )}
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search invoices..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    />
                </div>
                <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="partially_paid">Partially Paid</option>
                    <option value="paid">Paid</option>
                    <option value="overdue">Overdue</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : invoices.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineDocumentText className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No invoices found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Invoice #</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Client</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Balance</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Due Date</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {invoices.map((inv) => (
                                    <tr key={inv.id} className="hover:bg-gray-50">
                                        <td className="whitespace-nowrap px-4 py-3 text-sm font-medium text-primary-600">
                                            <Link to={`/finance/invoices/${inv.id}`}>{inv.invoice_number}</Link>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700">
                                            {inv.client?.company_name || '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[inv.status] || 'bg-gray-100 text-gray-600'}`}>
                                                {inv.status?.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900">
                                            {formatCurrency(inv.total, inv.currency)}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700">
                                            {inv.balance_due > 0 ? (
                                                <span className="text-red-600 font-medium">{formatCurrency(inv.balance_due, inv.currency)}</span>
                                            ) : (
                                                <span className="text-green-600">Paid</span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                            {inv.due_date}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link
                                                    to={`/finance/invoices/${inv.id}`}
                                                    className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                    title="View"
                                                >
                                                    <HiOutlineEye className="h-4 w-4" />
                                                </Link>
                                                <a
                                                    href={financeService.getPdfDownloadUrl(inv.id)}
                                                    className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                                    title="Download PDF"
                                                >
                                                    <HiOutlineDownload className="h-4 w-4" />
                                                </a>
                                                {can('finance.manage-budgets') && inv.status === 'draft' && (
                                                    <button
                                                        onClick={() => handleMarkSent(inv.id)}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600"
                                                        title="Mark as Sent"
                                                    >
                                                        <HiOutlinePaperAirplane className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {can('finance.manage-budgets') && (
                                                    <button
                                                        onClick={() => handleDelete(inv.id)}
                                                        className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                        title="Delete"
                                                    >
                                                        <HiOutlineTrash className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {pagination.last_page > 1 && (
                        <div className="flex items-center justify-between border-t px-4 py-3">
                            <p className="text-sm text-gray-500">
                                Showing {pagination.from}-{pagination.to} of {pagination.total}
                            </p>
                            <div className="flex gap-1">
                                {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                                    <button
                                        key={page}
                                        onClick={() => fetchInvoices(page)}
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
        </div>
    );
}
