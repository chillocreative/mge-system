import { useState, useEffect, useMemo, useRef } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import dashboardService from '@/services/dashboardService';
import LoadingSpinner from '@/components/LoadingSpinner';
import SearchableSelect from '@/components/SearchableSelect';
import { formatDate } from '@/utils/date';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Filler,
    Tooltip,
    Legend,
} from 'chart.js';
import { Doughnut, Line } from 'react-chartjs-2';
import {
    HiOutlineBriefcase,
    HiOutlineLightningBolt,
    HiOutlineCurrencyDollar,
    HiOutlineUserGroup,
    HiOutlineClipboardList,
    HiOutlineArrowSmRight,
    HiOutlineClipboardCheck,
    HiOutlineAcademicCap,
    HiOutlineClock,
    HiOutlineCalendar,
} from 'react-icons/hi';

ChartJS.register(
    CategoryScale, LinearScale, BarElement, LineElement, PointElement, ArcElement, Filler, Tooltip, Legend
);

// A restrained, mid-tone palette — legible on white, without the saturated
// "dashboard template" neon. Completed/Approved reuses the brand teal so the
// one positive state a viewer scans for stays recognisable.
const STATUS_COLORS_MAP = {
    'Draft': '#94a3b8', 'Planning': '#38bdf8', 'In progress': '#fbbf24', 'On hold': '#fb923c',
    'Completed': '#0d9488', 'Cancelled': '#f87171', 'Pending': '#94a3b8', 'In review': '#a78bfa',
    'Approved': '#0d9488', 'Rejected': '#f87171',
};

const priorityColors = {
    low: 'bg-slate-100 text-slate-600',
    medium: 'bg-sky-100 text-sky-700',
    high: 'bg-amber-100 text-amber-700',
    critical: 'bg-red-100 text-red-700',
};
const statusBadge = {
    draft: 'bg-slate-100 text-slate-600', planning: 'bg-sky-100 text-sky-700',
    in_progress: 'bg-amber-100 text-amber-700', on_hold: 'bg-orange-100 text-orange-700',
    completed: 'bg-primary-100 text-primary-700', cancelled: 'bg-red-100 text-red-700',
    pending: 'bg-slate-100 text-slate-600', in_review: 'bg-violet-100 text-violet-700',
};

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { labels: { font: { size: 12, family: 'Inter, sans-serif' }, color: '#6b7280', padding: 18, usePointStyle: true, pointStyleWidth: 8, boxHeight: 8 } },
        tooltip: {
            backgroundColor: '#ffffff', borderColor: '#e5e7eb', borderWidth: 1,
            titleColor: '#111827', bodyColor: '#4b5563',
            titleFont: { size: 13, weight: '600', family: 'Inter, sans-serif' }, bodyFont: { size: 12, family: 'Inter, sans-serif' },
            padding: 12, cornerRadius: 10, displayColors: true, boxPadding: 4,
        },
    },
};

function formatCurrency(value) {
    if (value >= 1_000_000) return `RM ${(value / 1_000_000).toFixed(1)}M`;
    if (value >= 1_000) return `RM ${(value / 1_000).toFixed(0)}K`;
    return `RM ${Number(value || 0).toLocaleString('en-MY')}`;
}

function greeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
}

/**
 * Slim multi-segment ratio bar for a StatCard. Only ever fed real,
 * already-true-subset numbers (e.g. delayed ⊆ active projects) — it must
 * never imply a proportion that isn't actually real.
 */
function RatioBar({ segments, total, caption }) {
    return (
        <div className="mt-4">
            <div className="flex h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                {segments.map((s, i) => (
                    <div key={i} className={`h-full ${s.color}`} style={{ width: `${total > 0 ? Math.min(100, (s.value / total) * 100) : 0}%` }} />
                ))}
            </div>
            {caption && <p className="mt-2 text-xs text-gray-400">{caption}</p>}
        </div>
    );
}

function StatCard({ label, value, icon: Icon, tint, subtitle, format, ratio, className = '' }) {
    const displayValue = format === 'currency' ? formatCurrency(value ?? 0) : (value ?? 0);
    return (
        <div className={`group rounded-2xl border border-gray-200/70 bg-white p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-gray-200 hover:shadow-md hover:shadow-gray-200/60 ${className}`}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-[13px] font-medium text-gray-500">{label}</p>
                    <p className="mt-2 text-[28px] font-semibold leading-none tracking-tight text-gray-900 tabular-nums">{displayValue}</p>
                    {subtitle && !ratio && <p className="mt-2 text-xs text-gray-400">{subtitle}</p>}
                </div>
                <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${tint?.bg || 'bg-gray-50'}`}>
                    <Icon className={`h-[18px] w-[18px] ${tint?.icon || 'text-gray-400'}`} />
                </div>
            </div>
            {ratio && <RatioBar {...ratio} />}
        </div>
    );
}

function CardSection({ title, action, cards, dim = false }) {
    if (!cards.length) return null;
    return (
        <section>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <h2 className="text-xs font-semibold uppercase tracking-wider text-gray-400">{title}</h2>
                {action}
            </div>
            <div className={`grid grid-cols-2 gap-4 transition-opacity duration-300 md:grid-cols-4 ${dim ? 'opacity-50' : ''}`}>
                {cards.map((c) => <StatCard key={c.key} {...c} />)}
            </div>
        </section>
    );
}

function ChartCard({ title, subtitle, children, total, totalLabel }) {
    return (
        <div className="rounded-2xl border border-gray-200/70 bg-white p-6">
            <div className="mb-5 flex items-baseline justify-between gap-3">
                <h3 className="text-[15px] font-semibold text-gray-900">{title}</h3>
                {subtitle && <span className="text-xs font-medium text-gray-400">{subtitle}</span>}
            </div>
            <div className="relative">
                {children}
                {total != null && (
                    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span className="text-3xl font-semibold tracking-tight text-gray-900 tabular-nums">{total}</span>
                        <span className="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-gray-400">{totalLabel}</span>
                    </div>
                )}
            </div>
        </div>
    );
}

function Panel({ title, icon: Icon, link, linkLabel, children }) {
    return (
        <div className="rounded-2xl border border-gray-200/70 bg-white">
            <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <div className="flex items-center gap-2.5">
                    <Icon className="h-[18px] w-[18px] text-gray-400" />
                    <h3 className="text-[15px] font-semibold text-gray-900">{title}</h3>
                </div>
                {link && (
                    <Link to={link} className="flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700">
                        {linkLabel || 'View all'} <HiOutlineArrowSmRight className="h-3.5 w-3.5" />
                    </Link>
                )}
            </div>
            <div className="divide-y divide-gray-100">{children}</div>
        </div>
    );
}

const cardDefs = {
    my_open_tasks: { label: 'My Open Tasks', icon: HiOutlineClipboardList, tint: { bg: 'bg-violet-50', icon: 'text-violet-500' }, subtitle: 'Assigned to me' },
    overdue_tasks: { label: 'Overdue Tasks', icon: HiOutlineClock, tint: { bg: 'bg-amber-50', icon: 'text-amber-500' }, subtitle: 'Past due date' },
    total_staff: { label: 'Active Staff', icon: HiOutlineUserGroup, tint: { bg: 'bg-indigo-50', icon: 'text-indigo-500' } },
    pending_approvals: { label: 'Leave to Approve', icon: HiOutlineClipboardCheck, tint: { bg: 'bg-amber-50', icon: 'text-amber-500' }, subtitle: 'Awaiting you' },
    training_pending: { label: 'Training Requests', icon: HiOutlineAcademicCap, tint: { bg: 'bg-sky-50', icon: 'text-sky-500' }, subtitle: 'Pending' },
    dept_open_tasks: { label: 'Open Tasks', icon: HiOutlineClipboardList, tint: { bg: 'bg-teal-50', icon: 'text-teal-500' }, subtitle: 'In department' },
};

const MY_WORK = ['my_open_tasks', 'overdue_tasks'];
const DEPARTMENT = ['total_staff', 'pending_approvals', 'training_pending', 'dept_open_tasks'];

const pickCards = (keys, stats) =>
    keys.filter((k) => stats[k] !== undefined).map((k) => ({ key: k, value: stats[k], ...cardDefs[k] }));

export default function Dashboard() {
    const { user, can } = useAuth();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [dept, setDept] = useState('');
    const mounted = useRef(false);

    useEffect(() => {
        let active = true;
        if (mounted.current) setRefreshing(true);
        dashboardService.getData(dept)
            .then((res) => { if (active) setData(res.data); })
            .catch(() => {})
            .finally(() => { if (active) { setLoading(false); setRefreshing(false); mounted.current = true; } });
        return () => { active = false; };
    }, [dept]);

    const doughnut = (items) => !items?.length ? null : {
        data: { labels: items.map((i) => i.label), datasets: [{ data: items.map((i) => i.value), backgroundColor: items.map((i) => STATUS_COLORS_MAP[i.label] || '#94a3b8'), borderWidth: 0, hoverOffset: 4 }] },
        options: { ...chartDefaults, cutout: '74%', plugins: { ...chartDefaults.plugins, legend: { ...chartDefaults.plugins.legend, position: 'bottom' } } },
        total: items.reduce((sum, i) => sum + (i.value || 0), 0),
    };
    const projectStatusChart = useMemo(() => doughnut(data?.charts?.project_status), [data?.charts?.project_status]);
    const leaveStatusChart = useMemo(() => doughnut(data?.charts?.leave_status), [data?.charts?.leave_status]);
    const taskStatusChart = useMemo(() => doughnut(data?.charts?.task_status), [data?.charts?.task_status]);

    // Real 12-month project trend — already computed server-side, just never
    // surfaced before. No fabricated data: this is exactly what monthly_projects
    // returns, one point per actual calendar month.
    const trend = data?.charts?.monthly_projects;
    const trendChart = useMemo(() => {
        if (!trend?.length) return null;
        return {
            data: {
                labels: trend.map((m) => m.label.split(' ')[0]),
                datasets: [{
                    data: trend.map((m) => m.value),
                    borderColor: '#0d9488',
                    backgroundColor: (ctx) => {
                        const { chart } = ctx;
                        const { ctx: c, chartArea } = chart;
                        if (!chartArea) return 'rgba(13,148,136,0.08)';
                        const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        g.addColorStop(0, 'rgba(13,148,136,0.28)');
                        g.addColorStop(1, 'rgba(13,148,136,0)');
                        return g;
                    },
                    fill: true,
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: '#0d9488',
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: chartDefaults.plugins.tooltip },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#9ca3af', font: { size: 11 } }, border: { display: false } },
                    y: { display: false, beginAtZero: true },
                },
            },
        };
    }, [trend]);

    if (loading) return <LoadingSpinner />;

    const stats = data?.stats || {};
    const lists = data?.lists || {};
    const departments = data?.departments || [];
    const myBalance = data?.my?.leave_balance || [];
    const myTasks = lists.my_tasks || [];
    const recentProjects = lists.recent_projects || [];
    const pendingApprovals = lists.pending_approvals || [];

    const myWorkCards = pickCards(MY_WORK, stats);
    const deptCards = pickCards(DEPARTMENT, stats);

    // Project health — delayed is a genuine subset of active (both share the
    // "not completed/cancelled" condition; delayed additionally has a past
    // end_date), so the bar shows a real part-of-whole, not an invented one.
    const hasProjectHealth = stats.active_projects !== undefined;
    const onTrack = Math.max(0, (stats.active_projects || 0) - (stats.delayed_projects || 0));

    // Contract value — receivables as a share of total contract value is a
    // standard "% still to collect" business metric, computed from real sums.
    const hasFinance = stats.total_revenue !== undefined;
    const receivablesPct = stats.total_revenue > 0 ? Math.round((stats.receivables / stats.total_revenue) * 100) : 0;

    // One headline status chart, chosen by what the user can see.
    const primary = projectStatusChart
        ? { title: 'Project Status', totalLabel: 'Projects', ...projectStatusChart }
        : leaveStatusChart
            ? { title: 'Leave by Status', totalLabel: 'Requests', ...leaveStatusChart }
            : taskStatusChart
                ? { title: 'My Tasks by Status', totalLabel: 'Tasks', ...taskStatusChart }
                : null;

    const deptDropdown = departments.length > 0 && (
        <SearchableSelect
            value={dept}
            onChange={setDept}
            options={departments}
            placeholder="All departments"
            getValue={(d) => d.id}
            getLabel={(d) => d.name}
            className="w-56"
        />
    );

    const today = new Date().toLocaleDateString('en-MY', { weekday: 'long', day: 'numeric', month: 'long' });

    return (
        <div className="space-y-8 pb-4">
            {/* Header */}
            <div>
                <p className="text-sm font-medium text-gray-400">{today}</p>
                <h1 className="mt-1 text-[28px] font-semibold tracking-tight text-gray-900 sm:text-3xl">
                    {greeting()}, {user?.first_name}
                </h1>
            </div>

            {/* My Work */}
            <CardSection title="My Work" cards={myWorkCards} />

            {/* Department — accumulates by the selected department */}
            <CardSection title="By Department" action={deptDropdown} cards={deptCards} dim={refreshing} />

            {/* Company-wide — the two cards below fold what used to be four flat
                boxes into two richer ones, each backed by a real ratio. */}
            {(hasProjectHealth || hasFinance) && (
                <section>
                    <h2 className="mb-4 text-xs font-semibold uppercase tracking-wider text-gray-400">Company-wide</h2>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {hasProjectHealth && (
                            <StatCard
                                label="Active Projects" value={stats.active_projects}
                                icon={HiOutlineLightningBolt} tint={{ bg: 'bg-primary-50', icon: 'text-primary-600' }}
                                ratio={{
                                    segments: [
                                        { value: onTrack, color: 'bg-primary-500' },
                                        { value: stats.delayed_projects || 0, color: 'bg-red-400' },
                                    ],
                                    total: stats.active_projects || 0,
                                    caption: stats.delayed_projects > 0
                                        ? `${stats.delayed_projects} of ${stats.active_projects} active projects past deadline`
                                        : 'All active projects on schedule',
                                }}
                            />
                        )}
                        {hasFinance && (
                            <StatCard
                                label="Contract Value" value={stats.total_revenue} format="currency"
                                icon={HiOutlineCurrencyDollar} tint={{ bg: 'bg-primary-50', icon: 'text-primary-600' }}
                                ratio={{
                                    segments: [{ value: stats.receivables || 0, color: 'bg-amber-400' }],
                                    total: stats.total_revenue || 0,
                                    caption: `${formatCurrency(stats.receivables)} outstanding — ${receivablesPct}% of total contract value`,
                                }}
                            />
                        )}
                    </div>
                </section>
            )}

            {/* Charts — status breakdown + a real 12-month trend */}
            <div className={`grid gap-6 ${primary && trendChart ? 'lg:grid-cols-2' : ''}`}>
                {primary && (
                    <ChartCard title={primary.title} total={primary.total} totalLabel={primary.totalLabel}>
                        <div className="h-64">
                            <Doughnut data={primary.data} options={primary.options} />
                        </div>
                    </ChartCard>
                )}
                {trendChart && (
                    <ChartCard
                        title="New Projects"
                        subtitle="Last 12 months"
                    >
                        <div className="h-64">
                            <Line data={trendChart.data} options={trendChart.options} />
                        </div>
                    </ChartCard>
                )}
            </div>

            <Panel title="My Tasks" icon={HiOutlineClipboardList} link={can('tasks.view') ? '/tasks' : null}>
                {myTasks.length === 0 ? (
                    <p className="px-6 py-12 text-center text-sm text-gray-400">No pending tasks</p>
                ) : myTasks.map((task) => (
                    <div key={task.id} className="flex items-center justify-between px-6 py-3.5 transition-colors hover:bg-gray-50">
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-gray-900">{task.title}</p>
                            <p className="mt-0.5 text-xs text-gray-400">{task.project?.name}{task.due_date && <span className="ml-2">Due {formatDate(task.due_date)}</span>}</p>
                        </div>
                        <div className="ml-4 flex shrink-0 items-center gap-2">
                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${priorityColors[task.priority]}`}>{task.priority}</span>
                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${statusBadge[task.status] || 'bg-gray-100 text-gray-600'}`}>{task.status.replace(/_/g, ' ')}</span>
                        </div>
                    </div>
                ))}
            </Panel>

            {/* One role-relevant list */}
            {'pending_approvals' in lists ? (
                <Panel title="Leave Awaiting Approval" icon={HiOutlineClipboardCheck} link="/hr/leave/approvals" linkLabel="Review">
                    {pendingApprovals.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-gray-400">Nothing awaiting you</p>
                    ) : pendingApprovals.map((l) => (
                        <div key={l.id} className="flex items-center justify-between px-6 py-3.5">
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-gray-900">{l.employee} · {l.type}</p>
                                <p className="mt-0.5 text-xs text-gray-400">{formatDate(l.start_date)} → {formatDate(l.end_date)}</p>
                            </div>
                            <span className="ml-3 shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">{l.stage}</span>
                        </div>
                    ))}
                </Panel>
            ) : recentProjects.length > 0 ? (
                <Panel title="Recent Projects" icon={HiOutlineBriefcase} link={can('projects.view') ? '/projects' : null}>
                    {recentProjects.map((project) => (
                        <Link key={project.id} to={`/projects/${project.id}`} className="flex items-center justify-between px-6 py-3.5 transition-colors hover:bg-gray-50">
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-gray-900">{project.name}</p>
                                <p className="mt-0.5 text-xs text-gray-400">{project.code}{project.client && <span className="ml-1">— {project.client}</span>}</p>
                            </div>
                            <div className="ml-4 flex shrink-0 items-center gap-3">
                                <div className="w-20">
                                    <div className="mb-1 text-right text-[10px] font-medium text-gray-400">{project.progress}%</div>
                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                        <div className="h-full rounded-full bg-primary-500 transition-all" style={{ width: `${project.progress}%` }} />
                                    </div>
                                </div>
                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${statusBadge[project.status] || 'bg-gray-100 text-gray-600'}`}>{project.status.replace(/_/g, ' ')}</span>
                            </div>
                        </Link>
                    ))}
                </Panel>
            ) : myBalance.length > 0 ? (
                <Panel title="My Leave Balance" icon={HiOutlineCalendar} link="/leave/my" linkLabel="My Leave">
                    {myBalance.map((b) => (
                        <div key={b.label} className="px-6 py-3.5">
                            <div className="mb-1.5 flex items-center justify-between text-sm">
                                <span className="text-gray-900">{b.label}</span>
                                <span className="font-semibold text-primary-600 tabular-nums">{b.remaining}<span className="font-normal text-gray-400"> / {b.entitled} days</span></span>
                            </div>
                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                <div className="h-full rounded-full bg-primary-500" style={{ width: `${b.entitled > 0 ? Math.min(100, (b.remaining / b.entitled) * 100) : 0}%` }} />
                            </div>
                        </div>
                    ))}
                </Panel>
            ) : null}
        </div>
    );
}
