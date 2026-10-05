import { useCallback, useEffect, useState } from 'react';
import { useAuth } from '@/context/AuthContext';
import materialService from '@/services/materialService';
import toast from 'react-hot-toast';

const blank = { category: '', description: '', is_active: true };

export default function Materials() {
    const { can } = useAuth();
    const canEdit = can('projects.edit');
    const [materials, setMaterials] = useState([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(blank);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const response = await materialService.list({ search });
            setMaterials(response.data?.data || []);
        } catch {
            toast.error('Unable to load materials');
        } finally {
            setLoading(false);
        }
    }, [search]);

    useEffect(() => { load(); }, [load]);

    const save = async (event) => {
        event.preventDefault();
        try {
            if (editing) await materialService.update(editing, form);
            else await materialService.create(form);
            toast.success('Material saved');
            setEditing(null);
            setForm(blank);
            load();
        } catch (error) {
            toast.error(error.response?.data?.errors?.description?.[0] || error.response?.data?.message || 'Unable to save material');
        }
    };

    const toggle = async (material) => {
        try {
            await materialService.update(material.id, { is_active: !material.is_active });
            load();
        } catch {
            toast.error('Unable to update material status');
        }
    };

    return <div className="space-y-5">
        <div><h1 className="text-2xl font-bold text-gray-900">Project Finance — Materials</h1><p className="text-sm text-gray-500">Manage categories and descriptions available for new expenses.</p></div>
        {canEdit && <form onSubmit={save} className="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-[1fr_2fr_auto]">
            <label className="text-sm">Category<input aria-label="Material category" required maxLength={100} value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className="mt-1 w-full rounded border px-3 py-2" /></label>
            <label className="text-sm">Description<input aria-label="Material description" required maxLength={255} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="mt-1 w-full rounded border px-3 py-2" /></label>
            <div className="flex items-end gap-2"><button className="rounded bg-primary-600 px-4 py-2 text-white">{editing ? 'Save changes' : 'Add material'}</button>{editing && <button type="button" onClick={() => { setEditing(null); setForm(blank); }} className="rounded border px-4 py-2">Cancel</button>}</div>
        </form>}
        <input aria-label="Search materials" placeholder="Search category or description" value={search} onChange={(e) => setSearch(e.target.value)} className="w-full max-w-md rounded border px-3 py-2" />
        <div className="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><table className="w-full text-left text-sm"><thead className="bg-gray-50"><tr><th className="px-4 py-3">Category</th><th className="px-4 py-3">Description</th><th className="px-4 py-3">Status</th>{canEdit && <th className="px-4 py-3">Actions</th>}</tr></thead><tbody>{materials.map((material) => <tr key={material.id} className="border-t"><td className="px-4 py-3">{material.category}</td><td className="px-4 py-3">{material.description}</td><td className="px-4 py-3">{material.is_active ? 'Active' : 'Inactive'}</td>{canEdit && <td className="whitespace-nowrap px-4 py-3"><button onClick={() => { setEditing(material.id); setForm({ category: material.category, description: material.description, is_active: material.is_active }); }} className="mr-3 text-primary-600">Edit</button><button onClick={() => toggle(material)} className="text-primary-600">{material.is_active ? 'Deactivate' : 'Activate'}</button></td>}</tr>)}{!materials.length && <tr><td colSpan={canEdit ? 4 : 3} className="px-4 py-8 text-center text-gray-500">{loading ? 'Loading…' : 'No materials found.'}</td></tr>}</tbody></table></div>
    </div>;
}
