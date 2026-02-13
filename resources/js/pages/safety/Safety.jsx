import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import safetyService from '@/services/safetyService';
import projectService from '@/services/projectService';
import toast from 'react-hot-toast';
import {
    HiOutlineExclamationCircle, HiOutlineShieldCheck, HiOutlineUserGroup,
    HiOutlineClipboardCheck, HiOutlinePlus, HiOutlineSearch, HiOutlineDownload,
    HiOutlineChevronLeft, HiOutlineChevronRight, HiOutlineX, HiOutlinePhotograph,
} from 'react-icons/hi';

const TABS = [
    { key: 'overview', label: 'Overview', icon: HiOutlineShieldCheck },
    { key: 'incidents', label: 'Incidents', icon: HiOutlineExclamationCircle },
    { key: 'hazards', label: 'Hazards', icon: HiOutlineShieldCheck },
    { key: 'meetings', label: 'Toolbox Meetings', icon: HiOutlineUserGroup },
    { key: 'checklists', label: 'Checklists', icon: HiOutlineClipboardCheck },
];

const severityColors = { minor: 'bg-blue-100 text-blue-800', moderate: 'bg-yellow-100 text-yellow-800', serious: 'bg-orange-100 text-orange-800', critical: 'bg-red-100 text-red-800' };
const statusColors = { open: 'bg-red-100 text-red-800', investigating: 'bg-yellow-100 text-yellow-800', resolved: 'bg-green-100 text-green-800', closed: 'bg-gray-100 text-gray-800', mitigated: 'bg-blue-100 text-blue-800' };
const riskColors = { low: 'bg-green-100 text-green-800', medium: 'bg-yellow-100 text-yellow-800', high: 'bg-orange-100 text-orange-800', critical: 'bg-red-100 text-red-800' };
const checklistStatusColors = { compliant: 'bg-green-100 text-green-800', non_compliant: 'bg-red-100 text-red-800', partial: 'bg-yellow-100 text-yellow-800' };

const Badge = ({ text, colorMap }) => (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${colorMap[text] || 'bg-gray-100 text-gray-800'}`}>
        {text?.replace(/_/g, ' ')}
    </span>
);

export default function Safety() {
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
        projectService.getProjects({ per_page: 100 }).then(r => setProjects(r.data.data?.data || [])).catch(() => {});
    }, []);

    // Fetch overview stats
    const fetchOverview = useCallback(async () => {
        setLoading(true);
        try {
            const r = await safetyService.getOverview();
            setStats(r.data.data);
        } catch { toast.error('Failed to load stats'); }
        setLoading(false);
    }, []);

    // Fetch list data
    const fetchList = useCallback(async () => {
        setLoading(true);
        try {
            let r;
            const params = { page, per_page: 15, search, ...filters };
            if (tab === 'incidents') r = await safetyService.getIncidents(params);
            else if (tab === 'hazards') r = await safetyService.getHazards(params);
            else if (tab === 'meetings') r = await safetyService.getMeetings(params);
            else if (tab === 'checklists') r = await safetyService.getChecklists(params);
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
            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Safety & OSHA</h1>
                    <p className="mt-1 text-sm text-gray-500">Incidents, hazards, toolbox meetings & compliance</p>
                </div>
            </div>

            {/* Tabs */}
            <div className="border-b border-gray-200">
                <nav className="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
                    {TABS.map(t => (
                        <button key={t.key} onClick={() => { setTab(t.key); setShowDetail(null); setShowModal(false); }}
                            className={`flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors ${
                                tab === t.key ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                            }`}>
                            <t.icon className="h-4 w-4" /> {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Content */}
            {tab === 'overview' && stats && <OverviewPanel stats={stats} />}

            {tab !== 'overview' && !showModal && !showDetail && (
                <>
                    {/* Toolbar */}
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="relative">
                                <HiOutlineSearch className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <input type="text" placeholder="Search..." value={search} onChange={e => setSearch(e.target.value)}
                                    className="rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500 w-64" />
                            </div>
                            <select value={filters.project_id || ''} onChange={e => setFilters(f => ({ ...f, project_id: e.target.value || undefined }))}
                                className="rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm focus:border-red-500 focus:outline-none">
                                <option value="">All Projects</option>
                                {projects.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </select>
                            {tab === 'incidents' && (
                                <>
                                    <select value={filters.severity || ''} onChange={e => setFilters(f => ({ ...f, severity: e.target.value || undefined }))}
                                        className="rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm">
                                        <option value="">All Severities</option>
                                        {['minor','moderate','serious','critical'].map(s => <option key={s} value={s}>{s}</option>)}
                                    </select>
                                    <select value={filters.status || ''} onChange={e => setFilters(f => ({ ...f, status: e.target.value || undefined }))}
                                        className="rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm">
                                        <option value="">All Statuses</option>
                                        {['open','investigating','resolved','closed'].map(s => <option key={s} value={s}>{s}</option>)}
                                    </select>
                                </>
                            )}
                            {tab === 'hazards' && (
                                <select value={filters.risk_level || ''} onChange={e => setFilters(f => ({ ...f, risk_level: e.target.value || undefined }))}
                                    className="rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm">
                                    <option value="">All Risk Levels</option>
                                    {['low','medium','high','critical'].map(s => <option key={s} value={s}>{s}</option>)}
                                </select>
                            )}
                        </div>
                        {can('safety.create') && (
                            <button onClick={() => setShowModal(true)}
                                className="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                <HiOutlinePlus className="h-4 w-4" /> New {tab === 'incidents' ? 'Incident' : tab === 'hazards' ? 'Hazard' : tab === 'meetings' ? 'Meeting' : 'Checklist'}
                            </button>
                        )}
                    </div>

                    {/* Table */}
                    {loading ? (
                        <div className="flex items-center justify-center py-12"><div className="h-8 w-8 animate-spin rounded-full border-4 border-red-500 border-t-transparent" /></div>
                    ) : (
                        <>
                            {tab === 'incidents' && <IncidentsTable items={items} onView={setShowDetail} />}
                            {tab === 'hazards' && <HazardsTable items={items} />}
                            {tab === 'meetings' && <MeetingsTable items={items} onView={setShowDetail} />}
                            {tab === 'checklists' && <ChecklistsTable items={items} onView={setShowDetail} />}

                            {pagination.last > 1 && (
                                <div className="flex items-center justify-between border-t border-gray-200 pt-4">
                                    <p className="text-sm text-gray-500">Showing page {pagination.current} of {pagination.last} ({pagination.total} records)</p>
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

            {/* Create Modals */}
            {showModal && tab === 'incidents' && <IncidentModal projects={projects} onClose={() => setShowModal(false)} onSaved={() => { setShowModal(false); fetchList(); }} />}
            {showModal && tab === 'hazards' && <HazardModal projects={projects} onClose={() => setShowModal(false)} onSaved={() => { setShowModal(false); fetchList(); }} />}
            {showModal && tab === 'meetings' && <MeetingModal projects={projects} onClose={() => setShowModal(false)} onSaved={() => { setShowModal(false); fetchList(); }} />}
            {showModal && tab === 'checklists' && <ChecklistModal projects={projects} onClose={() => setShowModal(false)} onSaved={() => { setShowModal(false); fetchList(); }} />}

            {/* Detail Views */}
            {showDetail && tab === 'incidents' && <IncidentDetail id={showDetail} onBack={() => setShowDetail(null)} canManage={can('safety.manage')} onUpdated={fetchList} />}
            {showDetail && tab === 'meetings' && <MeetingDetail id={showDetail} onBack={() => setShowDetail(null)} />}
            {showDetail && tab === 'checklists' && <ChecklistDetail id={showDetail} onBack={() => setShowDetail(null)} />}
        </div>
    );
}

/* ── Overview Panel ── */
function OverviewPanel({ stats }) {
    const cards = [
        { label: 'Open Incidents', value: stats.open_incidents, color: 'red', sub: `${stats.critical_incidents} critical` },
        { label: 'Open Hazards', value: stats.open_hazards, color: 'orange', sub: `${stats.high_risk_hazards} high risk` },
        { label: 'Meetings This Month', value: stats.meetings_this_month, color: 'blue', sub: `${stats.total_meetings} total` },
        { label: 'Non-Compliant', value: stats.non_compliant_checklists, color: 'yellow', sub: `${stats.total_incidents} total incidents` },
    ];
    const colorMap = { red: 'border-red-200 bg-red-50', orange: 'border-orange-200 bg-orange-50', blue: 'border-blue-200 bg-blue-50', yellow: 'border-yellow-200 bg-yellow-50' };
    const textMap = { red: 'text-red-700', orange: 'text-orange-700', blue: 'text-blue-700', yellow: 'text-yellow-700' };

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

/* ── Incidents Table ── */
function IncidentsTable({ items, onView }) {
    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Title</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Project</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Severity</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Reporter</th>
                        <th className="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {items.length === 0 && <tr><td colSpan="7" className="px-4 py-8 text-center text-sm text-gray-500">No incidents found</td></tr>}
                    {items.map(i => (
                        <tr key={i.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => onView(i.id)}>
                            <td className="px-4 py-3 text-sm font-medium text-gray-900">{i.title}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{i.project?.name || '-'}</td>
                            <td className="px-4 py-3"><Badge text={i.severity} colorMap={severityColors} /></td>
                            <td className="px-4 py-3"><Badge text={i.status} colorMap={statusColors} /></td>
                            <td className="px-4 py-3 text-sm text-gray-500">{new Date(i.incident_date).toLocaleDateString()}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{i.reporter?.first_name} {i.reporter?.last_name}</td>
                            <td className="px-4 py-3 text-right">
                                <a href={safetyService.getIncidentPdfUrl(i.id)} target="_blank" rel="noreferrer"
                                    onClick={e => e.stopPropagation()} className="text-red-600 hover:text-red-800">
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

/* ── Hazards Table ── */
function HazardsTable({ items }) {
    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Title</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Project</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Risk Level</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Reporter</th>
                        <th className="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">PDF</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {items.length === 0 && <tr><td colSpan="7" className="px-4 py-8 text-center text-sm text-gray-500">No hazards found</td></tr>}
                    {items.map(h => (
                        <tr key={h.id} className="hover:bg-gray-50">
                            <td className="px-4 py-3 text-sm font-medium text-gray-900">{h.title}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{h.project?.name || '-'}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{h.hazard_type?.replace(/_/g, ' ')}</td>
                            <td className="px-4 py-3"><Badge text={h.risk_level} colorMap={riskColors} /></td>
                            <td className="px-4 py-3"><Badge text={h.status} colorMap={statusColors} /></td>
                            <td className="px-4 py-3 text-sm text-gray-500">{h.reporter?.first_name} {h.reporter?.last_name}</td>
                            <td className="px-4 py-3 text-right">
                                <a href={safetyService.getHazardPdfUrl(h.id)} target="_blank" rel="noreferrer" className="text-red-600 hover:text-red-800">
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

/* ── Meetings Table ── */
function MeetingsTable({ items, onView }) {
    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Title</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Project</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Conductor</th>
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Attendees</th>
                        <th className="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {items.length === 0 && <tr><td colSpan="6" className="px-4 py-8 text-center text-sm text-gray-500">No meetings found</td></tr>}
                    {items.map(m => (
                        <tr key={m.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => onView(m.id)}>
                            <td className="px-4 py-3 text-sm font-medium text-gray-900">{m.title}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{m.project?.name || '-'}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{new Date(m.meeting_date).toLocaleDateString()}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{m.conductor?.first_name} {m.conductor?.last_name}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{m.attendees_count || 0}</td>
                            <td className="px-4 py-3 text-right">
                                <a href={safetyService.getMeetingPdfUrl(m.id)} target="_blank" rel="noreferrer"
                                    onClick={e => e.stopPropagation()} className="text-red-600 hover:text-red-800">
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

/* ── Checklists Table ── */
function ChecklistsTable({ items, onView }) {
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
                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Items</th>
                        <th className="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {items.length === 0 && <tr><td colSpan="7" className="px-4 py-8 text-center text-sm text-gray-500">No checklists found</td></tr>}
                    {items.map(c => (
                        <tr key={c.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => onView(c.id)}>
                            <td className="px-4 py-3 text-sm font-medium text-gray-900">{c.title}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{c.project?.name || '-'}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{c.type?.replace(/_/g, ' ').toUpperCase()}</td>
                            <td className="px-4 py-3"><Badge text={c.overall_status} colorMap={checklistStatusColors} /></td>
                            <td className="px-4 py-3 text-sm text-gray-500">{new Date(c.checklist_date).toLocaleDateString()}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{c.items_count || 0}</td>
                            <td className="px-4 py-3 text-right">
                                <a href={safetyService.getChecklistPdfUrl(c.id)} target="_blank" rel="noreferrer"
                                    onClick={e => e.stopPropagation()} className="text-red-600 hover:text-red-800">
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

/* ── Incident Create Modal ── */
function IncidentModal({ projects, onClose, onSaved }) {
    const [form, setForm] = useState({ project_id: '', title: '', description: '', incident_date: '', incident_time: '', location: '', severity: 'minor', type: 'near_miss', injured_person: '', injury_description: '', root_cause: '' });
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
            await safetyService.createIncident(fd);
            toast.success('Incident reported');
            onSaved();
        } catch (err) { toast.error(err.response?.data?.message || 'Failed to save'); }
        setSaving(false);
    };

    return (
        <Modal title="Report Incident" onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Select label="Project *" value={form.project_id} onChange={v => set('project_id', v)} options={projects.map(p => ({ value: p.id, label: p.name }))} required />
                    <Input label="Title *" value={form.title} onChange={v => set('title', v)} required />
                    <Input label="Date *" type="date" value={form.incident_date} onChange={v => set('incident_date', v)} required />
                    <Input label="Time" type="time" value={form.incident_time} onChange={v => set('incident_time', v)} />
                    <Input label="Location" value={form.location} onChange={v => set('location', v)} />
                    <Select label="Severity *" value={form.severity} onChange={v => set('severity', v)}
                        options={['minor','moderate','serious','critical'].map(s => ({ value: s, label: s }))} required />
                    <Select label="Type *" value={form.type} onChange={v => set('type', v)}
                        options={['injury','near_miss','property_damage','environmental','fire','other'].map(s => ({ value: s, label: s.replace(/_/g, ' ') }))} required />
                    <Input label="Injured Person" value={form.injured_person} onChange={v => set('injured_person', v)} />
                </div>
                <Textarea label="Description *" value={form.description} onChange={v => set('description', v)} required />
                <Textarea label="Injury Description" value={form.injury_description} onChange={v => set('injury_description', v)} />
                <Textarea label="Root Cause" value={form.root_cause} onChange={v => set('root_cause', v)} />
                <PhotoUpload photos={photos} setPhotos={setPhotos} />
                <div className="flex justify-end gap-3 pt-2">
                    <button type="button" onClick={onClose} className="rounded-lg border px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" disabled={saving} className="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 disabled:opacity-50">
                        {saving ? 'Saving...' : 'Report Incident'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

/* ── Hazard Create Modal ── */
function HazardModal({ projects, onClose, onSaved }) {
    const [form, setForm] = useState({ project_id: '', title: '', description: '', location: '', hazard_type: 'fall', risk_level: 'medium', recommended_action: '' });
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
            await safetyService.createHazard(fd);
            toast.success('Hazard reported');
            onSaved();
        } catch (err) { toast.error(err.response?.data?.message || 'Failed to save'); }
        setSaving(false);
    };

    return (
        <Modal title="Report Hazard" onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Select label="Project *" value={form.project_id} onChange={v => set('project_id', v)} options={projects.map(p => ({ value: p.id, label: p.name }))} required />
                    <Input label="Title *" value={form.title} onChange={v => set('title', v)} required />
                    <Input label="Location" value={form.location} onChange={v => set('location', v)} />
                    <Select label="Hazard Type *" value={form.hazard_type} onChange={v => set('hazard_type', v)}
                        options={['fall','electrical','chemical','structural','equipment','fire','ergonomic','other'].map(s => ({ value: s, label: s }))} required />
                    <Select label="Risk Level *" value={form.risk_level} onChange={v => set('risk_level', v)}
                        options={['low','medium','high','critical'].map(s => ({ value: s, label: s }))} required />
                </div>
                <Textarea label="Description *" value={form.description} onChange={v => set('description', v)} required />
                <Textarea label="Recommended Action" value={form.recommended_action} onChange={v => set('recommended_action', v)} />
                <PhotoUpload photos={photos} setPhotos={setPhotos} />
                <div className="flex justify-end gap-3 pt-2">
                    <button type="button" onClick={onClose} className="rounded-lg border px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" disabled={saving} className="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 disabled:opacity-50">
                        {saving ? 'Saving...' : 'Report Hazard'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

/* ── Meeting Create Modal ── */
function MeetingModal({ projects, onClose, onSaved }) {
    const [form, setForm] = useState({ project_id: '', title: '', topics: '', location: '', meeting_date: '', duration_minutes: '', notes: '', action_items: '' });
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
            await safetyService.createMeeting(fd);
            toast.success('Meeting logged');
            onSaved();
        } catch (err) { toast.error(err.response?.data?.message || 'Failed to save'); }
        setSaving(false);
    };

    return (
        <Modal title="Log Toolbox Meeting" onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Select label="Project *" value={form.project_id} onChange={v => set('project_id', v)} options={projects.map(p => ({ value: p.id, label: p.name }))} required />
                    <Input label="Title *" value={form.title} onChange={v => set('title', v)} required />
                    <Input label="Date *" type="date" value={form.meeting_date} onChange={v => set('meeting_date', v)} required />
                    <Input label="Duration (min)" type="number" value={form.duration_minutes} onChange={v => set('duration_minutes', v)} />
                    <Input label="Location" value={form.location} onChange={v => set('location', v)} />
                </div>
                <Textarea label="Topics Discussed *" value={form.topics} onChange={v => set('topics', v)} required />
                <Textarea label="Notes" value={form.notes} onChange={v => set('notes', v)} />
                <Textarea label="Action Items" value={form.action_items} onChange={v => set('action_items', v)} />
                <PhotoUpload photos={photos} setPhotos={setPhotos} />
                <div className="flex justify-end gap-3 pt-2">
                    <button type="button" onClick={onClose} className="rounded-lg border px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" disabled={saving} className="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 disabled:opacity-50">
                        {saving ? 'Saving...' : 'Log Meeting'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

/* ── Checklist Create Modal ── */
function ChecklistModal({ projects, onClose, onSaved }) {
    const [form, setForm] = useState({ project_id: '', title: '', type: 'general', checklist_date: '', notes: '' });
    const [items, setItems] = useState([{ item_text: '', status: 'na', notes: '' }]);
    const [saving, setSaving] = useState(false);
    const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

    const addItem = () => setItems(prev => [...prev, { item_text: '', status: 'na', notes: '' }]);
    const removeItem = (i) => setItems(prev => prev.filter((_, idx) => idx !== i));
    const updateItem = (i, k, v) => setItems(prev => prev.map((it, idx) => idx === i ? { ...it, [k]: v } : it));

    const submit = async e => {
        e.preventDefault();
        setSaving(true);
        try {
            await safetyService.createChecklist({ ...form, items });
            toast.success('Checklist created');
            onSaved();
        } catch (err) { toast.error(err.response?.data?.message || 'Failed to save'); }
        setSaving(false);
    };

    return (
        <Modal title="Create Compliance Checklist" onClose={onClose} wide>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Select label="Project *" value={form.project_id} onChange={v => set('project_id', v)} options={projects.map(p => ({ value: p.id, label: p.name }))} required />
                    <Input label="Title *" value={form.title} onChange={v => set('title', v)} required />
                    <Select label="Type *" value={form.type} onChange={v => set('type', v)}
                        options={['osha','fire_safety','ppe','scaffolding','electrical','excavation','general','custom'].map(s => ({ value: s, label: s.replace(/_/g, ' ').toUpperCase() }))} required />
                    <Input label="Date *" type="date" value={form.checklist_date} onChange={v => set('checklist_date', v)} required />
                </div>
                <Textarea label="Notes" value={form.notes} onChange={v => set('notes', v)} />

                <div>
                    <div className="mb-2 flex items-center justify-between">
                        <label className="text-sm font-medium text-gray-700">Checklist Items</label>
                        <button type="button" onClick={addItem} className="text-sm text-red-600 hover:text-red-700">+ Add Item</button>
                    </div>
                    <div className="space-y-2">
                        {items.map((it, i) => (
                            <div key={i} className="flex items-start gap-2 rounded-lg border border-gray-200 p-3">
                                <div className="flex-1 space-y-2">
                                    <input type="text" placeholder="Check item description..." value={it.item_text}
                                        onChange={e => updateItem(i, 'item_text', e.target.value)} required
                                        className="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-red-500 focus:outline-none" />
                                    <div className="flex gap-2">
                                        <select value={it.status} onChange={e => updateItem(i, 'status', e.target.value)}
                                            className="rounded border border-gray-300 px-2 py-1 text-sm">
                                            <option value="pass">Pass</option>
                                            <option value="fail">Fail</option>
                                            <option value="na">N/A</option>
                                        </select>
                                        <input type="text" placeholder="Notes..." value={it.notes || ''}
                                            onChange={e => updateItem(i, 'notes', e.target.value)}
                                            className="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-red-500 focus:outline-none" />
                                    </div>
                                </div>
                                {items.length > 1 && (
                                    <button type="button" onClick={() => removeItem(i)} className="mt-1 text-gray-400 hover:text-red-500">
                                        <HiOutlineX className="h-4 w-4" />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex justify-end gap-3 pt-2">
                    <button type="button" onClick={onClose} className="rounded-lg border px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" disabled={saving} className="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 disabled:opacity-50">
                        {saving ? 'Saving...' : 'Create Checklist'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

/* ── Incident Detail View ── */
function IncidentDetail({ id, onBack, canManage, onUpdated }) {
    const [record, setRecord] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        safetyService.getIncident(id).then(r => setRecord(r.data.data)).catch(() => toast.error('Failed to load')).finally(() => setLoading(false));
    }, [id]);

    const updateStatus = async (status) => {
        try {
            await safetyService.updateIncident(id, { status });
            toast.success(`Status updated to ${status}`);
            const r = await safetyService.getIncident(id);
            setRecord(r.data.data);
            onUpdated();
        } catch { toast.error('Failed to update'); }
    };

    if (loading) return <div className="flex justify-center py-12"><div className="h-8 w-8 animate-spin rounded-full border-4 border-red-500 border-t-transparent" /></div>;
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
                        <div className="mt-2 flex gap-2">
                            <Badge text={record.severity} colorMap={severityColors} />
                            <Badge text={record.status} colorMap={statusColors} />
                            <span className="text-sm text-gray-500">{record.type?.replace(/_/g, ' ')}</span>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <a href={safetyService.getIncidentPdfUrl(id)} target="_blank" rel="noreferrer"
                            className="rounded-lg border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                            <HiOutlineDownload className="inline h-4 w-4 mr-1" />PDF
                        </a>
                    </div>
                </div>
                <div className="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <InfoRow label="Project" value={record.project?.name} />
                    <InfoRow label="Reporter" value={`${record.reporter?.first_name} ${record.reporter?.last_name}`} />
                    <InfoRow label="Date" value={new Date(record.incident_date).toLocaleDateString()} />
                    <InfoRow label="Location" value={record.location} />
                    {record.injured_person && <InfoRow label="Injured Person" value={record.injured_person} />}
                    {record.investigator && <InfoRow label="Investigator" value={`${record.investigator.first_name} ${record.investigator.last_name}`} />}
                </div>
                <div className="mt-6 space-y-4">
                    <DetailBlock label="Description" text={record.description} />
                    {record.injury_description && <DetailBlock label="Injury Description" text={record.injury_description} />}
                    {record.root_cause && <DetailBlock label="Root Cause" text={record.root_cause} />}
                    {record.corrective_action && <DetailBlock label="Corrective Action" text={record.corrective_action} />}
                    {record.preventive_action && <DetailBlock label="Preventive Action" text={record.preventive_action} />}
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
                {canManage && record.status !== 'closed' && (
                    <div className="mt-6 flex gap-2 border-t pt-4">
                        {record.status === 'open' && <StatusBtn label="Investigating" status="investigating" onClick={updateStatus} />}
                        {['open','investigating'].includes(record.status) && <StatusBtn label="Resolved" status="resolved" onClick={updateStatus} />}
                        <StatusBtn label="Close" status="closed" onClick={updateStatus} />
                    </div>
                )}
            </div>
        </div>
    );
}

/* ── Meeting Detail View ── */
function MeetingDetail({ id, onBack }) {
    const [record, setRecord] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        safetyService.getMeeting(id).then(r => setRecord(r.data.data)).catch(() => toast.error('Failed to load')).finally(() => setLoading(false));
    }, [id]);

    if (loading) return <div className="flex justify-center py-12"><div className="h-8 w-8 animate-spin rounded-full border-4 border-red-500 border-t-transparent" /></div>;
    if (!record) return null;

    return (
        <div className="space-y-6">
            <button onClick={onBack} className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <HiOutlineChevronLeft className="h-4 w-4" /> Back to list
            </button>
            <div className="rounded-xl border border-gray-200 bg-white p-6">
                <div className="flex items-start justify-between">
                    <h2 className="text-xl font-bold text-gray-900">{record.title}</h2>
                    <a href={safetyService.getMeetingPdfUrl(id)} target="_blank" rel="noreferrer"
                        className="rounded-lg border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        <HiOutlineDownload className="inline h-4 w-4 mr-1" />PDF
                    </a>
                </div>
                <div className="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <InfoRow label="Project" value={record.project?.name} />
                    <InfoRow label="Conductor" value={`${record.conductor?.first_name} ${record.conductor?.last_name}`} />
                    <InfoRow label="Date" value={new Date(record.meeting_date).toLocaleDateString()} />
                    <InfoRow label="Duration" value={record.duration_minutes ? `${record.duration_minutes} min` : 'N/A'} />
                    <InfoRow label="Location" value={record.location} />
                </div>
                <div className="mt-6 space-y-4">
                    <DetailBlock label="Topics" text={record.topics} />
                    {record.notes && <DetailBlock label="Notes" text={record.notes} />}
                    {record.action_items && <DetailBlock label="Action Items" text={record.action_items} />}
                </div>
                {record.attendees?.length > 0 && (
                    <div className="mt-6">
                        <h3 className="mb-2 text-sm font-medium text-gray-700">Attendees ({record.attendees.length})</h3>
                        <div className="flex flex-wrap gap-2">
                            {record.attendees.map((a, i) => (
                                <span key={i} className="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700">
                                    {a.user ? `${a.user.first_name} ${a.user.last_name}` : a.name}
                                </span>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

/* ── Checklist Detail View ── */
function ChecklistDetail({ id, onBack }) {
    const [record, setRecord] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        safetyService.getChecklist(id).then(r => setRecord(r.data.data)).catch(() => toast.error('Failed to load')).finally(() => setLoading(false));
    }, [id]);

    if (loading) return <div className="flex justify-center py-12"><div className="h-8 w-8 animate-spin rounded-full border-4 border-red-500 border-t-transparent" /></div>;
    if (!record) return null;

    const passCount = record.items?.filter(i => i.status === 'pass').length || 0;
    const failCount = record.items?.filter(i => i.status === 'fail').length || 0;
    const naCount = record.items?.filter(i => i.status === 'na').length || 0;

    return (
        <div className="space-y-6">
            <button onClick={onBack} className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <HiOutlineChevronLeft className="h-4 w-4" /> Back to list
            </button>
            <div className="rounded-xl border border-gray-200 bg-white p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="text-xl font-bold text-gray-900">{record.title}</h2>
                        <div className="mt-2"><Badge text={record.overall_status} colorMap={checklistStatusColors} /></div>
                    </div>
                    <a href={safetyService.getChecklistPdfUrl(id)} target="_blank" rel="noreferrer"
                        className="rounded-lg border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        <HiOutlineDownload className="inline h-4 w-4 mr-1" />PDF
                    </a>
                </div>
                <div className="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <InfoRow label="Project" value={record.project?.name} />
                    <InfoRow label="Inspector" value={`${record.inspector?.first_name} ${record.inspector?.last_name}`} />
                    <InfoRow label="Date" value={new Date(record.checklist_date).toLocaleDateString()} />
                    <InfoRow label="Type" value={record.type?.replace(/_/g, ' ').toUpperCase()} />
                </div>
                <div className="mt-4 flex gap-4">
                    <span className="rounded-lg bg-green-50 px-3 py-1 text-sm font-medium text-green-700">{passCount} Pass</span>
                    <span className="rounded-lg bg-red-50 px-3 py-1 text-sm font-medium text-red-700">{failCount} Fail</span>
                    <span className="rounded-lg bg-gray-50 px-3 py-1 text-sm font-medium text-gray-700">{naCount} N/A</span>
                </div>
                {record.items?.length > 0 && (
                    <div className="mt-6">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">#</th>
                                    <th className="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Item</th>
                                    <th className="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Notes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {record.items.map((it, i) => (
                                    <tr key={it.id}>
                                        <td className="px-4 py-2 text-sm text-gray-500">{i + 1}</td>
                                        <td className="px-4 py-2 text-sm text-gray-900">{it.item_text}</td>
                                        <td className="px-4 py-2">
                                            <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                                                it.status === 'pass' ? 'bg-green-100 text-green-800' : it.status === 'fail' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'
                                            }`}>{it.status.toUpperCase()}</span>
                                        </td>
                                        <td className="px-4 py-2 text-sm text-gray-500">{it.notes || '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {record.notes && <div className="mt-6"><DetailBlock label="Notes" text={record.notes} /></div>}
            </div>
        </div>
    );
}

/* ── Shared Components ── */
function Modal({ title, onClose, children, wide }) {
    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-20">
            <div className={`w-full rounded-xl bg-white p-6 shadow-xl ${wide ? 'max-w-3xl' : 'max-w-2xl'}`}>
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
            <input {...props} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500" />
        </div>
    );
}

function Textarea({ label, value, onChange, ...props }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            <textarea value={value} onChange={e => onChange(e.target.value)} rows={3}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500" {...props} />
        </div>
    );
}

function Select({ label, value, onChange, options, ...props }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            <select value={value} onChange={e => onChange(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none" {...props}>
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
                className="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-red-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-red-700 hover:file:bg-red-100" />
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
