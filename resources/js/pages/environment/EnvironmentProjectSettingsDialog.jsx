import { useState, useEffect } from 'react';
import environmentSettingService from '@/services/environmentSettingService';
import toast from 'react-hot-toast';
import { HiOutlineTrash, HiOutlineDocumentText } from 'react-icons/hi';

const emptyForm = {
    consultant_company: '',
    consultant_name: '',
    consultant_reg_no: '',
    officer_name: '',
    officer_reg_no: '',
};

function UploadSlot({ label, projectId, kind, url, onChanged }) {
    const [busy, setBusy] = useState(false);

    const handlePick = async (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;
        setBusy(true);
        try {
            await environmentSettingService.uploadImage(projectId, kind, file);
            toast.success('Image uploaded');
            onChanged();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to upload image');
        } finally {
            setBusy(false);
        }
    };

    const handleRemove = async () => {
        setBusy(true);
        try {
            await environmentSettingService.removeImage(projectId, kind);
            toast.success('Image removed');
            onChanged();
        } catch {
            toast.error('Failed to remove image');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="rounded-lg border border-gray-200 p-3">
            <p className="mb-2 text-sm font-medium text-gray-700">{label}</p>
            {url ? (
                <div className="mb-2 flex items-center gap-3">
                    <div className="flex h-16 w-24 items-center justify-center overflow-hidden rounded bg-gray-50 ring-1 ring-gray-200">
                        <img
                            src={url}
                            alt={label}
                            className="h-full w-full object-cover"
                            onError={(e) => { e.target.style.display = 'none'; e.target.nextSibling.style.display = 'flex'; }}
                        />
                        <div className="hidden h-full w-full items-center justify-center text-gray-400" style={{ display: 'none' }}>
                            <HiOutlineDocumentText className="h-6 w-6" />
                        </div>
                    </div>
                    <a href={url} target="_blank" rel="noopener" className="text-xs text-primary-600 hover:underline">Preview</a>
                    <button type="button" onClick={handleRemove} disabled={busy} className="ml-auto rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove">
                        <HiOutlineTrash className="h-4 w-4" />
                    </button>
                </div>
            ) : (
                <p className="mb-2 text-xs text-gray-400">No file uploaded</p>
            )}
            <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                {busy ? 'Uploading...' : url ? 'Replace' : 'Upload'}
                <input type="file" accept="image/*,.pdf" className="hidden" disabled={busy} onChange={handlePick} />
            </label>
        </div>
    );
}

export default function EnvironmentProjectSettingsDialog({ projectId, onClose }) {
    const [form, setForm] = useState(emptyForm);
    const [settings, setSettings] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    const load = async () => {
        setLoading(true);
        try {
            const res = await environmentSettingService.get(projectId);
            const s = res.data || {};
            setSettings(s);
            setForm({
                consultant_company: s.consultant_company || '',
                consultant_name: s.consultant_name || '',
                consultant_reg_no: s.consultant_reg_no || '',
                officer_name: s.officer_name || '',
                officer_reg_no: s.officer_reg_no || '',
            });
        } catch {
            toast.error('Failed to load settings');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (projectId) load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [projectId]);

    const submit = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            await environmentSettingService.update(projectId, form);
            toast.success('Environment settings updated');
            load();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save settings');
        } finally {
            setSaving(false);
        }
    };

    const fieldClass = 'w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <h3 className="mb-4 text-lg font-semibold text-gray-900">Project Environment Settings</h3>
                {loading ? (
                    <p className="text-sm text-gray-500">Loading...</p>
                ) : (
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Consultant Company</label>
                            <input type="text" value={form.consultant_company} onChange={(e) => setForm((p) => ({ ...p, consultant_company: e.target.value }))} className={fieldClass} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Consultant Name</label>
                            <input type="text" value={form.consultant_name} onChange={(e) => setForm((p) => ({ ...p, consultant_name: e.target.value }))} className={fieldClass} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Consultant Reg. No</label>
                            <input type="text" value={form.consultant_reg_no} onChange={(e) => setForm((p) => ({ ...p, consultant_reg_no: e.target.value }))} className={fieldClass} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Officer Name</label>
                            <input type="text" value={form.officer_name} onChange={(e) => setForm((p) => ({ ...p, officer_name: e.target.value }))} className={fieldClass} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Officer Reg. No</label>
                            <input type="text" value={form.officer_reg_no} onChange={(e) => setForm((p) => ({ ...p, officer_reg_no: e.target.value }))} className={fieldClass} />
                        </div>

                        <UploadSlot
                            label="Environment Policy (image/PDF)"
                            projectId={projectId}
                            kind="policy"
                            url={settings?.policy_image_url}
                            onChanged={load}
                        />
                        <UploadSlot
                            label="Monitoring Location Map (image/PDF)"
                            projectId={projectId}
                            kind="location"
                            url={settings?.location_map_url}
                            onChanged={load}
                        />

                        <div className="flex justify-end gap-2 pt-2">
                            <button type="button" onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
                            <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}
