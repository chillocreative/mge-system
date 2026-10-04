import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import { HiOutlineCog, HiOutlineOfficeBuilding, HiOutlinePencil, HiOutlinePlus, HiOutlineSearch, HiOutlineTag, HiOutlineTrash } from 'react-icons/hi';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useConfirm } from '@/context/ConfirmContext';
import masterDataService from '@/services/masterDataService';
import projectService from '@/services/projectService';
import { compactPartyPayload, emptyPartyForm, partyToForm } from './masterDataForm';

const tabs = [
    { key: 'parties', label: 'Companies', icon: HiOutlineOfficeBuilding },
    { key: 'categories', label: 'Categories', icon: HiOutlineTag },
    { key: 'references', label: 'Project References', icon: HiOutlineCog },
];

const fieldClass = (error) => `w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1 ${error ? 'border-red-300 focus:ring-red-400' : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500'}`;

function ContactFields({ title, value, errors, prefix, onChange }) {
    return (
        <fieldset className="rounded-lg border border-gray-200 p-4">
            <legend className="px-1 text-sm font-semibold text-gray-800">{title}</legend>
            <div className="grid gap-3 sm:grid-cols-2">
                {['name', 'position', 'phone', 'email'].map((field) => (
                    <div key={field}>
                        <label className="mb-1 block text-xs font-medium capitalize text-gray-600">{field} *</label>
                        <input type={field === 'email' ? 'email' : 'text'} required value={value[field] || ''}
                            onChange={(event) => onChange(field, event.target.value)} className={fieldClass(errors[`${prefix}.${field}`])} />
                        {errors[`${prefix}.${field}`] && <p className="mt-1 text-xs text-red-500">{errors[`${prefix}.${field}`][0]}</p>}
                    </div>
                ))}
            </div>
        </fieldset>
    );
}

export default function MasterData() {
    const confirm = useConfirm();
    const [tab, setTab] = useState('parties');
    const [parties, setParties] = useState([]);
    const [categories, setCategories] = useState([]);
    const [projects, setProjects] = useState([]);
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [loading, setLoading] = useState(true);
    const [partyModal, setPartyModal] = useState(false);
    const [partyId, setPartyId] = useState(null);
    const [partyForm, setPartyForm] = useState(emptyPartyForm());
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const [categoryForm, setCategoryForm] = useState({ id: null, name: '', slug: '', is_active: true, sort_order: 0 });
    const [projectId, setProjectId] = useState('');
    const [referenceConfig, setReferenceConfig] = useState(null);
    const [previews, setPreviews] = useState({});

    const loadCategories = async () => {
        const response = await masterDataService.listCategories();
        setCategories(response.data?.data || response.data || []);
    };

    const loadParties = async () => {
        setLoading(true);
        try {
            const response = await masterDataService.listParties({ search: search || undefined, category: categoryFilter || undefined, per_page: 100 });
            setParties(response.data?.data || []);
        } catch {
            setParties([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        Promise.all([
            loadCategories(),
            projectService.list({ per_page: 100 }).then((response) => setProjects(response.data?.data || [])),
        ]).catch(() => toast.error('Failed to load master data'));
    }, []);

    useEffect(() => {
        if (tab !== 'parties') return undefined;
        const timer = setTimeout(loadParties, 300);
        return () => clearTimeout(timer);
    }, [tab, search, categoryFilter]);

    useEffect(() => {
        if (!projectId) {
            setReferenceConfig(null);
            return;
        }
        masterDataService.getReferenceSettings(projectId)
            .then((response) => setReferenceConfig(response.data))
            .catch(() => toast.error('Failed to load reference settings'));
    }, [projectId]);

    const openParty = (party = null) => {
        setPartyId(party?.id || null);
        setPartyForm(partyToForm(party));
        setErrors({});
        setPartyModal(true);
    };

    const updateContact = (type, field, value) => setPartyForm((current) => ({
        ...current,
        contacts: { ...current.contacts, [type]: { ...current.contacts[type], [field]: value } },
    }));

    const submitParty = async (event) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const payload = compactPartyPayload(partyForm);
            if (partyId) await masterDataService.updateParty(partyId, payload);
            else await masterDataService.createParty(payload);
            toast.success(partyId ? 'Company updated' : 'Company created');
            setPartyModal(false);
            await Promise.all([loadParties(), loadCategories()]);
        } catch (error) {
            setErrors(error.response?.data?.errors || {});
            toast.error(error.response?.data?.message || 'Failed to save company');
        } finally {
            setSaving(false);
        }
    };

    const deleteParty = async (party) => {
        if (!await confirm({ title: 'Delete company?', message: `Delete ${party.name}? Referenced companies cannot be deleted.` })) return;
        try {
            await masterDataService.deleteParty(party.id);
            toast.success('Company deleted');
            loadParties();
        } catch (error) {
            toast.error(error.response?.data?.message || 'Company is referenced and cannot be deleted');
        }
    };

    const saveCategory = async (event) => {
        event.preventDefault();
        try {
            const payload = { ...categoryForm, sort_order: Number(categoryForm.sort_order || 0) };
            if (categoryForm.id) await masterDataService.updateCategory(categoryForm.id, payload);
            else await masterDataService.createCategory(payload);
            toast.success('Category saved');
            setCategoryForm({ id: null, name: '', slug: '', is_active: true, sort_order: 0 });
            await loadCategories();
        } catch (error) {
            toast.error(error.response?.data?.message || 'Failed to save category');
        }
    };

    const deleteCategory = async (category) => {
        if (!await confirm({ title: 'Delete category?', message: `Delete ${category.name}?` })) return;
        try {
            await masterDataService.deleteCategory(category.id);
            toast.success('Category deleted');
            loadCategories();
        } catch (error) {
            toast.error(error.response?.data?.message || 'Category is in use and cannot be deleted');
        }
    };

    const updateSetting = (field, value) => setReferenceConfig((current) => ({
        ...current, settings: { ...current.settings, [field]: value },
    }));
    const updateTemplate = (index, field, value) => setReferenceConfig((current) => ({
        ...current,
        templates: current.templates.map((template, templateIndex) => templateIndex === index ? { ...template, [field]: value } : template),
    }));

    const saveReferences = async () => {
        setSaving(true);
        try {
            const payload = {
                settings: referenceConfig.settings,
                templates: referenceConfig.templates.map(({ code, name, type_token, pattern, padding, reset_period, is_active }) => ({
                    code, name, type_token, pattern, padding: Number(padding), reset_period, is_active,
                })),
            };
            const response = await masterDataService.updateReferenceSettings(projectId, payload);
            setReferenceConfig(response.data);
            toast.success('Reference settings updated');
        } catch (error) {
            toast.error(error.response?.data?.message || 'Failed to update reference settings');
        } finally {
            setSaving(false);
        }
    };

    const preview = async (code) => {
        try {
            const response = await masterDataService.previewReference(projectId, code);
            setPreviews((current) => ({ ...current, [code]: response.data.reference_no }));
        } catch (error) {
            toast.error(error.response?.data?.message || 'Preview failed');
        }
    };

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-gray-900">Master Data</h1>
                <p className="text-sm text-gray-500">Shared companies, categories, contacts, and project document references.</p>
            </div>
            <div className="flex flex-wrap gap-2 border-b border-gray-200">
                {tabs.map(({ key, label, icon: Icon }) => (
                    <button key={key} onClick={() => setTab(key)} className={`inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium ${tab === key ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500'}`}>
                        <Icon className="h-5 w-5" />{label}
                    </button>
                ))}
            </div>

            {tab === 'parties' && <>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-1 gap-3">
                        <div className="relative max-w-md flex-1"><HiOutlineSearch className="absolute left-3 top-2.5 h-5 w-5 text-gray-400" /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search company name or initial" className="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-3 text-sm" /></div>
                        <select value={categoryFilter} onChange={(event) => setCategoryFilter(event.target.value)} className="rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="">All categories</option>{categories.map((category) => <option key={category.id} value={category.slug}>{category.name}</option>)}</select>
                    </div>
                    <button onClick={() => openParty()} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white"><HiOutlinePlus />Add Company</button>
                </div>
                {loading ? <LoadingSpinner /> : <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><div className="overflow-x-auto"><table className="min-w-full divide-y divide-gray-200 text-sm"><thead className="bg-gray-50"><tr><th className="px-4 py-3 text-left">Company</th><th className="px-4 py-3 text-left">Categories</th><th className="px-4 py-3 text-left">Main PIC</th><th className="px-4 py-3 text-left">Additional PIC</th><th className="px-4 py-3 text-right">Actions</th></tr></thead><tbody className="divide-y divide-gray-100">{parties.map((party) => <tr key={party.id}><td className="px-4 py-3"><p className="font-semibold text-gray-900">{party.name}</p><p className="text-xs text-gray-500">{party.initial} · {party.is_active ? 'Active' : 'Inactive'}</p></td><td className="px-4 py-3">{party.categories.map((category) => <span key={category.id} className="mr-1 rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700">{category.name}</span>)}</td><td className="px-4 py-3"><p>{party.contacts?.main?.name}</p><p className="text-xs text-gray-500">{party.contacts?.main?.position}</p></td><td className="px-4 py-3"><p>{party.contacts?.additional?.name || '—'}</p><p className="text-xs text-gray-500">{party.contacts?.additional?.position}</p></td><td className="px-4 py-3 text-right"><button onClick={() => openParty(party)} className="rounded p-2 text-gray-500 hover:bg-gray-100"><HiOutlinePencil /></button><button onClick={() => deleteParty(party)} className="rounded p-2 text-red-500 hover:bg-red-50"><HiOutlineTrash /></button></td></tr>)}</tbody></table></div></div>}
            </>}

            {tab === 'categories' && <div className="grid gap-6 lg:grid-cols-[360px_1fr]">
                <form onSubmit={saveCategory} className="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200"><h2 className="font-semibold">{categoryForm.id ? 'Edit Category' : 'New Category'}</h2><input required placeholder="Category name" value={categoryForm.name} onChange={(event) => setCategoryForm((current) => ({ ...current, name: event.target.value }))} className={fieldClass()} /><input placeholder="slug (optional)" value={categoryForm.slug} onChange={(event) => setCategoryForm((current) => ({ ...current, slug: event.target.value }))} className={fieldClass()} /><input type="number" min="0" placeholder="Sort order" value={categoryForm.sort_order} onChange={(event) => setCategoryForm((current) => ({ ...current, sort_order: event.target.value }))} className={fieldClass()} /><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={categoryForm.is_active} onChange={(event) => setCategoryForm((current) => ({ ...current, is_active: event.target.checked }))} />Active</label><div className="flex gap-2"><button className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Save</button>{categoryForm.id && <button type="button" onClick={() => setCategoryForm({ id: null, name: '', slug: '', is_active: true, sort_order: 0 })} className="rounded-lg border px-4 py-2 text-sm">Cancel</button>}</div></form>
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><table className="min-w-full divide-y divide-gray-200 text-sm"><thead className="bg-gray-50"><tr><th className="px-4 py-3 text-left">Category</th><th className="px-4 py-3 text-left">Companies</th><th className="px-4 py-3 text-right">Actions</th></tr></thead><tbody className="divide-y">{categories.map((category) => <tr key={category.id}><td className="px-4 py-3"><p className="font-medium">{category.name}</p><p className="text-xs text-gray-500">{category.slug}{category.is_system ? ' · System' : ''}</p></td><td className="px-4 py-3">{category.parties_count ?? 0}</td><td className="px-4 py-3 text-right"><button onClick={() => setCategoryForm(category)} className="rounded p-2 text-gray-500"><HiOutlinePencil /></button>{!category.is_system && <button onClick={() => deleteCategory(category)} className="rounded p-2 text-red-500"><HiOutlineTrash /></button>}</td></tr>)}</tbody></table></div>
            </div>}

            {tab === 'references' && <div className="space-y-5">
                <select value={projectId} onChange={(event) => setProjectId(event.target.value)} className="w-full max-w-md rounded-lg border border-gray-300 px-3 py-2 text-sm"><option value="">Select project</option>{projects.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}</select>
                {projectId && !referenceConfig && <LoadingSpinner />}
                {referenceConfig && <><div className="grid gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:grid-cols-2 lg:grid-cols-5">{[['company_code', 'Company'], ['client_code', 'Client'], ['primary_project_code', 'Project'], ['alternate_project_code', 'Alternate project'], ['volume_code', 'Volume']].map(([field, label]) => <div key={field}><label className="mb-1 block text-xs font-medium text-gray-600">{label}</label><input value={referenceConfig.settings[field]} onChange={(event) => updateSetting(field, event.target.value.toUpperCase())} className={fieldClass()} /></div>)}</div>
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><div className="overflow-x-auto"><table className="min-w-full divide-y divide-gray-200 text-sm"><thead className="bg-gray-50"><tr><th className="px-3 py-3 text-left">Type</th><th className="px-3 py-3 text-left">Token</th><th className="px-3 py-3 text-left">Pattern</th><th className="px-3 py-3 text-left">Reset</th><th className="px-3 py-3 text-left">Preview</th></tr></thead><tbody className="divide-y">{referenceConfig.templates.map((template, index) => <tr key={template.code}><td className="px-3 py-3"><p className="font-medium">{template.name}</p><p className="text-xs text-gray-500">{template.code}</p></td><td className="px-3 py-3"><input value={template.type_token} onChange={(event) => updateTemplate(index, 'type_token', event.target.value)} className="w-28 rounded border px-2 py-1" /></td><td className="min-w-[420px] px-3 py-3"><input value={template.pattern} onChange={(event) => updateTemplate(index, 'pattern', event.target.value)} className="w-full rounded border px-2 py-1 font-mono text-xs" /></td><td className="px-3 py-3"><select value={template.reset_period} onChange={(event) => updateTemplate(index, 'reset_period', event.target.value)} className="rounded border px-2 py-1"><option value="annual">Annual</option><option value="monthly">Monthly</option><option value="never">Never</option></select></td><td className="px-3 py-3"><button onClick={() => preview(template.code)} className="rounded border px-2 py-1 text-xs">Preview</button><p className="mt-1 whitespace-nowrap text-xs text-gray-500">{previews[template.code]}</p></td></tr>)}</tbody></table></div></div>
                    <div className="flex justify-end"><button disabled={saving} onClick={saveReferences} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{saving ? 'Saving...' : 'Save Reference Settings'}</button></div></>}
            </div>}

            {partyModal && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setPartyModal(false)}><div className="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-6" onClick={(event) => event.stopPropagation()}><h2 className="mb-4 text-lg font-semibold">{partyId ? 'Edit Company' : 'Add Company'}</h2><form onSubmit={submitParty} className="space-y-4"><div className="grid gap-4 sm:grid-cols-[1fr_140px]"><div><label className="mb-1 block text-sm font-medium">Company Name *</label><input required value={partyForm.name} onChange={(event) => setPartyForm((current) => ({ ...current, name: event.target.value }))} className={fieldClass(errors.name)} /></div><div><label className="mb-1 block text-sm font-medium">3-character Initial *</label><input required maxLength="3" value={partyForm.initial} onChange={(event) => setPartyForm((current) => ({ ...current, initial: event.target.value.toUpperCase() }))} className={fieldClass(errors.initial)} /></div></div><div><label className="mb-2 block text-sm font-medium">Categories *</label><div className="flex flex-wrap gap-3">{categories.filter((category) => category.is_active).map((category) => <label key={category.id} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={partyForm.category_ids.includes(category.id)} onChange={(event) => setPartyForm((current) => ({ ...current, category_ids: event.target.checked ? [...current.category_ids, category.id] : current.category_ids.filter((id) => id !== category.id) }))} />{category.name}</label>)}</div></div><ContactFields title="Main PIC" value={partyForm.contacts.main} errors={errors} prefix="contacts.main" onChange={(field, value) => updateContact('main', field, value)} /><ContactFields title="Additional PIC" value={partyForm.contacts.additional} errors={errors} prefix="contacts.additional" onChange={(field, value) => updateContact('additional', field, value)} /><div className="grid gap-3 sm:grid-cols-2"><input placeholder="Address" value={partyForm.address} onChange={(event) => setPartyForm((current) => ({ ...current, address: event.target.value }))} className={fieldClass()} /><input type="url" placeholder="Website" value={partyForm.website} onChange={(event) => setPartyForm((current) => ({ ...current, website: event.target.value }))} className={fieldClass()} />{['city', 'state', 'country', 'postcode'].map((field) => <input key={field} placeholder={field[0].toUpperCase() + field.slice(1)} value={partyForm[field]} onChange={(event) => setPartyForm((current) => ({ ...current, [field]: event.target.value }))} className={fieldClass()} />)}</div><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={partyForm.is_active} onChange={(event) => setPartyForm((current) => ({ ...current, is_active: event.target.checked }))} />Active</label><div className="flex justify-end gap-2"><button type="button" onClick={() => setPartyModal(false)} className="rounded-lg border px-4 py-2 text-sm">Cancel</button><button disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{saving ? 'Saving...' : 'Save Company'}</button></div></form></div></div>}
        </div>
    );
}
