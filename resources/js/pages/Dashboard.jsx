import { useState, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import dashboardService from '@/services/dashboardService';
import LoadingSpinner from '@/components/LoadingSpinner';
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
import { Doughnut, Bar, Line } from 'react-chartjs-2';
import {
    HiOutlineBriefcase,
    HiOutlineLightningBolt,
    HiOutlineExclamationCircle,
    HiOutlineCurrencyDollar,
    HiOutlineDocumentText,
    HiOutlineUserGroup,
    HiOutlineShieldExclamation,
    HiOutlineClipboardList,
    HiOutlineArrowSmRight,
    HiOutlineTrendingUp,
    HiOutlineOfficeBuilding,
    HiOutlineClipboardCheck,
    HiOutlineAcademicCap,
    HiOutlineClock,
    HiOutlineCalendar,
    HiOutlineSparkles,
} from 'react-icons/hi';

ChartJS.register(
    CategoryScale, LinearScale, BarElement, LineElement, PointElement, ArcElement, Filler, Tooltip, Legend
);

// Dark-theme chart colors
const AXIS = 'rgba(209, 250, 229, 0.55)';
const AXIS_SOFT = 'rgba(209, 250, 229, 0.4)';
const GRID = 'rgba(255, 255, 255, 0.08)';

const STATUS_COLORS_MAP = {
    'Draft': '#94a3b8', 'Planning': '#38bdf8', 'In progress': '#facc15', 'On hold': '#fb923c',
    'Completed': '#34d399', 'Cancelled': '#9ca3af', 'Pending': '#94a3b8', 'In review': '#a78bfa',
    'Approved': '#34d399', 'Rejected': '#f87171', 'Labor': '#38bdf8', 'Materials': '#a3e635',
    'Equipment rental': '#fb923c', 'Subcontractor': '#a78bfa', 'Utilities': '#22d3ee', 'Travel': '#f472b6', 'Other': '#94a3b8',
};
const CATEGORY_PALETTE = ['#bef264', '#34d399', '#38bdf8', '#a78bfa', '#fb923c', '#f472b6', '#facc15', '#22d3ee', '#94a3b8'];

const priorityColors = {
    low: 'bg-slate-400/20 text-slate-200',
    medium: 'bg-sky-400/20 text-sky-200',
    high: 'bg-amber-400/20 text-amber-200',
    critical: 'bg-red-400/20 text-red-200',
};
const statusBadge = {
    draft: 'bg-slate-400/20 text-slate-200', planning: 'bg-sky-400/20 text-sky-200',
    in_progress: 'bg-amber-400/20 text-amber-200', on_hold: 'bg-orange-400/20 text-orange-200',
    completed: 'bg-emerald-400/20 text-emerald-200', cancelled: 'bg-red-400/20 text-red-200',
    pending: 'bg-slate-400/20 text-slate-200', in_review: 'bg-violet-400/20 text-violet-200',
};

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { labels: { font: { size: 12, family: 'Inter, sans-serif' }, color: AXIS, padding: 16, usePointStyle: true, pointStyleWidth: 8 } },
        tooltip: {
            backgroundColor: 'rgba(3, 20, 12, 0.92)', borderColor: 'rgba(190,242,100,0.25)', borderWidth: 1,
            titleFont: { size: 13, family: 'Inter, sans-serif' }, bodyFont: { size: 12, family: 'Inter, sans-serif' },
            padding: 12, cornerRadius: 8, displayColors: true, boxPadding: 4,
        },
    },
};

function formatCurrency(value) {
    if (value >= 1_000_000) return `RM ${(value / 1_000_000).toFixed(1)}M`;
    if (value >= 1_000) return `RM ${(value / 1_000).toFixed(0)}K`;
    return `RM ${Number(value || 0).toLocaleString('en-MY')}`;
}

function Aurora() {
    return (
        <>
            <style>{`
                @keyframes dashAurora { 0%,100%{transform:translate(0,0) scale(1)} 33%{transform:translate(40px,-50px) scale(1.15)} 66%{transform:translate(-30px,30px) scale(0.92)} }
                @keyframes dashAurora2 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(-50px,40px) scale(1.2)} }
            `}</style>
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute -left-32 -top-32 h-[34rem] w-[34rem] rounded-full bg-emerald-500/20 blur-[120px]" style={{ animation: 'dashAurora 18s ease-in-out infinite' }} />
                <div className="absolute right-[-10rem] top-1/4 h-[28rem] w-[28rem] rounded-full bg-lime-400/15 blur-[130px]" style={{ animation: 'dashAurora2 21s ease-in-out infinite' }} />
                <div className="absolute bottom-[-12rem] left-1/3 h-[30rem] w-[30rem] rounded-full bg-teal-400/12 blur-[140px]" style={{ animation: 'dashAurora 24s ease-in-out infinite' }} />
                <div className="absolute inset-0 opacity-[0.05]" style={{
                    backgroundImage: 'linear-gradient(rgba(190,242,100,0.4) 1px, transparent 1px), linear-gradient(90deg, rgba(190,242,100,0.4) 1px, transparent 1px)',
                    backgroundSize: '46px 46px',
                    maskImage: 'radial-gradient(ellipse at center, black 20%, transparent 80%)',
                }} />
            </div>
        </>
    );
}

function StatCard({ label, value, icon: Icon, accent, subtitle, format }) {
    const displayValue = format === 'currency' ? formatCurrency(value ?? 0) : (value ?? 0);
    return (
        <div className="group relative overflow-hidden rounded-2xl border border-white/15 bg-white/[0.08] p-5 shadow-lg shadow-black/20 backdrop-blur-xl transition-all hover:border-white/20 hover:bg-white/[0.1]">
            <div className="flex items-start justify-between">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-emerald-100/60">{label}</p>
                    <p className="mt-2 text-2xl font-bold tracking-tight text-white">{displayValue}</p>
                    {subtitle && <p className="mt-1 text-xs text-emerald-200/40">{subtitle}</p>}
                </div>
                <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${accent?.bg || 'bg-white/10'}`}>
                    <Icon className={`h-5 w-5 ${accent?.icon || 'text-lime-300'}`} />
                </div>
            </div>
            <div className={`absolute bottom-0 left-0 h-1 w-full ${accent?.bar || 'bg-lime-400/50'}`} />
        </div>
    );
}

function ChartCard({ title, children, className = '' }) {
    return (
        <div className={`rounded-2xl border border-white/15 bg-white/[0.08] p-5 shadow-lg shadow-black/20 backdrop-blur-xl ${className}`}>
            <h3 className="mb-4 text-sm font-semibold text-emerald-50">{title}</h3>
            {children}
        </div>
    );
}

function Panel({ title, icon: Icon, link, linkLabel, children }) {
    return (
        <div className="rounded-2xl border border-white/15 bg-white/[0.08] shadow-lg shadow-black/20 backdrop-blur-xl">
            <div className="flex items-center justify-between border-b border-white/10 px-5 py-4">
                <div className="flex items-center gap-2">
                    <Icon className="h-5 w-5 text-emerald-200/50" />
                    <h3 className="text-sm font-semibold text-emerald-50">{title}</h3>
                </div>
                {link && (
                    <Link to={link} className="flex items-center gap-1 text-xs font-medium text-lime-300 hover:text-lime-200">
                        {linkLabel || 'View all'} <HiOutlineArrowSmRight className="h-3.5 w-3.5" />
                    </Link>
                )}
            </div>
            <div className="divide-y divide-white/5">{children}</div>
        </div>
    );
}

const cardDefs = {
    total_projects: { label: 'Total Projects', icon: HiOutlineBriefcase, accent: { bg: 'bg-sky-400/15', icon: 'text-sky-300', bar: 'bg-sky-400/60' } },
    active_projects: { label: 'Active Projects', icon: HiOutlineLightningBolt, accent: { bg: 'bg-lime-400/15', icon: 'text-lime-300', bar: 'bg-lime-400/60' } },
    delayed_projects: { label: 'Delayed Projects', icon: HiOutlineExclamationCircle, accent: { bg: 'bg-red-400/15', icon: 'text-red-300', bar: 'bg-red-400/60' }, subtitle: 'Past deadline' },
    my_open_tasks: { label: 'My Open Tasks', icon: HiOutlineClipboardList, accent: { bg: 'bg-violet-400/15', icon: 'text-violet-300', bar: 'bg-violet-400/60' }, subtitle: 'Assigned to me' },
    overdue_tasks: { label: 'Overdue Tasks', icon: HiOutlineClock, accent: { bg: 'bg-amber-400/15', icon: 'text-amber-300', bar: 'bg-amber-400/60' } },
    total_clients: { label: 'Clients', icon: HiOutlineOfficeBuilding, accent: { bg: 'bg-teal-400/15', icon: 'text-teal-300', bar: 'bg-teal-400/60' }, subtitle: 'Active' },
    total_revenue: { label: 'Contract Value', icon: HiOutlineCurrencyDollar, accent: { bg: 'bg-emerald-400/15', icon: 'text-emerald-300', bar: 'bg-emerald-400/60' }, format: 'currency', subtitle: 'Total budget' },
    receivables: { label: 'Receivables', icon: HiOutlineDocumentText, accent: { bg: 'bg-amber-400/15', icon: 'text-amber-300', bar: 'bg-amber-400/60' }, format: 'currency', subtitle: 'Unpaid invoices' },
    overdue_invoices: { label: 'Overdue Invoices', icon: HiOutlineExclamationCircle, accent: { bg: 'bg-red-400/15', icon: 'text-red-300', bar: 'bg-red-400/60' } },
    pending_expenses: { label: 'Pending Expenses', icon: HiOutlineCurrencyDollar, accent: { bg: 'bg-orange-400/15', icon: 'text-orange-300', bar: 'bg-orange-400/60' }, format: 'currency', subtitle: 'Awaiting approval' },
    total_staff: { label: 'Active Staff', icon: HiOutlineUserGroup, accent: { bg: 'bg-indigo-400/15', icon: 'text-indigo-300', bar: 'bg-indigo-400/60' } },
    pending_approvals: { label: 'Leave to Approve', icon: HiOutlineClipboardCheck, accent: { bg: 'bg-amber-400/15', icon: 'text-amber-300', bar: 'bg-amber-400/60' }, subtitle: 'Awaiting you' },
    training_pending: { label: 'Training Requests', icon: HiOutlineAcademicCap, accent: { bg: 'bg-sky-400/15', icon: 'text-sky-300', bar: 'bg-sky-400/60' }, subtitle: 'Pending' },
    open_incidents: { label: 'Open Incidents', icon: HiOutlineShieldExclamation, accent: { bg: 'bg-red-400/15', icon: 'text-red-300', bar: 'bg-red-400/60' }, subtitle: 'Safety' },
    open_hazards: { label: 'Open Hazards', icon: HiOutlineExclamationCircle, accent: { bg: 'bg-orange-400/15', icon: 'text-orange-300', bar: 'bg-orange-400/60' } },
};
const CARD_ORDER = [
    'total_projects', 'active_projects', 'delayed_projects', 'my_open_tasks', 'overdue_tasks', 'total_clients',
    'total_revenue', 'receivables', 'overdue_invoices', 'pending_expenses', 'total_staff',
    'pending_approvals', 'training_pending', 'open_incidents', 'open_hazards',
];

export default function Dashboard() {
    const { user, can } = useAuth();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        dashboardService.getData().then((res) => setData(res.data)).catch(() => {}).finally(() => setLoading(false));
    }, []);

    const statCards = useMemo(() => {
        const s = data?.stats || {};
        return CARD_ORDER.filter((k) => s[k] !== undefined).map((k) => ({ key: k, value: s[k], ...cardDefs[k] }));
    }, [data?.stats]);

    const doughnut = (items, palette) => !items?.length ? null : {
        data: { labels: items.map((i) => i.label), datasets: [{ data: items.map((i) => i.value), backgroundColor: items.map((i, idx) => (palette ? palette[idx % palette.length] : (STATUS_COLORS_MAP[i.label] || '#94a3b8'))), borderWidth: 0, hoverOffset: 4 }] },
        options: { ...chartDefaults, cutout: '68%', plugins: { ...chartDefaults.plugins, legend: { ...chartDefaults.plugins.legend, position: 'bottom' } } },
    };
    const projectStatusChart = useMemo(() => doughnut(data?.charts?.project_status), [data?.charts?.project_status]);
    const taskStatusChart = useMemo(() => doughnut(data?.charts?.task_status), [data?.charts?.task_status]);
    const leaveStatusChart = useMemo(() => doughnut(data?.charts?.leave_status), [data?.charts?.leave_status]);
    const expenseChart = useMemo(() => doughnut(data?.charts?.expense_category, CATEGORY_PALETTE), [data?.charts?.expense_category]);

    const budgetChart = useMemo(() => {
        const items = data?.charts?.budget_by_status || [];
        if (!items.length) return null;
        return {
            data: { labels: items.map((i) => i.label), datasets: [
                { label: 'Budget', data: items.map((i) => i.budget), backgroundColor: 'rgba(190, 242, 100, 0.85)', borderRadius: 6, borderSkipped: false, barPercentage: 0.6, categoryPercentage: 0.7 },
                { label: 'Spent', data: items.map((i) => i.spent), backgroundColor: 'rgba(16, 185, 129, 0.65)', borderRadius: 6, borderSkipped: false, barPercentage: 0.6, categoryPercentage: 0.7 },
            ] },
            options: { ...chartDefaults,
                scales: { x: { grid: { display: false }, ticks: { font: { size: 11, family: 'Inter, sans-serif' }, color: AXIS_SOFT } },
                    y: { grid: { color: GRID }, ticks: { font: { size: 11, family: 'Inter, sans-serif' }, color: AXIS_SOFT, callback: (v) => formatCurrency(v) } } },
                plugins: { ...chartDefaults.plugins, legend: { ...chartDefaults.plugins.legend, position: 'top' },
                    tooltip: { ...chartDefaults.plugins.tooltip, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatCurrency(ctx.raw)}` } } } },
        };
    }, [data?.charts?.budget_by_status]);

    const monthlyChart = useMemo(() => {
        const items = data?.charts?.monthly_projects || [];
        if (!items.length) return null;
        return {
            data: { labels: items.map((i) => i.label), datasets: [{ label: 'Projects Created', data: items.map((i) => i.value),
                borderColor: '#a3e635', backgroundColor: 'rgba(163, 230, 53, 0.12)', tension: 0.4, fill: true,
                pointBackgroundColor: '#a3e635', pointBorderColor: '#03140c', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6 }] },
            options: { ...chartDefaults,
                scales: { x: { grid: { display: false }, ticks: { font: { size: 10, family: 'Inter, sans-serif' }, color: AXIS_SOFT, maxRotation: 45 } },
                    y: { beginAtZero: true, grid: { color: GRID }, ticks: { font: { size: 11, family: 'Inter, sans-serif' }, color: AXIS_SOFT, stepSize: 1 } } },
                plugins: { ...chartDefaults.plugins, legend: { display: false } } },
        };
    }, [data?.charts?.monthly_projects]);

    const topProjectsChart = useMemo(() => {
        const items = data?.charts?.top_projects || [];
        if (!items.length) return null;
        return {
            data: { labels: items.map((i) => i.name.length > 20 ? i.name.slice(0, 20) + '...' : i.name), datasets: [
                { label: 'Budget', data: items.map((i) => i.budget), backgroundColor: 'rgba(190, 242, 100, 0.85)', borderRadius: 4, borderSkipped: false },
                { label: 'Spent', data: items.map((i) => i.spent), backgroundColor: 'rgba(16, 185, 129, 0.65)', borderRadius: 4, borderSkipped: false },
            ] },
            options: { ...chartDefaults, indexAxis: 'y',
                scales: { x: { grid: { color: GRID }, ticks: { font: { size: 11, family: 'Inter, sans-serif' }, color: AXIS_SOFT, callback: (v) => formatCurrency(v) } },
                    y: { grid: { display: false }, ticks: { font: { size: 11, family: 'Inter, sans-serif' }, color: AXIS } } },
                plugins: { ...chartDefaults.plugins, legend: { ...chartDefaults.plugins.legend, position: 'top' },
                    tooltip: { ...chartDefaults.plugins.tooltip, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatCurrency(ctx.raw)}` } } } },
        };
    }, [data?.charts?.top_projects]);

    if (loading) return <LoadingSpinner />;

    const charts = data?.charts || {};
    const lists = data?.lists || {};
    const myBalance = data?.my?.leave_balance || [];
    const myTasks = lists.my_tasks || [];
    const recentProjects = lists.recent_projects || [];
    const pendingApprovals = lists.pending_approvals || [];
    const recentActivity = lists.recent_activity || [];
    const upcoming = lists.upcoming || [];

    const hasProjectCharts = 'project_status' in charts;

    return (
        <div className="relative -m-4 min-h-[calc(100vh-4rem)] overflow-hidden bg-primary-900 p-4 lg:-m-6 lg:p-6">
            <div className="absolute inset-0 bg-gradient-to-br from-primary-900 via-[#0f4827] to-[#0c3c20]" />
            <Aurora />

            <div className="relative z-10 space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Dashboard</h1>
                        <p className="text-sm text-emerald-100/60">Welcome back, {user?.first_name}. Here is your live overview.</p>
                    </div>
                    <div className="flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-lime-200/80 backdrop-blur">
                        <HiOutlineTrendingUp className="h-3.5 w-3.5" /> Live overview
                    </div>
                </div>

                {/* Stat Cards */}
                {statCards.length > 0 && (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        {statCards.map((card) => <StatCard key={card.key} {...card} />)}
                    </div>
                )}

                {/* Project charts */}
                {hasProjectCharts && (
                    <>
                        <div className="grid gap-6 lg:grid-cols-3">
                            <ChartCard title="Project Status Distribution">
                                <div className="h-64">{projectStatusChart ? <Doughnut data={projectStatusChart.data} options={projectStatusChart.options} /> : <EmptyChart />}</div>
                            </ChartCard>
                            <ChartCard title="Budget vs Spent by Status" className="lg:col-span-2">
                                <div className="h-64">{budgetChart ? <Bar data={budgetChart.data} options={budgetChart.options} /> : <EmptyChart />}</div>
                            </ChartCard>
                        </div>
                        <div className="grid gap-6 lg:grid-cols-3">
                            <ChartCard title="Project Creation Trend (12 Months)" className="lg:col-span-2">
                                <div className="h-64">{monthlyChart ? <Line data={monthlyChart.data} options={monthlyChart.options} /> : <EmptyChart />}</div>
                            </ChartCard>
                            <ChartCard title="Task Status Breakdown">
                                <div className="h-64">{taskStatusChart ? <Doughnut data={taskStatusChart.data} options={taskStatusChart.options} /> : <EmptyChart />}</div>
                            </ChartCard>
                        </div>
                        {topProjectsChart && (
                            <ChartCard title="Top Projects by Budget">
                                <div className="h-64"><Bar data={topProjectsChart.data} options={topProjectsChart.options} /></div>
                            </ChartCard>
                        )}
                    </>
                )}

                {/* HR / Finance charts */}
                {('leave_status' in charts || 'expense_category' in charts || !hasProjectCharts) && (
                    <div className="grid gap-6 lg:grid-cols-3">
                        {!hasProjectCharts && (
                            <ChartCard title="My Task Breakdown">
                                <div className="h-64">{taskStatusChart ? <Doughnut data={taskStatusChart.data} options={taskStatusChart.options} /> : <EmptyChart />}</div>
                            </ChartCard>
                        )}
                        {'leave_status' in charts && (
                            <ChartCard title="Leave Requests by Status">
                                <div className="h-64">{leaveStatusChart ? <Doughnut data={leaveStatusChart.data} options={leaveStatusChart.options} /> : <EmptyChart />}</div>
                            </ChartCard>
                        )}
                        {'expense_category' in charts && (
                            <ChartCard title="Expenses by Category">
                                <div className="h-64">{expenseChart ? <Doughnut data={expenseChart.data} options={expenseChart.options} /> : <EmptyChart />}</div>
                            </ChartCard>
                        )}
                    </div>
                )}

                {/* Lists */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* My Tasks */}
                    <Panel title="My Tasks" icon={HiOutlineClipboardList} link={can('tasks.view') ? '/tasks' : null}>
                        {myTasks.length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-emerald-200/40">No pending tasks</p>
                        ) : myTasks.map((task) => (
                            <div key={task.id} className="flex items-center justify-between px-5 py-3 transition-colors hover:bg-white/5">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-white">{task.title}</p>
                                    <p className="text-xs text-emerald-200/40">{task.project?.name}{task.due_date && <span className="ml-2">Due {task.due_date}</span>}</p>
                                </div>
                                <div className="ml-4 flex items-center gap-2">
                                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${priorityColors[task.priority]}`}>{task.priority}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${statusBadge[task.status] || 'bg-white/10 text-white'}`}>{task.status.replace(/_/g, ' ')}</span>
                                </div>
                            </div>
                        ))}
                    </Panel>

                    {/* Pending leave approvals */}
                    {'pending_approvals' in lists && (
                        <Panel title="Leave Awaiting Approval" icon={HiOutlineClipboardCheck} link="/hr/leave/approvals" linkLabel="Review">
                            {pendingApprovals.length === 0 ? (
                                <p className="px-5 py-10 text-center text-sm text-emerald-200/40">Nothing awaiting you</p>
                            ) : pendingApprovals.map((l) => (
                                <div key={l.id} className="flex items-center justify-between px-5 py-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-white">{l.employee} · {l.type}</p>
                                        <p className="text-xs text-emerald-200/40">{l.start_date} → {l.end_date}</p>
                                    </div>
                                    <span className="ml-3 rounded-full bg-amber-400/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-200">{l.stage}</span>
                                </div>
                            ))}
                        </Panel>
                    )}

                    {/* Recent Projects */}
                    {recentProjects.length > 0 && (
                        <Panel title="Recent Projects" icon={HiOutlineBriefcase} link={can('projects.view') ? '/projects' : null}>
                            {recentProjects.map((project) => (
                                <Link key={project.id} to={`/projects/${project.id}`} className="flex items-center justify-between px-5 py-3 transition-colors hover:bg-white/5">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-white">{project.name}</p>
                                        <p className="text-xs text-emerald-200/40">{project.code}{project.client && <span className="ml-1">— {project.client}</span>}</p>
                                    </div>
                                    <div className="ml-4 flex items-center gap-3">
                                        <div className="w-20">
                                            <div className="mb-1 text-right text-[10px] font-medium text-emerald-200/40">{project.progress}%</div>
                                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                                                <div className="h-full rounded-full bg-lime-400 transition-all" style={{ width: `${project.progress}%` }} />
                                            </div>
                                        </div>
                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${statusBadge[project.status] || 'bg-white/10 text-white'}`}>{project.status.replace(/_/g, ' ')}</span>
                                    </div>
                                </Link>
                            ))}
                        </Panel>
                    )}

                    {/* My leave balance */}
                    {myBalance.length > 0 && (
                        <Panel title="My Leave Balance" icon={HiOutlineCalendar} link="/leave/my" linkLabel="My Leave">
                            {myBalance.map((b) => (
                                <div key={b.label} className="px-5 py-3">
                                    <div className="mb-1 flex items-center justify-between text-sm">
                                        <span className="text-white">{b.label}</span>
                                        <span className="font-semibold text-lime-300">{b.remaining}<span className="text-emerald-200/40"> / {b.entitled} days</span></span>
                                    </div>
                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                                        <div className="h-full rounded-full bg-lime-400" style={{ width: `${b.entitled > 0 ? Math.min(100, (b.remaining / b.entitled) * 100) : 0}%` }} />
                                    </div>
                                </div>
                            ))}
                        </Panel>
                    )}

                    {/* Upcoming events */}
                    {'upcoming' in lists && (
                        <Panel title="Upcoming (14 days)" icon={HiOutlineCalendar} link={can('calendar.view') ? '/hr/calendar' : null}>
                            {upcoming.length === 0 ? (
                                <p className="px-5 py-10 text-center text-sm text-emerald-200/40">Nothing scheduled</p>
                            ) : upcoming.map((e) => (
                                <div key={e.id} className="flex items-center justify-between px-5 py-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-white">{e.title}</p>
                                        <p className="text-xs text-emerald-200/40 capitalize">{e.type}{e.project && <span> · {e.project}</span>}</p>
                                    </div>
                                    <span className="ml-3 shrink-0 text-xs text-emerald-200/60">{e.when}</span>
                                </div>
                            ))}
                        </Panel>
                    )}

                    {/* Recent activity */}
                    {'recent_activity' in lists && (
                        <Panel title="Recent Activity" icon={HiOutlineSparkles}>
                            {recentActivity.length === 0 ? (
                                <p className="px-5 py-10 text-center text-sm text-emerald-200/40">No recent activity</p>
                            ) : recentActivity.map((a) => (
                                <div key={a.id} className="flex items-center justify-between px-5 py-3">
                                    <p className="min-w-0 flex-1 truncate text-sm text-emerald-50">
                                        <span className="font-medium text-white">{a.user || 'Someone'}</span> {a.action} {a.subject && <span className="text-emerald-200/50">{a.subject}</span>}
                                    </p>
                                    <span className="ml-3 shrink-0 text-xs text-emerald-200/40">{a.at}</span>
                                </div>
                            ))}
                        </Panel>
                    )}
                </div>
            </div>
        </div>
    );
}

function EmptyChart() {
    return (
        <div className="flex h-full items-center justify-center">
            <p className="text-sm text-emerald-200/40">No data available</p>
        </div>
    );
}
