import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import reportDataService from '@/services/reportDataService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useConfirm } from '@/context/ConfirmContext';
import { HiOutlinePencil, HiOutlineTrash, HiOutlineUpload, HiOutlinePlus, HiOutlineX } from 'react-icons/hi';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

const ROLES = [
    ['owner', 'Project Owner'], ['superintending_officer', 'Superintending Officer (SO)'], ['so_representative', "Representative of the SO"],
    ['district_engineer', 'District Engineer'], ['quantity_surveyor', 'Quantity Surveyor'], ['consultant', 'Consultant'], ['contractor', 'Contractor'], ['other', 'Other'],
];
const ROLE_LABELS = Object.fromEntries(ROLES);

const TYPE_FROM_ROLE = {
    owner: 'client', superintending_officer: 'client', so_representative: 'client',
    district_engineer: 'client', quantity_surveyor: 'client', consultant: 'consultant',
    contractor: 'main_contractor', other: 'other',
};

const emptyContact = () => ({ name: '', designation: '', phone: '', email: '' });
const emptyForm = () => ({ id: null, report_role: 'owner', role_label: '', name: '', address: '', sort_order: 0, contacts: [emptyContact()] });

export default function PartiesPanel({ project, canEdit }) {
    const confirm = useConfirm();
    const [loading, setLoading] = useState(true);
    const [parties, setParties] = useState([]);
    const [form, setForm] = useState(null);
    const [saving, setSaving] = useState(false);
    const [uploadingId, setUploadingId] = useState(null);

    const load = () => {
        setLoading(true);
        reportDataService.listParties(project.id)
            .then((res) => setParties(res.data || []))
            .catch(() => toast.error('Failed to load parties'))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id]);

    const openAdd = () => setForm(emptyForm());
    const openEdit = (party) => setForm({
        id: party.id,
        hadRole: !!party.report_role,
        report_role: party.report_role || 'other',
        role_label: party.role_label || '',
        name: party.name || '',
        address: party.address || '',
        sort_order: party.sort_order ?? 0,
        contacts: party.contacts?.length ? party.contacts.map((c) => ({ name: c.name || '', designation: c.designation || '', phone: c.phone || '', email: c.email || '' })) : [emptyContact()],
    });
    const closeForm = () => setForm(null);

    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));
    const setContact = (i, k) => (e) => setForm((f) => ({ ...f, contacts: f.contacts.map((c, idx) => (idx === i ? { ...c, [k]: e.target.value } : c)) }));
    const addContact = () => setForm((f) => ({ ...f, contacts: [...f.contacts, emptyContact()] }));
    const removeContact = (i) => setForm((f) => ({ ...f, contacts: f.contacts.filter((_, idx) => idx !== i) }));

    const save = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = {
                name: form.name,
                report_role: form.report_role,
                role_label: form.report_role === 'other' ? form.role_label : null,
                address: form.address,
                sort_order: Number(form.sort_order) || 0,
                contacts: form.contacts.filter((c) => c.name),
            };
            // Only send `type` when creating a party, or when editing one that had no
            // report_role yet (and the user is now picking one) — never overwrite the
            // Correspondence `type` of a party that already had a report_role set.
            if (!form.id || !form.hadRole) {
                payload.type = TYPE_FROM_ROLE[form.report_role] || 'other';
            }
            if (form.id) {
                await reportDataService.updateParty(project.id, form.id, payload);
                toast.success('Party updated');
            } else {
                await reportDataService.createParty(project.id, payload);
                toast.success('Party added');
            }
            closeForm();
            load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save party');
        } finally {
            setSaving(false);
        }
    };

    const onDelete = async (party) => {
        if (!(await confirm({ title: 'Delete party?', message: `Delete "${party.name}"?` }))) return;
        try {
            await reportDataService.deleteParty(project.id, party.id);
            toast.success('Party removed');
            setParties((p) => p.filter((x) => x.id !== party.id));
        } catch {
            toast.error('Failed to delete party');
        }
    };

    const onUploadLogo = async (party, e) => {
        const file = e.target.files[0];
        e.target.value = '';
        if (!file) return;
        setUploadingId(party.id);
        try {
            const formData = new FormData();
            formData.append('logo', file);
            await reportDataService.uploadPartyLogo(project.id, party.id, formData);
            toast.success('Logo uploaded');
            load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to upload logo');
        } finally {
            setUploadingId(null);
        }
    };

    if (loading) return <LoadingSpinner />;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900">Parties & Contacts</h2>
                {canEdit && !form && (
                    <button type="button" onClick={openAdd} className="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-700">
                        <HiOutlinePlus className="h-4 w-4" /> Add party
                    </button>
                )}
            </div>

            {form && (
                <form onSubmit={save} className="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-gray-900">{form.id ? 'Edit party' : 'New party'}</h3>
                        <button type="button" onClick={closeForm} className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <HiOutlineX className="h-4 w-4" />
                        </button>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Report role</label>
                            <select value={form.report_role} onChange={set('report_role')} className={input}>
                                {ROLES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </div>
                        {form.report_role === 'other' && (
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Role label</label>
                                <input value={form.role_label} onChange={set('role_label')} className={input} required />
                            </div>
                        )}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Company</label>
                            <input value={form.name} onChange={set('name')} className={input} required />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Order</label>
                            <input type="number" value={form.sort_order} onChange={set('sort_order')} className={input} />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Address</label>
                            <textarea value={form.address} onChange={set('address')} className={input} rows={2} />
                        </div>
                    </div>

                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm font-semibold text-gray-900">Contacts</p>
                            <button type="button" onClick={addContact} className="text-sm font-medium text-primary-600 hover:text-primary-700">+ Add contact</button>
                        </div>
                        <div className="space-y-3">
                            {form.contacts.map((c, i) => (
                                <div key={i} className="grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-4">
                                    <input placeholder="Name" value={c.name} onChange={setContact(i, 'name')} className={input} />
                                    <input placeholder="Designation" value={c.designation} onChange={setContact(i, 'designation')} className={input} />
                                    <input placeholder="Phone" value={c.phone} onChange={setContact(i, 'phone')} className={input} />
                                    <input placeholder="Email" value={c.email} onChange={setContact(i, 'email')} className={input} />
                                    <button type="button" onClick={() => removeContact(i)} className="text-left text-xs text-red-600 hover:underline sm:col-span-4">Remove contact</button>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={closeForm} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving…' : 'Save party'}</button>
                    </div>
                </form>
            )}

            {parties.length === 0 && !form ? (
                <div className="rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">No parties added yet.</div>
            ) : (
                <div className="space-y-3">
                    {parties.map((party) => (
                        <div key={party.id} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                            <div className="flex items-start gap-4">
                                <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                    {party.logo_path ? (
                                        <img src={`${reportDataService.getPartyLogoUrl(project.id, party.id)}?v=${encodeURIComponent(party.updated_at || '')}`} alt={`${party.name} logo`} className="h-full w-full object-contain" />
                                    ) : (
                                        <span className="text-xs text-gray-300">No logo</span>
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs font-semibold uppercase text-primary-600">
                                        {party.report_role === 'other' ? party.role_label : (ROLE_LABELS[party.report_role] || party.type)}
                                    </p>
                                    <p className="text-sm font-semibold text-gray-900">{party.name}</p>
                                    {party.address && <p className="mt-0.5 whitespace-pre-line text-xs text-gray-500">{party.address}</p>}
                                    {party.contacts?.length > 0 && (
                                        <ul className="mt-2 space-y-1">
                                            {party.contacts.map((c) => (
                                                <li key={c.id} className="text-xs text-gray-600">
                                                    <span className="font-medium">{c.name}</span>
                                                    {c.designation && ` · ${c.designation}`}
                                                    {c.phone && ` · ${c.phone}`}
                                                    {c.email && ` · ${c.email}`}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                                {canEdit && (
                                    <div className="flex shrink-0 items-center gap-1">
                                        <label className="cursor-pointer rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Upload logo" aria-label="Upload logo">
                                            <HiOutlineUpload className="h-4 w-4" />
                                            <input type="file" accept=".png,.jpg,.jpeg,.webp" className="hidden" disabled={uploadingId === party.id} onChange={(e) => onUploadLogo(party, e)} />
                                        </label>
                                        <button type="button" onClick={() => openEdit(party)} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Edit" aria-label="Edit">
                                            <HiOutlinePencil className="h-4 w-4" />
                                        </button>
                                        <button type="button" onClick={() => onDelete(party)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete" aria-label="Delete">
                                            <HiOutlineTrash className="h-4 w-4" />
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
