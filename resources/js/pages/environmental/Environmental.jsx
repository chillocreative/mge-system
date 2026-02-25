import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import environmentalService from '@/services/environmentalService';
import projectService from '@/services/projectService';
import toast from 'react-hot-toast';
import {
    HiOutlineGlobe, HiOutlineTrash, HiOutlineEye, HiOutlineClipboardCheck,
    HiOutlinePlus, HiOutlineSearch, HiOutlineDownload, HiOutlineChevronLeft,
    HiOutlineChevronRight, HiOutlineX, HiOutlinePhotograph,
} from 'react-icons/hi';

const TABS = [
    { key: 'overview', label: 'Overview', icon: HiOutlineGlobe },
    { key: 'waste', label: 'Waste Tracking', icon: HiOutlineTrash },
    { key: 'inspections', label: 'Site Inspections', icon: HiOutlineEye },
    { key: 'audits', label: 'Audits', icon: HiOutlineClipboardCheck },
];

const wasteStatusColors = { pending: 'bg-gray-100 text-gray-800', collected: 'bg-blue-100 text-blue-800', disposed: 'bg-yellow-100 text-yellow-800', verified: 'bg-green-100 text-green-800' };
const inspStatusColors = { satisfactory: 'bg-green-100 text-green-800', needs_improvement: 'bg-yellow-100 text-yellow-800', unsatisfactory: 'bg-red-100 text-red-800' };
const auditStatusColors = { scheduled: 'bg-blue-100 text-blue-800', in_progress: 'bg-yellow-100 text-yellow-800', completed: 'bg-green-100 text-green-800', closed: 'bg-gray-100 text-gray-800' };
const wasteTypeColors = { hazardous: 'bg-red-100 text-red-800', chemical: 'bg-orange-100 text-orange-800', general: 'bg-gray-100 text-gray-800', recyclable: 'bg-green-100 text-green-800', construction_debris: 'bg-yellow-100 text-yellow-800', organic: 'bg-emerald-100 text-emerald-800', electronic: 'bg-purple-100 text-purple-800', other: 'bg-gray-100 text-gray-800' };

const Badge = ({ text, colorMap }) => (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${colorMap[text] || 'bg-gray-100 text-gray-800'}`}>
        {text?.replace(/_/g, ' ')}
    </span>
);

export default function Environmental() {
    const { can } = useAuth();
    const [tab, setTab] = useState('overview');
    const [stats, setStats] = useState(null);
    const [items, setItems] = useState([]);
    const [pagination, setPagination] = useState({});
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [filters, setFilters] = useState({});
    const [projects, setProjects] = useState([]);
    const [loading, setLoading] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [showDetail, setShowDetail] = useState(null);

    useEffect(() => {
        projectService.list({ per_page: 100 }).then(r => setProjects(r.data?.data || [])).catch(() => {});
    }, []);

    const fetchOverview = useCallback(async () => {
        setLoading(true);
        try {
            const r = await environmentalService.getOverview();
            setStats(r.data.data);
        } catch { toast.error('Failed to load stats'); }
        setLoading(false);
    }, []);

    const fetchList = useCallback(async () => {
        setLoading(true);
        try {
            let r;
            const params = { page, per_page: 15, search, ...filters };
            if (tab === 'waste') r = await environmentalService.getWasteRecords(params);
            else if (tab === 'inspections') r = await environmentalService.getInspections(params);
            else if (tab === 'audits') r = await environmentalService.getAudits(params);
            if (r) {
                const d = r.data.data;
                setItems(d.data || []);
                setPagination({ current: d.current_page, last: d.last_page, total: d.total });
            }
        } catch { toast.error('Failed to load data'); }
        setLoading(false);
    }, [tab, page, search, filters]);

    useEffect(() => {
        if (tab === 'overview') fetchOverview();
        else fetchList();
    }, [tab, fetchOverview, fetchList]);

    useEffect(() => { setPage(1); }, [tab, search, filters]);

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Environmental</h1>
                    <p className="mt-1 text-sm text-gray-500">Waste tracking, site inspections & environmental audits</p>
                </div>
            </div>

            {/* Tabs */}
            <div className="border-b border-gray-200">
                <nav className="-mb-px flex space-x-6 overflow-x-auto">
                    {TABS.map(t => (
                        <button key={t.key} onClick={() => { setTab(t.key); setShowDetail(null); setShowModal(false); }}
                            className={`flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors ${
                                tab === t.key ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                            }`}>
                            <t.icon className="h-4 w-4" /> {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {tab === 'overview' && stats && <EnvOverview stats={stats} />}

            {tab !== 'overview' && !showModal && !showDetail && (
                <>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="relative">
                                <HiOutlineSearch className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <input type="text" placeholder="Search..." value={search} onChange={e => setSearch(e.target.value)}
                                    className="rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 w-64" />
                            </div>
                            <select value={filters.project_id || ''} onChange={e => setFilters(f => ({ ...f, project_id: e.target.value || undefined }))}
                                className="rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm focus:border-emerald-500 focus:outline-none">
                                <option value="">All Projects</option>
                                {projects.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </select>
                            {tab === 'waste' && (
                                <select value={filters.waste_type || ''} onChange={e => setFilters(f => ({ ...f, waste_type: e.target.value || undefined }))}
                                    className="rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm">
                                    <option value="">All Types</option>
                                    {['general','hazardous','recyclable','construction_debris','chemical','organic','electronic','other'].map(s => (
                                        <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>
                                    ))}
                                </select>
                            )}
                            {tab === 'inspections' && (
                                <select value={filters.overall_status || ''} onChange={e => setFilters(f => ({ ...f, overall_status: e.target.value || undefined }))}
                                    className="rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm">
                                    <option value="">All Statuses</option>
                                    {['satisfactory','needs_improvement','unsatisfactory'].map(s => (
                                        <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>
                                    ))}
                                </select>
                            )}
                            {tab === 'audits' && (
                                <select value={filters.status || ''} onChange={e => setFilters(f => ({ ...f, status: e.target.value || undefined }))}
                                    className="rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm">
                                    <option value="">All Statuses</option>
                                    {['scheduled','in_progress','completed','closed'].map(s => (
                                        <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>
                                    ))}
                                </select>
                            )}
                        </div>
                        {can('environmental.create') && (
                            <button onClick={() => setShowModal(true)}
                                className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                <HiOutlinePlus className="h-4 w-4" /> New {tab === 'waste' ? 'Waste Record' : tab === 'inspections' ? 'Inspection' : 'Audit'}
                            </button>
                        )}
                    </div>

                    {loading ? (
                        <div className="flex items-center justify-center py-12"><div className="h-8 w-8 animate-spin rounded-full border-4 border-emerald-500 border-t-transparent" /></div>
                    ) : (
                        <>
                            {tab === 'waste' && <WasteTable items={items} />}
                            {tab === 'inspections' && <InspectionsTable items={items} onView={setShowDetail} />}
                            {tab === 'audits' && <AuditsTable items={items} onView={setShowDetail} />}

                            {pagination.last > 1 && (
                                <div className="flex items-center justify-between border-t border-gray-200 pt-4">
                                    <p className="text-sm text-gray-500">Page {pagination.current} of {pagination.last} ({pagination.total} records)</p>
                                    <div className="flex gap-2">
                                        <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page <= 1}
                                            className="rounded-lg border px-3 py-1.5 text-sm disabled:opacity-50"><HiOutlineChevronLeft className="h-4 w-4" /></button>
                                        <button onClick={() => setPage(p => Math.min(pagination.last, p + 1))} disabled={page >= pagination.last}
                                            className="rounded-lg border px-3 py-1.5 text-sm disabled:opacity-50"><HiOutlineChevronRight className="h-4 w-4" /></button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </>
            )}

            {showModal && tab === 'waste' && <WasteModal projects={projects} onClose={() => setShowModal(false)} onSaved={() => { setShowModal(false); fetchList(); }} />}
            {showModal && tab === 'inspections' && <InspectionModal projects={projects} onClose={() => setShowModal(false)} onSaved={() => { setShowModal(false); fetchList(); }} />}
            {showModal && tab === 'audits' && <AuditModal projects={projects} onClose={() => setShowModal(false)} onSaved={() => { setShowModal(false); fetchList(); }} />}

            {showDetail && tab === 'inspections' && <InspectionDetail id={showDetail} onBack={() => setShowDetail(null)} canManage={can('environmental.manage')} onUpdated={fetchList} />}
            {showDetail && tab === 'audits' && <AuditDetail id={showDetail} onBack={() => setShowDetail(null)} canManage={can('environmental.manage')} onUpdated={fetchList} />}
        </div>
    );
}

/* ── Overview Panel ── */
function EnvOverview({ stats }) {
    const cards = [
        { label: 'Pending Waste', value: stats.pending_waste, color: 'amber', sub: `${stats.hazardous_waste_pending} hazardous` },
        { label: 'Total Waste Qty', value: parseFloat(stats.total_waste_quantity || 0).toLocaleString(), color: 'emerald', sub: 'all projects' },
        { label: 'Inspections This Month', value: stats.inspections_this_month, color: 'blue', sub: `${stats.unsatisfactory_inspections} unsatisfactory` },
        { label: 'Open Audits', value: stats.open_audits, color: 'purple', sub: `${stats.total_audits} total` },
    ];
    const colorMap = { amber: 'border-amber-200 bg-amber-50', emerald: 'border-emerald-200 bg-emerald-50', blue: 'border-blue-200 bg-blue-50', purple: 'border-purple-200 bg-purple-50' };
    const textMap = { amber: 'text-amber-700', emerald: 'text-emerald-700', blue: 'text-blue-700', purple: 'text-purple-700' };

    return (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {cards.map(c => (
                <div key={c.label} className={`rounded-xl border p-6 ${colorMap[c.color]}`}>
                    <p className="text-sm font-medium text-gray-600">{c.label}</p>
                    <p className={`mt-2 text-3xl font-bold ${textMap[c.color]}`}>{c.value}</p>
                    <p className="mt-1 text-xs text-gray-500">{c.sub}</p>
                </div>
            ))}
        </div>
    );
}

/* ── Waste Table ── */
function WasteTable({ items }) {
    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Description</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Project</th>
                        <th className="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Quantity</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Hauler</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Recorder</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {items.length === 0 && <tr><td colSpan="7" className="px-4 py-8 text-center text-sm text-gray-500">No waste records found</td></tr>}
                    {items.map(w => (
                        <tr key={w.id} className="hover:bg-gray-50">
                            <td className="px-4 py-3"><Badge text={w.waste_type} colorMap={wasteTypeColors} /></td>
                            <td className="px-4 py-3 text-sm text-gray-900 max-w-xs truncate">{w.description}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{w.project?.name || '-'}</td>
                            <td className="px-4 py-3 text-sm text-right text-gray-900">{w.quantity} {w.unit}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{w.hauler || '-'}</td>
                            <td className="px-4 py-3"><Badge text={w.status} colorMap={wasteStatusColors} /></td>
                            <td className="px-4 py-3 text-sm text-gray-500">{w.recorder?.first_name} {w.recorder?.last_name}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/* ── Inspections Table ── */
function InspectionsTable({ items, onView }) {
    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Title</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Project</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Inspector</th>
                        <th className="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">PDF</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {items.length === 0 && <tr><td colSpan="7" className="px-4 py-8 text-center text-sm text-gray-500">No inspections found</td></tr>}
                    {items.map(i => (
                        <tr key={i.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => onView(i.id)}>
                            <td className="px-4 py-3 text-sm font-medium text-gray-900">{i.title}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{i.project?.name || '-'}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{i.type?.replace(/_/g, ' ')}</td>
                            <td className="px-4 py-3"><Badge text={i.overall_status} colorMap={inspStatusColors} /></td>
                            <td className="px-4 py-3 text-sm text-gray-500">{new Date(i.inspection_date).toLocaleDateString()}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{i.inspector?.first_name} {i.inspector?.last_name}</td>
                            <td className="px-4 py-3 text-right">
                                <a href={environmentalService.getInspectionPdfUrl(i.id)} target="_blank" rel="noreferrer"
                                    onClick={e => e.stopPropagation()} className="text-emerald-600 hover:text-emerald-800">
                                    <HiOutlineDownload className="inline h-4 w-4" />
                                </a>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/* ── Audits Table ── */
function AuditsTable({ items, onView }) {
    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Title</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Project</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Audit Date</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Auditor</th>
                        <th className="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">PDF</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {items.length === 0 && <tr><td colSpan="7" className="px-4 py-8 text-center text-sm text-gray-500">No audits found</td></tr>}
                    {items.map(a => (
                        <tr key={a.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => onView(a.id)}>
                            <td className="px-4 py-3 text-sm font-medium text-gray-900">{a.title}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{a.project?.name || '-'}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{a.type}</td>
                            <td className="px-4 py-3"><Badge text={a.status} colorMap={auditStatusColors} /></td>
                            <td className="px-4 py-3 text-sm text-gray-500">{new Date(a.audit_date).toLocaleDateString()}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{a.auditor?.first_name} {a.auditor?.last_name}</td>
                            <td className="px-4 py-3 text-right">
                                <a href={environmentalService.getAuditPdfUrl(a.id)} target="_blank" rel="noreferrer"
                                    onClick={e => e.stopPropagation()} className="text-emerald-600 hover:text-emerald-800">
                                    <HiOutlineDownload className="inline h-4 w-4" />
                                </a>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/* ── Waste Create Modal ── */
function WasteModal({ projects, onClose, onSaved }) {
    const [form, setForm] = useState({ project_id: '', waste_type: 'general', description: '', quantity: '', unit: 'kg', disposal_method: '', disposal_date: '', hauler: '', manifest_number: '', destination: '' });
    const [photos, setPhotos] = useState([]);
    const [saving, setSaving] = useState(false);
    const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

    const submit = async e => {
        e.preventDefault();
        setSaving(true);
        try {
            const fd = new FormData();
            Object.entries(form).forEach(([k, v]) => { if (v) fd.append(k, v); });
            photos.forEach(p => fd.append('photos[]', p));
            await environmentalService.createWaste(fd);
            toast.success('Waste record created');
            onSaved();
        } catch (err) { toast.error(err.response?.data?.message || 'Failed to save'); }
        setSaving(false);
    };

    return (
        <Modal title="New Waste Record" onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Select label="Project *" value={form.project_id} onChange={v => set('project_id', v)} options={projects.map(p => ({ value: p.id, label: p.name }))} required />
                    <Select label="Waste Type *" value={form.waste_type} onChange={v => set('waste_type', v)}
                        options={['general','hazardous','recyclable','construction_debris','chemical','organic','electronic','other'].map(s => ({ value: s, label: s.replace(/_/g, ' ') }))} required />
                    <Input label="Quantity *" type="number" step="0.01" value={form.quantity} onChange={v => set('quantity', v)} required />
                    <Select label="Unit *" value={form.unit} onChange={v => set('unit', v)}
                        options={['kg','tonnes','liters','cubic_meters','bags','drums','loads'].map(s => ({ value: s, label: s.replace(/_/g, ' ') }))} required />
                    <Input label="Disposal Method" value={form.disposal_method} onChange={v => set('disposal_method', v)} />
                    <Input label="Disposal Date" type="date" value={form.disposal_date} onChange={v => set('disposal_date', v)} />
                    <Input label="Hauler" value={form.hauler} onChange={v => set('hauler', v)} />
                    <Input label="Manifest Number" value={form.manifest_number} onChange={v => set('manifest_number', v)} />
                    <Input label="Destination" value={form.destination} onChange={v => set('destination', v)} />
                </div>
                <Textarea label="Description *" value={form.description} onChange={v => set('description', v)} required />
                <PhotoUpload photos={photos} setPhotos={setPhotos} />
                <div className="flex justify-end gap-3 pt-2">
                    <button type="button" onClick={onClose} className="rounded-lg border px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" disabled={saving} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700 disabled:opacity-50">
                        {saving ? 'Saving...' : 'Create Record'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

/* ── Inspection Create Modal ── */
function InspectionModal({ projects, onClose, onSaved }) {
    const [form, setForm] = useState({ project_id: '', title: '', inspection_date: '', type: 'routine', findings: '', recommendations: '', overall_status: 'satisfactory', follow_up_required: false, follow_up_date: '', corrective_actions: '' });
    const [photos, setPhotos] = useState([]);
    const [saving, setSaving] = useState(false);
    const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

    const submit = async e => {
        e.preventDefault();
        setSaving(true);
        try {
            const fd = new FormData();
            Object.entries(form).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined) {
                    fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v);
                }
            });
            photos.forEach(p => fd.append('photos[]', p));
            await environmentalService.createInspection(fd);
            toast.success('Inspection recorded');
            onSaved();
        } catch (err) { toast.error(err.response?.data?.message || 'Failed to save'); }
        setSaving(false);
    };

    return (
        <Modal title="New Site Inspection" onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Select label="Project *" value={form.project_id} onChange={v => set('project_id', v)} options={projects.map(p => ({ value: p.id, label: p.name }))} required />
                    <Input label="Title *" value={form.title} onChange={v => set('title', v)} required />
                    <Input label="Date *" type="date" value={form.inspection_date} onChange={v => set('inspection_date', v)} required />
                    <Select label="Type *" value={form.type} onChange={v => set('type', v)}
                        options={['routine','follow_up','complaint','regulatory','pre_construction','other'].map(s => ({ value: s, label: s.replace(/_/g, ' ') }))} required />
                    <Select label="Overall Status *" value={form.overall_status} onChange={v => set('overall_status', v)}
                        options={['satisfactory','needs_improvement','unsatisfactory'].map(s => ({ value: s, label: s.replace(/_/g, ' ') }))} required />
                    <div className="flex items-center gap-2 pt-6">
                        <input type="checkbox" id="followUp" checked={form.follow_up_required} onChange={e => set('follow_up_required', e.target.checked)}
                            className="h-4 w-4 rounded border-gray-300 text-emerald-600" />
                        <label htmlFor="followUp" className="text-sm text-gray-700">Follow-up Required</label>
                    </div>
                    {form.follow_up_required && (
                        <Input label="Follow-up Date" type="date" value={form.follow_up_date} onChange={v => set('follow_up_date', v)} />
                    )}
                </div>
                <Textarea label="Findings *" value={form.findings} onChange={v => set('findings', v)} required />
                <Textarea label="Recommendations" value={form.recommendations} onChange={v => set('recommendations', v)} />
                <Textarea label="Corrective Actions" value={form.corrective_actions} onChange={v => set('corrective_actions', v)} />
                <PhotoUpload photos={photos} setPhotos={setPhotos} />
                <div className="flex justify-end gap-3 pt-2">
                    <button type="button" onClick={onClose} className="rounded-lg border px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" disabled={saving} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700 disabled:opacity-50">
                        {saving ? 'Saving...' : 'Record Inspection'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

/* ── Audit Create Modal ── */
function AuditModal({ projects, onClose, onSaved }) {
    const [form, setForm] = useState({ project_id: '', title: '', audit_date: '', type: 'internal', scope: '', findings: '', non_conformities: '', corrective_actions: '', status: 'scheduled', next_audit_date: '' });
    const [photos, setPhotos] = useState([]);
    const [saving, setSaving] = useState(false);
    const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

    const submit = async e => {
        e.preventDefault();
        setSaving(true);
        try {
            const fd = new FormData();
            Object.entries(form).forEach(([k, v]) => { if (v) fd.append(k, v); });
            photos.forEach(p => fd.append('photos[]', p));
            await environmentalService.createAudit(fd);
            toast.success('Audit record created');
            onSaved();
        } catch (err) { toast.error(err.response?.data?.message || 'Failed to save'); }
        setSaving(false);
    };

    return (
        <Modal title="New Environmental Audit" onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Select label="Project *" value={form.project_id} onChange={v => set('project_id', v)} options={projects.map(p => ({ value: p.id, label: p.name }))} required />
                    <Input label="Title *" value={form.title} onChange={v => set('title', v)} required />
                    <Input label="Audit Date *" type="date" value={form.audit_date} onChange={v => set('audit_date', v)} required />
                    <Select label="Type *" value={form.type} onChange={v => set('type', v)}
                        options={['internal','external','regulatory'].map(s => ({ value: s, label: s }))} required />
                    <Select label="Status *" value={form.status} onChange={v => set('status', v)}
                        options={['scheduled','in_progress','completed','closed'].map(s => ({ value: s, label: s.replace(/_/g, ' ') }))} required />
                    <Input label="Next Audit Date" type="date" value={form.next_audit_date} onChange={v => set('next_audit_date', v)} />
                </div>
                <Textarea label="Scope" value={form.scope} onChange={v => set('scope', v)} />
                <Textarea label="Findings *" value={form.findings} onChange={v => set('findings', v)} required />
                <Textarea label="Non-Conformities" value={form.non_conformities} onChange={v => set('non_conformities', v)} />
                <Textarea label="Corrective Actions" value={form.corrective_actions} onChange={v => set('corrective_actions', v)} />
                <PhotoUpload photos={photos} setPhotos={setPhotos} />
                <div className="flex justify-end gap-3 pt-2">
                    <button type="button" onClick={onClose} className="rounded-lg border px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" disabled={saving} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700 disabled:opacity-50">
                        {saving ? 'Saving...' : 'Create Audit'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

/* ── Inspection Detail ── */
function InspectionDetail({ id, onBack, canManage, onUpdated }) {
    const [record, setRecord] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        environmentalService.getInspection(id).then(r => setRecord(r.data.data)).catch(() => toast.error('Failed to load')).finally(() => setLoading(false));
    }, [id]);

    if (loading) return <div className="flex justify-center py-12"><div className="h-8 w-8 animate-spin rounded-full border-4 border-emerald-500 border-t-transparent" /></div>;
    if (!record) return null;

    return (
        <div className="space-y-6">
            <button onClick={onBack} className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <HiOutlineChevronLeft className="h-4 w-4" /> Back to list
            </button>
            <div className="rounded-xl border border-gray-200 bg-white p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="text-xl font-bold text-gray-900">{record.title}</h2>
                        <div className="mt-2"><Badge text={record.overall_status} colorMap={inspStatusColors} /></div>
                    </div>
                    <a href={environmentalService.getInspectionPdfUrl(id)} target="_blank" rel="noreferrer"
                        className="rounded-lg border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        <HiOutlineDownload className="inline h-4 w-4 mr-1" />PDF
                    </a>
                </div>
                <div className="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <InfoRow label="Project" value={record.project?.name} />
                    <InfoRow label="Inspector" value={`${record.inspector?.first_name} ${record.inspector?.last_name}`} />
                    <InfoRow label="Date" value={new Date(record.inspection_date).toLocaleDateString()} />
                    <InfoRow label="Type" value={record.type?.replace(/_/g, ' ')} />
                    <InfoRow label="Follow-Up Required" value={record.follow_up_required ? 'Yes' : 'No'} />
                    {record.follow_up_date && <InfoRow label="Follow-Up Date" value={new Date(record.follow_up_date).toLocaleDateString()} />}
                </div>
                <div className="mt-6 space-y-4">
                    <DetailBlock label="Findings" text={record.findings} />
                    {record.recommendations && <DetailBlock label="Recommendations" text={record.recommendations} />}
                    {record.corrective_actions && <DetailBlock label="Corrective Actions" text={record.corrective_actions} />}
                </div>
                {record.photos?.length > 0 && (
                    <div className="mt-6">
                        <h3 className="mb-2 text-sm font-medium text-gray-700">Photos ({record.photos.length})</h3>
                        <div className="flex flex-wrap gap-2">
                            {record.photos.map(p => (
                                <div key={p.id} className="flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm">
                                    <HiOutlinePhotograph className="h-4 w-4 text-gray-400" /> {p.file_name}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

/* ── Audit Detail ── */
function AuditDetail({ id, onBack, canManage, onUpdated }) {
    const [record, setRecord] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        environmentalService.getAudit(id).then(r => setRecord(r.data.data)).catch(() => toast.error('Failed to load')).finally(() => setLoading(false));
    }, [id]);

    const updateStatus = async (status) => {
        try {
            await environmentalService.updateAudit(id, { status });
            toast.success(`Status updated to ${status.replace(/_/g, ' ')}`);
            const r = await environmentalService.getAudit(id);
            setRecord(r.data.data);
            onUpdated();
        } catch { toast.error('Failed to update'); }
    };

    if (loading) return <div className="flex justify-center py-12"><div className="h-8 w-8 animate-spin rounded-full border-4 border-emerald-500 border-t-transparent" /></div>;
    if (!record) return null;

    return (
        <div className="space-y-6">
            <button onClick={onBack} className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <HiOutlineChevronLeft className="h-4 w-4" /> Back to list
            </button>
            <div className="rounded-xl border border-gray-200 bg-white p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="text-xl font-bold text-gray-900">{record.title}</h2>
                        <div className="mt-2"><Badge text={record.status} colorMap={auditStatusColors} /></div>
                    </div>
                    <a href={environmentalService.getAuditPdfUrl(id)} target="_blank" rel="noreferrer"
                        className="rounded-lg border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        <HiOutlineDownload className="inline h-4 w-4 mr-1" />PDF
                    </a>
                </div>
                <div className="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <InfoRow label="Project" value={record.project?.name} />
                    <InfoRow label="Auditor" value={`${record.auditor?.first_name} ${record.auditor?.last_name}`} />
                    <InfoRow label="Date" value={new Date(record.audit_date).toLocaleDateString()} />
                    <InfoRow label="Type" value={record.type} />
                    {record.next_audit_date && <InfoRow label="Next Audit" value={new Date(record.next_audit_date).toLocaleDateString()} />}
                </div>
                <div className="mt-6 space-y-4">
                    {record.scope && <DetailBlock label="Scope" text={record.scope} />}
                    <DetailBlock label="Findings" text={record.findings} />
                    {record.non_conformities && <DetailBlock label="Non-Conformities" text={record.non_conformities} />}
                    {record.corrective_actions && <DetailBlock label="Corrective Actions" text={record.corrective_actions} />}
                </div>
                {record.photos?.length > 0 && (
                    <div className="mt-6">
                        <h3 className="mb-2 text-sm font-medium text-gray-700">Photos ({record.photos.length})</h3>
                        <div className="flex flex-wrap gap-2">
                            {record.photos.map(p => (
                                <div key={p.id} className="flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm">
                                    <HiOutlinePhotograph className="h-4 w-4 text-gray-400" /> {p.file_name}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
                {canManage && !['completed', 'closed'].includes(record.status) && (
                    <div className="mt-6 flex gap-2 border-t pt-4">
                        {record.status === 'scheduled' && <StatusBtn label="In Progress" status="in_progress" onClick={updateStatus} />}
                        {['scheduled','in_progress'].includes(record.status) && <StatusBtn label="Completed" status="completed" onClick={updateStatus} />}
                        <StatusBtn label="Closed" status="closed" onClick={updateStatus} />
                    </div>
                )}
            </div>
        </div>
    );
}

/* ── Shared Components ── */
function Modal({ title, onClose, children }) {
    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-20">
            <div className="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600"><HiOutlineX className="h-5 w-5" /></button>
                </div>
                {children}
            </div>
        </div>
    );
}

function Input({ label, ...props }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            <input {...props} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
        </div>
    );
}

function Textarea({ label, value, onChange, ...props }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            <textarea value={value} onChange={e => onChange(e.target.value)} rows={3}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" {...props} />
        </div>
    );
}

function Select({ label, value, onChange, options, ...props }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            <select value={value} onChange={e => onChange(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none" {...props}>
                <option value="">Select...</option>
                {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
        </div>
    );
}

function PhotoUpload({ photos, setPhotos }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Photos</label>
            <input type="file" multiple accept="image/*" onChange={e => setPhotos([...e.target.files])}
                className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-emerald-700 hover:file:bg-emerald-100" />
            {photos.length > 0 && <p className="mt-1 text-xs text-gray-500">{photos.length} file(s) selected</p>}
        </div>
    );
}

function InfoRow({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase text-gray-500">{label}</dt>
            <dd className="mt-1 text-sm text-gray-900">{value || 'N/A'}</dd>
        </div>
    );
}

function DetailBlock({ label, text }) {
    return (
        <div>
            <h4 className="text-sm font-medium text-gray-700">{label}</h4>
            <p className="mt-1 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-sm text-gray-600">{text}</p>
        </div>
    );
}

function StatusBtn({ label, status, onClick }) {
    return (
        <button onClick={() => onClick(status)}
            className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
            Mark as {label}
        </button>
    );
}
