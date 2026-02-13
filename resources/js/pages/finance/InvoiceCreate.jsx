import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import financeService from '@/services/financeService';
import clientService from '@/services/clientService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlinePlus, HiOutlineTrash } from 'react-icons/hi';

const emptyItem = { description: '', quantity: 1, unit: '', unit_price: 0 };

export default function InvoiceCreate() {
    const { id } = useParams();
    const navigate = useNavigate();
    const isEdit = !!id;

    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [clients, setClients] = useState([]);
    const [projects, setProjects] = useState([]);

    const [form, setForm] = useState({
        client_id: '',
        project_id: '',
        issue_date: new Date().toISOString().split('T')[0],
        due_date: '',
        tax_rate: 0,
        discount: 0,
        currency: 'PKR',
        notes: '',
        terms: '',
        items: [{ ...emptyItem }],
    });

    const [errors, setErrors] = useState({});

    // Load clients & projects for dropdowns
    useEffect(() => {
        (async () => {
            try {
                const [cRes, pRes] = await Promise.all([
                    clientService.list({ per_page: 100 }),
                    projectService.list({ per_page: 100 }),
                ]);
                setClients(cRes.data?.data || []);
                setProjects(pRes.data?.data || []);
            } catch { /* ignore */ }
        })();
    }, []);

    // Load existing invoice if editing
    useEffect(() => {
        if (!isEdit) return;
        (async () => {
            setLoading(true);
            try {
                const res = await financeService.getInvoice(id);
                const inv = res.data;
                setForm({
                    client_id: inv.client_id || '',
                    project_id: inv.project_id || '',
                    issue_date: inv.issue_date || '',
                    due_date: inv.due_date || '',
                    tax_rate: inv.tax_rate || 0,
                    discount: inv.discount || 0,
                    currency: inv.currency || 'PKR',
                    notes: inv.notes || '',
                    terms: inv.terms || '',
                    items: inv.items?.length
                        ? inv.items.map((i) => ({
                              description: i.description,
                              quantity: i.quantity,
                              unit: i.unit || '',
                              unit_price: i.unit_price,
                          }))
                        : [{ ...emptyItem }],
                });
            } catch {
                toast.error('Failed to load invoice');
                navigate('/finance/invoices');
            } finally {
                setLoading(false);
            }
        })();
    }, [id]);

    const updateField = (field, value) => {
        setForm((prev) => ({ ...prev, [field]: value }));
        setErrors((prev) => ({ ...prev, [field]: undefined }));
    };

    const updateItem = (index, field, value) => {
        setForm((prev) => {
            const items = [...prev.items];
            items[index] = { ...items[index], [field]: value };
            return { ...prev, items };
        });
    };

    const addItem = () => setForm((prev) => ({ ...prev, items: [...prev.items, { ...emptyItem }] }));

    const removeItem = (index) => {
        if (form.items.length <= 1) return;
        setForm((prev) => ({ ...prev, items: prev.items.filter((_, i) => i !== index) }));
    };

    // Calculated totals
    const subtotal = form.items.reduce((sum, i) => sum + (Number(i.quantity) || 0) * (Number(i.unit_price) || 0), 0);
    const taxAmount = subtotal * (Number(form.tax_rate) || 0) / 100;
    const total = subtotal + taxAmount - (Number(form.discount) || 0);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const payload = {
                ...form,
                client_id: Number(form.client_id),
                project_id: form.project_id ? Number(form.project_id) : null,
                tax_rate: Number(form.tax_rate) || 0,
                discount: Number(form.discount) || 0,
                items: form.items.map((i) => ({
                    description: i.description,
                    quantity: Number(i.quantity) || 1,
                    unit: i.unit || null,
                    unit_price: Number(i.unit_price) || 0,
                })),
            };

            if (isEdit) {
                await financeService.updateInvoice(id, payload);
                toast.success('Invoice updated');
            } else {
                await financeService.createInvoice(payload);
                toast.success('Invoice created');
            }
            navigate('/finance/invoices');
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors || {});
                toast.error('Please fix the validation errors');
            } else {
                toast.error(err.response?.data?.message || 'Failed to save invoice');
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;

    return (
        <div className="mx-auto max-w-4xl">
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">{isEdit ? 'Edit Invoice' : 'Create Invoice'}</h1>
                <p className="text-sm text-gray-500">{isEdit ? 'Update invoice details' : 'Generate a new client invoice'}</p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Client & Project */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Invoice Details</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Client *</label>
                            <select
                                value={form.client_id}
                                onChange={(e) => updateField('client_id', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="">Select Client</option>
                                {clients.map((c) => (
                                    <option key={c.id} value={c.id}>{c.company_name}</option>
                                ))}
                            </select>
                            {errors.client_id && <p className="mt-1 text-xs text-red-500">{errors.client_id[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Project</label>
                            <select
                                value={form.project_id}
                                onChange={(e) => updateField('project_id', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="">None</option>
                                {projects.map((p) => (
                                    <option key={p.id} value={p.id}>{p.name} ({p.code})</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Issue Date *</label>
                            <input
                                type="date"
                                value={form.issue_date}
                                onChange={(e) => updateField('issue_date', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {errors.issue_date && <p className="mt-1 text-xs text-red-500">{errors.issue_date[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Due Date *</label>
                            <input
                                type="date"
                                value={form.due_date}
                                onChange={(e) => updateField('due_date', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                            {errors.due_date && <p className="mt-1 text-xs text-red-500">{errors.due_date[0]}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Currency</label>
                            <input
                                type="text"
                                value={form.currency}
                                onChange={(e) => updateField('currency', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                    </div>
                </div>

                {/* Line Items */}
                <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase text-gray-500">Line Items</h2>
                        <button
                            type="button"
                            onClick={addItem}
                            className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
                        >
                            <HiOutlinePlus className="h-4 w-4" />
                            Add Item
                        </button>
                    </div>

                    <div className="space-y-3">
                        {/* Header */}
                        <div className="hidden sm:grid sm:grid-cols-12 sm:gap-3 sm:px-1">
                            <span className="col-span-5 text-xs font-medium text-gray-500">Description</span>
                            <span className="col-span-2 text-xs font-medium text-gray-500">Qty</span>
                            <span className="col-span-1 text-xs font-medium text-gray-500">Unit</span>
                            <span className="col-span-2 text-xs font-medium text-gray-500">Unit Price</span>
                            <span className="col-span-1 text-right text-xs font-medium text-gray-500">Amount</span>
                            <span className="col-span-1" />
                        </div>

                        {form.items.map((item, index) => (
                            <div key={index} className="grid gap-2 rounded-lg border border-gray-100 p-3 sm:grid-cols-12 sm:gap-3 sm:border-0 sm:p-0">
                                <input
                                    placeholder="Description"
                                    value={item.description}
                                    onChange={(e) => updateItem(index, 'description', e.target.value)}
                                    className="col-span-5 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                                <input
                                    type="number"
                                    placeholder="Qty"
                                    value={item.quantity}
                                    onChange={(e) => updateItem(index, 'quantity', e.target.value)}
                                    min="0.01"
                                    step="0.01"
                                    className="col-span-2 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                                <input
                                    placeholder="Unit"
                                    value={item.unit}
                                    onChange={(e) => updateItem(index, 'unit', e.target.value)}
                                    className="col-span-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                                <input
                                    type="number"
                                    placeholder="Price"
                                    value={item.unit_price}
                                    onChange={(e) => updateItem(index, 'unit_price', e.target.value)}
                                    min="0"
                                    step="0.01"
                                    className="col-span-2 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                                <div className="col-span-1 flex items-center justify-end text-sm font-medium text-gray-700">
                                    {((Number(item.quantity) || 0) * (Number(item.unit_price) || 0)).toLocaleString('en-PK', { minimumFractionDigits: 2 })}
                                </div>
                                <div className="col-span-1 flex items-center justify-center">
                                    <button
                                        type="button"
                                        onClick={() => removeItem(index)}
                                        disabled={form.items.length <= 1}
                                        className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-500 disabled:opacity-30"
                                    >
                                        <HiOutlineTrash className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    {errors.items && <p className="mt-2 text-xs text-red-500">{errors.items[0]}</p>}
                </div>

                {/* Totals & Notes */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Notes & Terms</h2>
                        <div className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <textarea
                                    rows={3}
                                    value={form.notes}
                                    onChange={(e) => updateField('notes', e.target.value)}
                                    placeholder="Additional notes for the client..."
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Terms & Conditions</label>
                                <textarea
                                    rows={3}
                                    value={form.terms}
                                    onChange={(e) => updateField('terms', e.target.value)}
                                    placeholder="Payment terms..."
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Summary</h2>
                        <div className="space-y-3">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Tax Rate (%)</label>
                                    <input
                                        type="number"
                                        value={form.tax_rate}
                                        onChange={(e) => updateField('tax_rate', e.target.value)}
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Discount ({form.currency})</label>
                                    <input
                                        type="number"
                                        value={form.discount}
                                        onChange={(e) => updateField('discount', e.target.value)}
                                        min="0"
                                        step="0.01"
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div className="space-y-2 border-t pt-3">
                                <div className="flex justify-between text-sm text-gray-600">
                                    <span>Subtotal</span>
                                    <span>{form.currency} {subtotal.toLocaleString('en-PK', { minimumFractionDigits: 2 })}</span>
                                </div>
                                {Number(form.tax_rate) > 0 && (
                                    <div className="flex justify-between text-sm text-gray-600">
                                        <span>Tax ({form.tax_rate}%)</span>
                                        <span>{form.currency} {taxAmount.toLocaleString('en-PK', { minimumFractionDigits: 2 })}</span>
                                    </div>
                                )}
                                {Number(form.discount) > 0 && (
                                    <div className="flex justify-between text-sm text-gray-600">
                                        <span>Discount</span>
                                        <span>- {form.currency} {Number(form.discount).toLocaleString('en-PK', { minimumFractionDigits: 2 })}</span>
                                    </div>
                                )}
                                <div className="flex justify-between border-t pt-2 text-lg font-bold text-primary-700">
                                    <span>Total</span>
                                    <span>{form.currency} {total.toLocaleString('en-PK', { minimumFractionDigits: 2 })}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={() => navigate('/finance/invoices')}
                        className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={saving}
                        className="rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50"
                    >
                        {saving ? 'Saving...' : isEdit ? 'Update Invoice' : 'Create Invoice'}
                    </button>
                </div>
            </form>
        </div>
    );
}
