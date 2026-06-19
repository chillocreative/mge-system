import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import clientService from '@/services/clientService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlinePlus, HiOutlineSearch, HiOutlineOfficeBuilding, HiOutlinePencil, HiOutlineTrash } from 'react-icons/hi';

const emptyForm = {
    company_name: '', contact_person: '', email: '', phone: '', website: '',
    address: '', city: '', state: '', country: '', zip_code: '', status: 'active',
};

export default function Clients() {
    const { can } = useAuth();
    const [clients, setClients] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [showForm, setShowForm] = useState(false);
    const [editId, setEditId] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    const fetchClients = async () => {
        setLoading(true);
        try {
            const params = {};
            if (search) params.search = search;
            const response = await clientService.list(params);
            setClients(response.data?.data || []);
        } catch {
            setClients([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => fetchClients(), 400);
        return () => clearTimeout(timer);
    }, [search]);

    const openCreate = () => { setEditId(null); setForm(emptyForm); setErrors({}); setShowForm(true); };
    const openEdit = (c) => {
        setEditId(c.id);
        setForm({
            company_name: c.company_name || '', contact_person: c.contact_person || '', email: c.email || '',
            phone: c.phone || '', website: c.website || '', address: c.address || '', city: c.city || '',
            state: c.state || '', country: c.country || '', zip_code: c.zip_code || '', status: c.status || 'active',
        });
        setErrors({});
        setShowForm(true);
    };

    const submit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        const payload = { ...form };
        Object.keys(payload).forEach((k) => { if (payload[k] === '') payload[k] = null; });
        if (!editId) delete payload.status; // status defaults on create
        try {
            if (editId) {
                await clientService.update(editId, payload);
                toast.success('Client updated');
            } else {
                await clientService.create(payload);
                toast.success('Client created');
            }
            setShowForm(false);
            fetchClients();
        } catch (err) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors || {});
                toast.error('Please fix the highlighted fields');
            } else {
                toast.error(err.response?.data?.message || 'Failed to save client');
            }
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (c) => {
        if (!window.confirm(`Delete ${c.company_name}?`)) return;
        try {
            await clientService.delete(c.id);
            toast.success('Client deleted');
            fetchClients();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete client');
        }
    };

    const fieldClass = (name) =>
        `w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1 ${errors[name] ? 'border-red-300 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500'}`;

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Clients</h1>
                    <p className="text-sm text-gray-500">Manage construction clients</p>
                </div>
                {can('clients.create') && (
                    <button onClick={openCreate} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                        <HiOutlinePlus className="h-5 w-5" />
                        New Client
                    </button>
                )}
            </div>

            <div className="mb-6">
                <div className="relative max-w-md">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input type="text" placeholder="Search clients..." value={search} onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                </div>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : clients.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineOfficeBuilding className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No clients found</p>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {clients.map((client) => (
                        <div key={client.id} className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                            <div className="mb-3 flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-sm font-bold text-primary-700">
                                    {client.company_name?.[0]}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <h3 className="truncate text-sm font-semibold text-gray-900">{client.company_name}</h3>
                                    <p className="truncate text-xs text-gray-500">{client.contact_person}</p>
                                </div>
                            </div>
                            <div className="space-y-1.5 text-xs text-gray-500">
                                <p className="truncate">{client.email}</p>
                                {client.phone && <p>{client.phone}</p>}
                                {client.city && <p>{client.city}{client.country ? `, ${client.country}` : ''}</p>}
                            </div>
                            <div className="mt-3 flex items-center justify-between border-t pt-3">
                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${client.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                    {client.status}
                                </span>
                                <div className="flex items-center gap-1">
                                    {can('clients.edit') && (
                                        <button onClick={() => openEdit(client)} title="Edit" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><HiOutlinePencil className="h-4 w-4" /></button>
                                    )}
                                    {can('clients.delete') && (
                                        <button onClick={() => handleDelete(client)} title="Delete" className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineTrash className="h-4 w-4" /></button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Create / Edit modal */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowForm(false)}>
                    <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">{editId ? 'Edit Client' : 'New Client'}</h3>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Company Name *</label>
                                <input type="text" value={form.company_name} onChange={(e) => setForm((p) => ({ ...p, company_name: e.target.value }))} required className={fieldClass('company_name')} />
                                {errors.company_name && <p className="mt-1 text-xs text-red-500">{errors.company_name[0]}</p>}
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Contact Person *</label>
                                    <input type="text" value={form.contact_person} onChange={(e) => setForm((p) => ({ ...p, contact_person: e.target.value }))} required className={fieldClass('contact_person')} />
                                    {errors.contact_person && <p className="mt-1 text-xs text-red-500">{errors.contact_person[0]}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Email *</label>
                                    <input type="email" value={form.email} onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))} required className={fieldClass('email')} placeholder="name@company.com" />
                                    {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email[0]}</p>}
                                </div>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Phone</label>
                                    <input type="tel" value={form.phone} onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))} className={fieldClass('phone')} placeholder="+60 ..." />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Website</label>
                                    <input type="url" value={form.website} onChange={(e) => setForm((p) => ({ ...p, website: e.target.value }))} className={fieldClass('website')} placeholder="https://example.com" />
                                    {errors.website && <p className="mt-1 text-xs text-red-500">{errors.website[0]}</p>}
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Address</label>
                                <textarea rows={2} value={form.address} onChange={(e) => setForm((p) => ({ ...p, address: e.target.value }))} className={fieldClass('address')} />
                            </div>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">City</label>
                                    <input type="text" value={form.city} onChange={(e) => setForm((p) => ({ ...p, city: e.target.value }))} className={fieldClass('city')} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">State</label>
                                    <input type="text" value={form.state} onChange={(e) => setForm((p) => ({ ...p, state: e.target.value }))} className={fieldClass('state')} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Country</label>
                                    <input type="text" value={form.country} onChange={(e) => setForm((p) => ({ ...p, country: e.target.value }))} className={fieldClass('country')} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Zip</label>
                                    <input type="text" value={form.zip_code} onChange={(e) => setForm((p) => ({ ...p, zip_code: e.target.value }))} className={fieldClass('zip_code')} />
                                </div>
                            </div>
                            {editId && (
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                    <select value={form.status} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))} className={fieldClass('status')}>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            )}
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                    {saving ? 'Saving...' : editId ? 'Update Client' : 'Create Client'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
