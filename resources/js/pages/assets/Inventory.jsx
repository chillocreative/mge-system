import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import inventoryService from '@/services/inventoryService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus,
    HiOutlineSearch,
    HiOutlineCube,
    HiOutlineTag,
    HiOutlineChevronRight,
    HiOutlineTrash,
} from 'react-icons/hi';

function formatQty(val) {
    return Number(val || 0).toLocaleString('en-MY', { maximumFractionDigits: 2 });
}

export default function Inventory() {
    const { can } = useAuth();
    const [items, setItems] = useState([]);
    const [categories, setCategories] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [lowStockOnly, setLowStockOnly] = useState(false);
    const [pagination, setPagination] = useState({});
    const [showForm, setShowForm] = useState(false);
    const [showCatModal, setShowCatModal] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({
        name: '', sku: '', category_id: '', unit: '', quantity_on_hand: '',
        reorder_level: '', unit_cost: '', location: '', status: 'active',
    });
    const [catForm, setCatForm] = useState({ name: '', code: '', description: '' });

    const fetchItems = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (search) params.search = search;
            if (categoryFilter) params.category_id = categoryFilter;
            if (lowStockOnly) params.low_stock = 1;
            const res = await inventoryService.listItems(params);
            setItems(res.data?.data || []);
            setPagination(res.data?.meta || res.data || {});
        } catch {
            setItems([]);
        } finally {
            setLoading(false);
        }
    };

    const fetchCategories = async () => {
        try {
            const res = await inventoryService.listCategories();
            setCategories(res.data || []);
        } catch { /* ignore */ }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchItems(), 400);
        return () => clearTimeout(timer);
    }, [search, categoryFilter, lowStockOnly]);

    useEffect(() => { fetchItems(); fetchCategories(); }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = { ...form };
            Object.keys(payload).forEach((k) => { if (payload[k] === '') delete payload[k]; });
            await inventoryService.createItem(payload);
            toast.success('Item created');
            setShowForm(false);
            setForm({ name: '', sku: '', category_id: '', unit: '', quantity_on_hand: '', reorder_level: '', unit_cost: '', location: '', status: 'active' });
            fetchItems();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to create item');
        } finally {
            setSaving(false);
        }
    };

    const handleAddCategory = async (e) => {
        e.preventDefault();
        try {
            await inventoryService.createCategory(catForm);
            toast.success('Category added');
            setCatForm({ name: '', code: '', description: '' });
            fetchCategories();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed');
        }
    };

    const handleDeleteCategory = async (catId) => {
        if (!confirm('Delete this category?')) return;
        try {
            await inventoryService.deleteCategory(catId);
            toast.success('Category deleted');
            fetchCategories();
        } catch {
            toast.error('Failed to delete');
        }
    };

    const isLow = (item) => Number(item.quantity_on_hand) <= Number(item.reorder_level);

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Inventory</h1>
                    <p className="text-sm text-gray-500">Track stock items, levels and locations</p>
                </div>
                <div className="flex gap-2">
                    {can('inventory.manage') && (
                        <button onClick={() => setShowCatModal(true)} className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            <HiOutlineTag className="h-5 w-5" /> Categories
                        </button>
                    )}
                    {can('inventory.manage') && (
                        <button onClick={() => setShowForm(true)} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                            <HiOutlinePlus className="h-5 w-5" /> New Item
                        </button>
                    )}
                </div>
            </div>

            {/* Filters */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="relative max-w-md flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input type="text" placeholder="Search items..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                </div>
                <select value={categoryFilter} onChange={(e) => setCategoryFilter(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">All Categories</option>
                    {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={lowStockOnly} onChange={(e) => setLowStockOnly(e.target.checked)} className="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                    Low stock only
                </label>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : items.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineCube className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No items found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Item</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Category</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">On Hand</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Reorder</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Location</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {items.map((item) => (
                                    <tr key={item.id} className={`hover:bg-gray-50 ${isLow(item) ? 'bg-red-50/40' : ''}`}>
                                        <td className="px-4 py-3">
                                            <Link to={`/assets/inventory/${item.id}`} className="text-sm font-medium text-primary-700 hover:underline">{item.name}</Link>
                                            <p className="text-xs text-gray-500">{item.sku}</p>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{item.category?.name || '-'}</td>
                                        <td className="px-4 py-3 text-right text-sm font-medium text-gray-900">
                                            {formatQty(item.quantity_on_hand)} {item.unit || ''}
                                            {isLow(item) && <span className="ml-2 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Low</span>}
                                        </td>
                                        <td className="px-4 py-3 text-right text-sm text-gray-500">{formatQty(item.reorder_level)}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{item.location || '-'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Link to={`/assets/inventory/${item.id}`} className="inline-flex items-center text-gray-400 hover:text-primary-600"><HiOutlineChevronRight className="h-5 w-5" /></Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {pagination.last_page > 1 && (
                        <div className="flex items-center justify-between border-t px-4 py-3">
                            <p className="text-sm text-gray-500">Showing {pagination.from}-{pagination.to} of {pagination.total}</p>
                            <div className="flex gap-1">
                                {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                                    <button key={page} onClick={() => fetchItems(page)} className={`rounded px-3 py-1 text-sm ${page === pagination.current_page ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}>{page}</button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* New Item Modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowForm(false)}>
                    <div className="mx-4 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">New Inventory Item</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Name *</label>
                                    <input type="text" value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">SKU *</label>
                                    <input type="text" value={form.sku} onChange={(e) => setForm((p) => ({ ...p, sku: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Category</label>
                                    <select value={form.category_id} onChange={(e) => setForm((p) => ({ ...p, category_id: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                        <option value="">No Category</option>
                                        {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Unit</label>
                                    <input type="text" placeholder="pcs, kg, box..." value={form.unit} onChange={(e) => setForm((p) => ({ ...p, unit: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Opening Qty</label>
                                    <input type="number" step="0.01" value={form.quantity_on_hand} onChange={(e) => setForm((p) => ({ ...p, quantity_on_hand: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Reorder Level</label>
                                    <input type="number" step="0.01" value={form.reorder_level} onChange={(e) => setForm((p) => ({ ...p, reorder_level: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Unit Cost (RM)</label>
                                    <input type="number" step="0.01" value={form.unit_cost} onChange={(e) => setForm((p) => ({ ...p, unit_cost: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Location</label>
                                <input type="text" value={form.location} onChange={(e) => setForm((p) => ({ ...p, location: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : 'Create Item'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Categories Modal */}
            {showCatModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowCatModal(false)}>
                    <div className="mx-4 max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Manage Categories</h3>
                        <form onSubmit={handleAddCategory} className="mb-4 space-y-3 rounded-lg bg-gray-50 p-3">
                            <div className="grid grid-cols-2 gap-3">
                                <input type="text" placeholder="Name *" value={catForm.name} onChange={(e) => setCatForm((p) => ({ ...p, name: e.target.value }))} required className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                                <input type="text" placeholder="Code" value={catForm.code} onChange={(e) => setCatForm((p) => ({ ...p, code: e.target.value }))} className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            </div>
                            <input type="text" placeholder="Description" value={catForm.description} onChange={(e) => setCatForm((p) => ({ ...p, description: e.target.value }))} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                            <button type="submit" className="w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">Add Category</button>
                        </form>
                        <div className="space-y-2">
                            {categories.length === 0 ? (
                                <p className="py-4 text-center text-sm text-gray-500">No categories yet</p>
                            ) : categories.map((c) => (
                                <div key={c.id} className="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{c.name}</p>
                                        <p className="text-xs text-gray-500">{c.items_count ?? 0} items{c.code ? ` · ${c.code}` : ''}</p>
                                    </div>
                                    <button onClick={() => handleDeleteCategory(c.id)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineTrash className="h-4 w-4" /></button>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 flex justify-end">
                            <button onClick={() => setShowCatModal(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
