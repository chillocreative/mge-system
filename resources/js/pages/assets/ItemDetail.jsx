import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import inventoryService from '@/services/inventoryService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlineArrowLeft,
    HiOutlinePlus,
    HiOutlineSwitchHorizontal,
} from 'react-icons/hi';

const txnColors = {
    in: 'bg-green-100 text-green-700',
    out: 'bg-amber-100 text-amber-700',
    adjustment: 'bg-blue-100 text-blue-700',
    write_off: 'bg-red-100 text-red-700',
};

function formatQty(val) {
    return Number(val || 0).toLocaleString('en-MY', { maximumFractionDigits: 2 });
}

function cap(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : '';
}

export default function ItemDetail() {
    const { id } = useParams();
    const { can } = useAuth();
    const [item, setItem] = useState(null);
    const [transactions, setTransactions] = useState([]);
    const [pagination, setPagination] = useState({});
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({
        type: 'in', quantity: '', reference: '',
        transaction_date: new Date().toISOString().split('T')[0], notes: '',
    });

    const fetchItem = async () => {
        try {
            const res = await inventoryService.getItem(id);
            setItem(res.data);
        } catch {
            toast.error('Failed to load item');
        }
    };

    const fetchTransactions = async (page = 1) => {
        try {
            const res = await inventoryService.listTransactions(id, { page });
            setTransactions(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setTransactions([]);
        }
    };

    useEffect(() => {
        (async () => {
            setLoading(true);
            await Promise.all([fetchItem(), fetchTransactions()]);
            setLoading(false);
        })();
    }, [id]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = { ...form };
            Object.keys(payload).forEach((k) => { if (payload[k] === '') delete payload[k]; });
            await inventoryService.recordTransaction(id, payload);
            toast.success('Transaction recorded');
            setShowForm(false);
            setForm({ type: 'in', quantity: '', reference: '', transaction_date: new Date().toISOString().split('T')[0], notes: '' });
            fetchItem();
            fetchTransactions();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to record');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;
    if (!item) return null;

    const isLow = Number(item.quantity_on_hand) <= Number(item.reorder_level);

    return (
        <div>
            <Link to="/assets/inventory" className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary-600">
                <HiOutlineArrowLeft className="h-4 w-4" /> Back to Inventory
            </Link>

            <div className="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">{item.name}</h1>
                        <p className="text-sm text-gray-500">{item.sku}{item.category ? ` · ${item.category.name}` : ''}</p>
                    </div>
                    {can('inventory.manage') && (
                        <button onClick={() => setShowForm(true)} className="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            <HiOutlineSwitchHorizontal className="h-4 w-4" /> Record Transaction
                        </button>
                    )}
                </div>
                <div className="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <p className="text-gray-500">On Hand</p>
                        <p className="text-lg font-bold text-gray-900">
                            {formatQty(item.quantity_on_hand)} {item.unit || ''}
                            {isLow && <span className="ml-2 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Low</span>}
                        </p>
                    </div>
                    <div><p className="text-gray-500">Reorder Level</p><p className="font-medium text-gray-900">{formatQty(item.reorder_level)}</p></div>
                    <div><p className="text-gray-500">Unit Cost</p><p className="font-medium text-gray-900">RM {formatQty(item.unit_cost)}</p></div>
                    <div><p className="text-gray-500">Location</p><p className="font-medium text-gray-900">{item.location || '-'}</p></div>
                </div>
            </div>

            {/* Transactions */}
            <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h2 className="mb-4 text-lg font-semibold text-gray-900">Stock Transactions</h2>
                {transactions.length === 0 ? (
                    <p className="py-6 text-center text-sm text-gray-500">No transactions yet</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Quantity</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Reference</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Notes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {transactions.map((t) => (
                                    <tr key={t.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm text-gray-500">{t.transaction_date}</td>
                                        <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs font-medium ${txnColors[t.type]}`}>{cap(t.type)}</span></td>
                                        <td className="px-4 py-3 text-right text-sm font-medium text-gray-900">{formatQty(t.quantity)}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{t.reference || '-'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{t.notes || '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {pagination.last_page > 1 && (
                    <div className="flex items-center justify-between border-t px-4 py-3">
                        <p className="text-sm text-gray-500">Showing {pagination.from}-{pagination.to} of {pagination.total}</p>
                        <div className="flex gap-1">
                            {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                                <button key={page} onClick={() => fetchTransactions(page)} className={`rounded px-3 py-1 text-sm ${page === pagination.current_page ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}>{page}</button>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Record Transaction Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowForm(false)}>
                    <div className="mx-4 w-full max-w-md rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Record Transaction</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Type *</label>
                                    <select value={form.type} onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="in">Stock In (+)</option>
                                        <option value="out">Stock Out (-)</option>
                                        <option value="adjustment">Adjustment (set)</option>
                                        <option value="write_off">Write Off (-)</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Quantity *</label>
                                    <input type="number" step="0.01" min="0" value={form.quantity} onChange={(e) => setForm((p) => ({ ...p, quantity: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            {form.type === 'adjustment' && (
                                <p className="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-700">Adjustment sets the on-hand quantity to the value entered above.</p>
                            )}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Date *</label>
                                    <input type="date" value={form.transaction_date} onChange={(e) => setForm((p) => ({ ...p, transaction_date: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Reference</label>
                                    <input type="text" placeholder="PO/DO no." value={form.reference} onChange={(e) => setForm((p) => ({ ...p, reference: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <textarea rows={2} value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : 'Record'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
