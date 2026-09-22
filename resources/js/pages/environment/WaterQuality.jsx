import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import { useConfirm } from '@/context/ConfirmContext';
import waterQualityService from '@/services/waterQualityService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import toast from 'react-hot-toast';
import {
    HiOutlinePlus, HiOutlineSearch, HiOutlineBeaker, HiOutlinePencil, HiOutlineTrash,
    HiOutlineDownload, HiOutlineEye,
} from 'react-icons/hi';

const STORAGE_KEY = 'environment.project';

const POINTS = ['W1', 'W2', 'W3', 'W4'];

const CONDITION_FIELDS = [
    { key: 'odour', label: 'Odour', options: ['No', 'Slightly', 'Medium', 'Strong'] },
    { key: 'floating', label: 'Floating Material', options: ['Yes', 'No'] },
    { key: 'area', label: 'Description Area', options: ['Residential', 'Forest', 'Bushes', 'Plantation', 'Construction'] },
    { key: 'flow', label: 'Flow of Water', options: ['Stagnant', 'Slow', 'Medium', 'Fast'] },
    { key: 'colour', label: 'Colour', options: ['Clear', 'Slightly Cloudy', 'Med. Cloudy', 'Very Cloudy'] },
    { key: 'level', label: 'Water Level', options: ['Shallow', 'Medium', 'Deep'] },
    { key: 'weather', label: 'Weather', options: ['Sunny', 'Cloudy', 'Light Rain', 'Med. Rain', 'Heavy Rain', 'Gloomy'] },
];

const INSITU_PARAMS = [
    { key: 'temperature', label: 'Temperature (°C)' },
    { key: 'ph', label: 'pH (-)' },
    { key: 'do', label: 'Dissolved Oxygen (D.O) (mg/L)' },
];

const LAB_PARAMS = [
    { key: 'cod', label: 'COD (mg/L)' },
    { key: 'bod', label: 'BOD (mg/L)' },
    { key: 'tss', label: 'TSS (mg/L)' },
    { key: 'oil_grease', label: 'Oil & Grease (mg/L)' },
    { key: 'ecoli', label: 'E-coli (CFU/100mL)' },
    { key: 'ammoniacal_n', label: 'Ammoniacal Nitrogen (mg/L)' },
    { key: 'temperature', label: 'Temperature (°C)' },
    { key: 'ph', label: 'pH (-)' },
    { key: 'do', label: 'Dissolved Oxygen (D.O) (mg/L)' },
];

const today = () => new Date().toISOString().split('T')[0];

function emptyConditions() {
    const c = {};
    POINTS.forEach((p) => { c[p] = {}; CONDITION_FIELDS.forEach((f) => { c[p][f.key] = ''; }); });
    return c;
}
function emptyParams(params) {
    const v = {};
    POINTS.forEach((p) => { v[p] = {}; params.forEach((f) => { v[p][f.key] = ''; }); });
    return v;
}

const emptyForm = () => ({
    project_id: '',
    sample_date: today(),
    sample_time: '',
    data_collector: '',
    witness: '',
    conditions: emptyConditions(),
    insitu: emptyParams(INSITU_PARAMS),
    lab: emptyParams(LAB_PARAMS),
    notes: '',
});

export default function WaterQuality() {
    const { can } = useAuth();
    const canCreate = can('environmental.create');
    const canManage = can('environmental.manage');
    const confirm = useConfirm();

    const [records, setRecords] = useState([]);
    const [projects, setProjects] = useState([]);
    const [pagination, setPagination] = useState({});
    const [loading, setLoading] = useState(true);

    const [projectFilter, setProjectFilter] = useState(() => localStorage.getItem(STORAGE_KEY) || '');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [search, setSearch] = useState('');

    const [showForm, setShowForm] = useState(false);
    const [readOnly, setReadOnly] = useState(false);
    const [editId, setEditId] = useState(null);
    const [form, setForm] = useState(emptyForm());
    const [showLab, setShowLab] = useState(false);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
    }, []);

    useEffect(() => {
        if (projectFilter) localStorage.setItem(STORAGE_KEY, projectFilter);
        else localStorage.removeItem(STORAGE_KEY);
    }, [projectFilter]);

    const fetchRecords = useCallback(async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (projectFilter) params.project_id = projectFilter;
            if (from) params.from = from;
            if (to) params.to = to;
            if (search) params.search = search;
            const res = await waterQualityService.list(params);
            setRecords(res.data?.data || []);
            setPagination(res.data || {});
        } catch {
            setRecords([]);
        } finally {
            setLoading(false);
        }
    }, [projectFilter, from, to, search]);

    useEffect(() => {
        const t = setTimeout(() => fetchRecords(), 300);
        return () => clearTimeout(t);
    }, [fetchRecords]);

    const openCreate = () => {
        setEditId(null);
        setReadOnly(false);
        setForm({ ...emptyForm(), project_id: projectFilter || '' });
        setShowLab(false);
        setErrors({});
        setShowForm(true);
    };

    const hydrateForm = (rec) => ({
        project_id: rec.project_id,
        sample_date: rec.sample_date ? String(rec.sample_date).slice(0, 10) : today(),
        sample_time: rec.sample_time ? String(rec.sample_time).slice(0, 5) : '',
        data_collector: rec.data_collector || '',
        witness: rec.witness || '',
        conditions: { ...emptyConditions(), ...(rec.conditions || {}) },
        insitu: { ...emptyParams(INSITU_PARAMS), ...(rec.insitu || {}) },
        lab: { ...emptyParams(LAB_PARAMS), ...(rec.lab || {}) },
        notes: rec.notes || '',
    });

    const openEdit = (rec) => {
        setEditId(rec.id);
        setReadOnly(false);
        setForm(hydrateForm(rec));
        setShowLab(Object.values(rec.lab || {}).some((v) => Object.values(v || {}).some((x) => x)));
        setErrors({});
        setShowForm(true);
    };

    const openView = (rec) => {
        setEditId(rec.id);
        setReadOnly(true);
        setForm(hydrateForm(rec));
        setShowLab(Object.values(rec.lab || {}).some((v) => Object.values(v || {}).some((x) => x)));
        setErrors({});
        setShowForm(true);
    };

    const submit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const payload = { ...form };
            if (editId) await waterQualityService.update(editId, payload);
            else await waterQualityService.create(payload);
            toast.success(editId ? 'Record updated' : 'Record created');
            setShowForm(false);
            fetchRecords(pagination.current_page || 1);
        } catch (err) {
            if (err.response?.status === 422) {
                const errs = err.response.data.errors || {};
                setErrors(errs);
                const first = Object.values(errs)[0]?.[0];
                toast.error(first || 'Please fix the highlighted fields');
            } else {
                toast.error(err.response?.data?.message || 'Failed to save record');
            }
        } finally {
            setSaving(false);
        }
    };

    const remove = async (rec) => {
        if (!(await confirm({ title: 'Delete record?', message: `Delete the water quality record for ${formatDate(rec.sample_date)}?` }))) return;
        try {
            await waterQualityService.remove(rec.id);
            toast.success('Record deleted');
            fetchRecords(pagination.current_page || 1);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to delete record');
        }
    };

    const download = async (rec) => {
        try { await waterQualityService.downloadPdf(rec.id); }
        catch { toast.error('Failed to download PDF'); }
    };

    const setCondition = (point, field, value) => {
        setForm((p) => ({ ...p, conditions: { ...p.conditions, [point]: { ...p.conditions[point], [field]: value } } }));
    };
    const setParam = (group, point, field, value) => {
        setForm((p) => ({ ...p, [group]: { ...p[group], [point]: { ...p[group][point], [field]: value } } }));
    };

    const pointsWithData = (rec) => POINTS.filter((p) => {
        const v = rec.insitu?.[p];
        return v && Object.values(v).some((x) => x !== '' && x !== null && x !== undefined);
    });

    const fieldClass = () => 'w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-500';
    const selectClass = 'w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-500';
    const inputCellClass = 'w-full rounded border border-gray-300 px-1.5 py-1 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-500';

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Water Quality Monitoring</h1>
                    <p className="text-sm text-gray-500">River condition and in-situ / lab parameter readings at sampling points W1–W4</p>
                </div>
                {canCreate && (
                    <button onClick={openCreate} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                        <HiOutlinePlus className="h-5 w-5" /> New Record
                    </button>
                )}
            </div>

            {/* Toolbar */}
            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
                <select value={projectFilter} onChange={(e) => setProjectFilter(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 lg:min-w-[16rem]">
                    <option value="">All Projects</option>
                    {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
                <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                <span className="text-sm text-gray-400">to</span>
                <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                <div className="relative max-w-xs flex-1">
                    <HiOutlineSearch className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input type="text" placeholder="Search collector / witness / notes..." value={search} onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                </div>
            </div>

            {loading ? <LoadingSpinner /> : records.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineBeaker className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No water quality records found</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Time</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Points sampled</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Data Collector</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Witness</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Updated</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {records.map((rec) => (
                                    <tr key={rec.id} className="hover:bg-gray-50">
                                        <td className="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{formatDate(rec.sample_date)}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{rec.sample_time ? String(rec.sample_time).slice(0, 5) : '-'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{rec.project?.name || '-'}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {pointsWithData(rec).length ? pointsWithData(rec).map((p) => (
                                                    <span key={p} className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">{p}</span>
                                                )) : <span className="text-xs text-gray-300">—</span>}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{rec.data_collector || '-'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{rec.witness || '-'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{formatDate(rec.updated_at)}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <button onClick={() => openView(rec)} title="View" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><HiOutlineEye className="h-4 w-4" /></button>
                                                {canManage && <button onClick={() => openEdit(rec)} title="Edit" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><HiOutlinePencil className="h-4 w-4" /></button>}
                                                <button onClick={() => download(rec)} title="Download worksheet" className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600"><HiOutlineDownload className="h-4 w-4" /></button>
                                                {canManage && <button onClick={() => remove(rec)} title="Delete" className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineTrash className="h-4 w-4" /></button>}
                                            </div>
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
                                    <button key={page} onClick={() => fetchRecords(page)} className={`rounded px-3 py-1 text-sm ${page === pagination.current_page ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}>{page}</button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Modal form */}
            {showForm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowForm(false)}>
                    <div className="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between gap-2">
                            <h3 className="text-lg font-semibold text-gray-900">
                                {readOnly ? 'Water Quality Record' : editId ? 'Edit Water Quality Record' : 'New Water Quality Record'}
                            </h3>
                            {readOnly && <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">Read only</span>}
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Project *</label>
                                    <select value={form.project_id} disabled={readOnly} onChange={(e) => setForm((p) => ({ ...p, project_id: e.target.value }))} required className={fieldClass()}>
                                        <option value="">Select project...</option>
                                        {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </select>
                                    {errors.project_id && <p className="mt-1 text-xs text-red-500">{errors.project_id[0]}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Date *</label>
                                    <input type="date" value={form.sample_date} disabled={readOnly} onChange={(e) => setForm((p) => ({ ...p, sample_date: e.target.value }))} required className={fieldClass()} />
                                    {errors.sample_date && <p className="mt-1 text-xs text-red-500">{errors.sample_date[0]}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Time</label>
                                    <input type="time" value={form.sample_time} disabled={readOnly} onChange={(e) => setForm((p) => ({ ...p, sample_time: e.target.value }))} className={fieldClass()} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Data Collector</label>
                                    <input type="text" value={form.data_collector} disabled={readOnly} onChange={(e) => setForm((p) => ({ ...p, data_collector: e.target.value }))} className={fieldClass()} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Witness</label>
                                    <input type="text" value={form.witness} disabled={readOnly} onChange={(e) => setForm((p) => ({ ...p, witness: e.target.value }))} className={fieldClass()} />
                                </div>
                            </div>

                            {/* River conditions */}
                            <div>
                                <h4 className="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">River Conditions</h4>
                                <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
                                    <table className="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-2 py-2 text-left font-semibold text-gray-500">Point</th>
                                                {CONDITION_FIELDS.map((f) => (
                                                    <th key={f.key} className="px-2 py-2 text-left font-semibold text-gray-500">{f.label}</th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {POINTS.map((point) => (
                                                <tr key={point}>
                                                    <td className="px-2 py-1.5 font-semibold text-gray-700">{point}</td>
                                                    {CONDITION_FIELDS.map((f) => (
                                                        <td key={f.key} className="px-2 py-1.5">
                                                            <select
                                                                value={form.conditions[point]?.[f.key] || ''}
                                                                disabled={readOnly}
                                                                onChange={(e) => setCondition(point, f.key, e.target.value)}
                                                                className={selectClass}
                                                            >
                                                                <option value="">—</option>
                                                                {f.options.map((o) => <option key={o} value={o}>{o}</option>)}
                                                            </select>
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* In-situ parameters */}
                            <div>
                                <h4 className="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Parameter (In-situ)</h4>
                                <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
                                    <table className="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-2 py-2 text-left font-semibold text-gray-500">Parameter</th>
                                                <th className="px-2 py-1 text-center font-semibold text-gray-500" colSpan={2}>W1 / W2 (Upstream)</th>
                                                <th className="px-2 py-1 text-center font-semibold text-gray-500" colSpan={2}>W3 / W4 (Downstream)</th>
                                            </tr>
                                            <tr>
                                                <th className="px-2 py-1"></th>
                                                <th className="px-2 py-1 text-center font-medium text-gray-400">W1</th>
                                                <th className="px-2 py-1 text-center font-medium text-gray-400">W2</th>
                                                <th className="px-2 py-1 text-center font-medium text-gray-400">W3</th>
                                                <th className="px-2 py-1 text-center font-medium text-gray-400">W4</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {INSITU_PARAMS.map((param) => (
                                                <tr key={param.key}>
                                                    <td className="px-2 py-1.5 text-gray-700">{param.label}</td>
                                                    {POINTS.map((point) => (
                                                        <td key={point} className="px-2 py-1.5">
                                                            <input
                                                                type="text"
                                                                value={form.insitu[point]?.[param.key] || ''}
                                                                disabled={readOnly}
                                                                onChange={(e) => setParam('insitu', point, param.key, e.target.value)}
                                                                className={inputCellClass}
                                                            />
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* Lab results (collapsible) */}
                            <div>
                                <button type="button" onClick={() => setShowLab((v) => !v)} className="mb-2 flex items-center gap-1 text-xs font-bold uppercase tracking-wide text-gray-500 hover:text-gray-700">
                                    Lab Results (optional) <span className="text-gray-400">{showLab ? '▲' : '▼'}</span>
                                </button>
                                {showLab && (
                                    <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
                                        <table className="min-w-full divide-y divide-gray-200 text-xs">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-2 py-2 text-left font-semibold text-gray-500">Parameter</th>
                                                    <th className="px-2 py-1 text-center font-semibold text-gray-500" colSpan={2}>W1 / W2 (Upstream)</th>
                                                    <th className="px-2 py-1 text-center font-semibold text-gray-500" colSpan={2}>W3 / W4 (Downstream)</th>
                                                </tr>
                                                <tr>
                                                    <th className="px-2 py-1"></th>
                                                    <th className="px-2 py-1 text-center font-medium text-gray-400">W1</th>
                                                    <th className="px-2 py-1 text-center font-medium text-gray-400">W2</th>
                                                    <th className="px-2 py-1 text-center font-medium text-gray-400">W3</th>
                                                    <th className="px-2 py-1 text-center font-medium text-gray-400">W4</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {LAB_PARAMS.map((param, idx) => (
                                                    <tr key={`${param.key}-${idx}`}>
                                                        <td className="px-2 py-1.5 text-gray-700">{param.label}</td>
                                                        {POINTS.map((point) => (
                                                            <td key={point} className="px-2 py-1.5">
                                                                <input
                                                                    type="text"
                                                                    value={form.lab[point]?.[param.key] || ''}
                                                                    disabled={readOnly}
                                                                    onChange={(e) => setParam('lab', point, param.key, e.target.value)}
                                                                    className={inputCellClass}
                                                                />
                                                            </td>
                                                        ))}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                                <textarea rows={2} value={form.notes} disabled={readOnly} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} className={fieldClass()} />
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    {readOnly ? 'Close' : 'Cancel'}
                                </button>
                                {!readOnly && (
                                    <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                        {saving ? 'Saving...' : editId ? 'Update Record' : 'Create Record'}
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
