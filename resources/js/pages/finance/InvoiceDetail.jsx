import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import financeService from '@/services/financeService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePencil,
    HiOutlineDownload,
    HiOutlinePaperAirplane,
    HiOutlineCurrencyDollar,
    HiOutlineArrowLeft,
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

export default function InvoiceDetail() {
    const { id } = useParams();
    const { can } = useAuth();
    const navigate = useNavigate();
    const [invoice, setInvoice] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showPaymentForm, setShowPaymentForm] = useState(false);
    const [paymentSaving, setPaymentSaving] = useState(false);
    const [paymentForm, setPaymentForm] = useState({
        amount: '',
        payment_date: new Date().toISOString().split('T')[0],
        method: 'bank_transfer',
        reference: '',
        notes: '',
    });

    const fetchInvoice = async () => {
        try {
            const res = await financeService.getInvoice(id);
            setInvoice(res.data);
        } catch {
            toast.error('Failed to load invoice');
            navigate('/finance/invoices');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchInvoice(); }, [id]);

    const handleMarkSent = async () => {
        try {
            await financeService.markAsSent(id);
            toast.success('Invoice marked as sent');
            fetchInvoice();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed');
        }
    };

    const handleRecordPayment = async (e) => {
        e.preventDefault();
        setPaymentSaving(true);
        try {
            await financeService.recordPayment(id, {
                ...paymentForm,
                amount: Number(paymentForm.amount),
            });
            toast.success('Payment recorded');
            setShowPaymentForm(false);
            setPaymentForm({ amount: '', payment_date: new Date().toISOString().split('T')[0], method: 'bank_transfer', reference: '', notes: '' });
            fetchInvoice();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to record payment');
        } finally {
            setPaymentSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;
    if (!invoice) return null;

    return (
        <div className="mx-auto max-w-4xl">
            {/* Header */}
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <Link to="/finance/invoices" className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                        <HiOutlineArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-gray-900">{invoice.invoice_number}</h1>
                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${statusColors[invoice.status]}`}>
                                {invoice.status?.replace('_', ' ')}
                            </span>
                        </div>
                        <p className="text-sm text-gray-500">{invoice.client?.company_name}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <a
                        href={financeService.getPdfDownloadUrl(invoice.id)}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <HiOutlineDownload className="h-4 w-4" />
                        Download PDF
                    </a>
                    {can('finance.manage-budgets') && invoice.status === 'draft' && (
                        <>
                            <button
                                onClick={handleMarkSent}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-blue-300 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50"
                            >
                                <HiOutlinePaperAirplane className="h-4 w-4" />
                                Mark as Sent
                            </button>
                            <Link
                                to={`/finance/invoices/${invoice.id}/edit`}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                            >
                                <HiOutlinePencil className="h-4 w-4" />
                                Edit
                            </Link>
                        </>
                    )}
                    {can('finance.manage-budgets') && invoice.balance_due > 0 && invoice.status !== 'draft' && (
                        <button
                            onClick={() => setShowPaymentForm(true)}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700"
                        >
                            <HiOutlineCurrencyDollar className="h-4 w-4" />
                            Record Payment
                        </button>
                    )}
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Main Info */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Invoice Details */}
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-xs font-medium uppercase text-gray-400">Bill To</p>
                                <p className="mt-1 font-semibold text-gray-900">{invoice.client?.company_name}</p>
                                {invoice.client?.contact_person && <p className="text-sm text-gray-600">{invoice.client.contact_person}</p>}
                                {invoice.client?.email && <p className="text-sm text-gray-500">{invoice.client.email}</p>}
                            </div>
                            <div className="text-right">
                                <div className="text-sm text-gray-600">
                                    <p><span className="font-medium">Issue Date:</span> {invoice.issue_date}</p>
                                    <p><span className="font-medium">Due Date:</span> {invoice.due_date}</p>
                                    {invoice.project && <p><span className="font-medium">Project:</span> {invoice.project.name}</p>}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Items Table */}
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">#</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Description</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Qty</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Unit Price</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Amount</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {invoice.items?.map((item, i) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-3 text-sm text-gray-500">{i + 1}</td>
                                        <td className="px-4 py-3 text-sm text-gray-900">{item.description}</td>
                                        <td className="px-4 py-3 text-right text-sm text-gray-700">
                                            {item.quantity} {item.unit || ''}
                                        </td>
                                        <td className="px-4 py-3 text-right text-sm text-gray-700">
                                            {formatCurrency(item.unit_price, invoice.currency)}
                                        </td>
                                        <td className="px-4 py-3 text-right text-sm font-medium text-gray-900">
                                            {formatCurrency(item.amount, invoice.currency)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Payment History */}
                    {invoice.payments?.length > 0 && (
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                            <div className="border-b px-4 py-3">
                                <h3 className="text-sm font-semibold text-gray-700">Payment History</h3>
                            </div>
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Method</th>
                                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Reference</th>
                                        <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {invoice.payments.map((p) => (
                                        <tr key={p.id}>
                                            <td className="px-4 py-2.5 text-sm text-gray-700">{p.payment_date}</td>
                                            <td className="px-4 py-2.5 text-sm text-gray-600">{p.method?.replace('_', ' ')}</td>
                                            <td className="px-4 py-2.5 text-sm text-gray-500">{p.reference || '-'}</td>
                                            <td className="px-4 py-2.5 text-right text-sm font-medium text-green-600">
                                                {formatCurrency(p.amount, invoice.currency)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Notes */}
                    {(invoice.notes || invoice.terms) && (
                        <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                            {invoice.notes && (
                                <div className="mb-4">
                                    <p className="text-xs font-medium uppercase text-gray-400">Notes</p>
                                    <p className="mt-1 text-sm text-gray-600">{invoice.notes}</p>
                                </div>
                            )}
                            {invoice.terms && (
                                <div>
                                    <p className="text-xs font-medium uppercase text-gray-400">Terms & Conditions</p>
                                    <p className="mt-1 text-sm text-gray-600">{invoice.terms}</p>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Sidebar - Totals */}
                <div className="space-y-6">
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h3 className="mb-4 text-sm font-semibold uppercase text-gray-500">Summary</h3>
                        <div className="space-y-2">
                            <div className="flex justify-between text-sm text-gray-600">
                                <span>Subtotal</span>
                                <span>{formatCurrency(invoice.subtotal, invoice.currency)}</span>
                            </div>
                            {invoice.tax_rate > 0 && (
                                <div className="flex justify-between text-sm text-gray-600">
                                    <span>Tax ({invoice.tax_rate}%)</span>
                                    <span>{formatCurrency(invoice.tax_amount, invoice.currency)}</span>
                                </div>
                            )}
                            {invoice.discount > 0 && (
                                <div className="flex justify-between text-sm text-gray-600">
                                    <span>Discount</span>
                                    <span>- {formatCurrency(invoice.discount, invoice.currency)}</span>
                                </div>
                            )}
                            <div className="flex justify-between border-t pt-2 text-base font-bold text-gray-900">
                                <span>Total</span>
                                <span>{formatCurrency(invoice.total, invoice.currency)}</span>
                            </div>
                            {invoice.amount_paid > 0 && (
                                <div className="flex justify-between text-sm text-green-600">
                                    <span>Paid</span>
                                    <span>{formatCurrency(invoice.amount_paid, invoice.currency)}</span>
                                </div>
                            )}
                            {invoice.balance_due > 0 && (
                                <div className="flex justify-between border-t pt-2 text-base font-bold text-red-600">
                                    <span>Balance Due</span>
                                    <span>{formatCurrency(invoice.balance_due, invoice.currency)}</span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* PDF Preview */}
                    <div className="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                        <div className="border-b px-4 py-3">
                            <h3 className="text-sm font-semibold text-gray-700">PDF Preview</h3>
                        </div>
                        <div className="p-2">
                            <iframe
                                src={financeService.getPdfPreviewUrl(invoice.id)}
                                className="h-96 w-full rounded border"
                                title="Invoice PDF Preview"
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* Record Payment Modal */}
            {showPaymentForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowPaymentForm(false)}>
                    <div className="mx-4 w-full max-w-md rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Record Payment</h3>
                        <p className="mb-4 text-sm text-gray-500">
                            Balance due: <span className="font-medium text-red-600">{formatCurrency(invoice.balance_due, invoice.currency)}</span>
                        </p>
                        <form onSubmit={handleRecordPayment} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Amount *</label>
                                <input
                                    type="number"
                                    value={paymentForm.amount}
                                    onChange={(e) => setPaymentForm((p) => ({ ...p, amount: e.target.value }))}
                                    min="0.01"
                                    step="0.01"
                                    max={invoice.balance_due}
                                    required
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Payment Date *</label>
                                <input
                                    type="date"
                                    value={paymentForm.payment_date}
                                    onChange={(e) => setPaymentForm((p) => ({ ...p, payment_date: e.target.value }))}
                                    required
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Method</label>
                                <select
                                    value={paymentForm.method}
                                    onChange={(e) => setPaymentForm((p) => ({ ...p, method: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                >
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="cash">Cash</option>
                                    <option value="online">Online</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Reference</label>
                                <input
                                    type="text"
                                    value={paymentForm.reference}
                                    onChange={(e) => setPaymentForm((p) => ({ ...p, reference: e.target.value }))}
                                    placeholder="Transaction ID, cheque no..."
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <textarea
                                    rows={2}
                                    value={paymentForm.notes}
                                    onChange={(e) => setPaymentForm((p) => ({ ...p, notes: e.target.value }))}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setShowPaymentForm(false)}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={paymentSaving}
                                    className="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                                >
                                    {paymentSaving ? 'Recording...' : 'Record Payment'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
