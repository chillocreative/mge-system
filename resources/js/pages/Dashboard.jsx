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
    'Completed': '#34d399', 'Cancelled': '#f87171', 'Pending': '#94a3b8', 'In review': '#a78bfa',
};

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
    return `RM ${Number(value).toLocaleString('en-MY')}`;
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
        <div className="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.07] p-5 shadow-lg shadow-black/20 backdrop-blur-xl transition-all hover:border-white/20 hover:bg-white/[0.1]">
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
        <div className={`rounded-2xl border border-white/10 bg-white/[0.07] p-5 shadow-lg shadow-black/20 backdrop-blur-xl ${className}`}>
            <h3 className="mb-4 text-sm font-semibold text-emerald-50">{title}</h3>
            {children}
        </div>
    );
}

export default function Dashboard() {
    const { user, can } = useAuth();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        dashboardService.getData().then((res) => setData(res.data)).catch(() => {}).finally(() => setLoading(false));
    }, []);

    const statCards = useMemo(() => {
        if (!data?.stats) return [];
        const s = data.stats;
        const cards = [
            { label: 'Total Projects', value: s.total_projects, icon: HiOutlineBriefcase, accent: { bg: 'bg-sky-400/15', icon: 'text-sky-300', bar: 'bg-sky-400/60' } },
            { label: 'Active Projects', value: s.active_projects, icon: HiOutlineLightningBolt, accent: { bg: 'bg-lime-400/15', icon: 'text-lime-300', bar: 'bg-lime-400/60' } },
            { label: 'Delayed Projects', value: s.delayed_projects, icon: HiOutlineExclamationCircle, accent: { bg: 'bg-red-400/15', icon: 'text-red-300', bar: 'bg-red-400/60' }, subtitle: 'Past deadline' },
        ];
        if (s.total_revenue !== undefined) cards.push({ label: 'Total Revenue', value: s.total_revenue, format: 'currency', icon: HiOutlineCurrencyDollar, accent: { bg: 'bg-emerald-400/15', icon: 'text-emerald-300', bar: 'bg-emerald-400/60' }, subtitle: 'Contract value' });
        if (s.pending_invoices !== undefined) cards.push({ label: 'Pending Invoices', value: s.pending_invoices, format: 'currency', icon: HiOutlineDocumentText, accent: { bg: 'bg-amber-400/15', icon: 'text-amber-300', bar: 'bg-amber-400/60' }, subtitle: 'Unbilled amount' });
        if (s.staff_present_today !== undefined) cards.push({ label: 'Staff Today', value: `${s.staff_present_today}/${s.total_staff}`, icon: HiOutlineUserGroup, accent: { bg: 'bg-indigo-400/15', icon: 'text-indigo-300', bar: 'bg-indigo-400/60' }, subtitle: 'Active personnel' });
        cards.push({
            label: 'Safety Incidents', value: s.safety_incidents, icon: HiOutlineShieldExclamation,
            accent: s.safety_incidents > 0 ? { bg: 'bg-red-400/15', icon: 'text-red-300', bar: 'bg-red-400/60' } : { bg: 'bg-emerald-400/15', icon: 'text-emerald-300', bar: 'bg-emerald-400/60' },
            subtitle: 'This month',
        });
        return cards;
    }, [data?.stats]);

    const doughnut = (items) => !items?.length ? null : {
        data: { labels: items.map((i) => i.label), datasets: [{ data: items.map((i) => i.value), backgroundColor: items.map((i) => STATUS_COLORS_MAP[i.label] || '#94a3b8'), borderWidth: 0, hoverOffset: 4 }] },
        options: { ...chartDefaults, cutout: '68%', plugins: { ...chartDefaults.plugins, legend: { ...chartDefaults.plugins.legend, position: 'bottom' } } },
    };
    const projectStatusChart = useMemo(() => doughnut(data?.charts?.project_status), [data?.charts?.project_status]);
    const taskStatusChart = useMemo(() => doughnut(data?.charts?.task_status), [data?.charts?.task_status]);

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

    const myTasks = data?.my_tasks || [];
    const recentProjects = data?.recent_projects || [];

    return (
        <div className="relative -m-4 min-h-[calc(100vh-4rem)] overflow-hidden bg-[#03140c] p-4 lg:-m-6 lg:p-6">
            <div className="absolute inset-0 bg-gradient-to-br from-[#03130b] via-[#052016] to-[#03241a]" />
            <Aurora />

            <div className="relative z-10 space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Dashboard</h1>
                        <p className="text-sm text-emerald-100/60">Welcome back, {user?.first_name}. Here is your project overview.</p>
                    </div>
                    <div className="flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-lime-200/80 backdrop-blur">
                        <HiOutlineTrendingUp className="h-3.5 w-3.5" /> Live overview
                    </div>
                </div>

                {/* Stat Cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
                    {statCards.map((card) => <StatCard key={card.label} {...card} />)}
                </div>

                {/* Charts Row 1 */}
                <div className="grid gap-6 lg:grid-cols-3">
                    <ChartCard title="Project Status Distribution">
                        <div className="h-64">{projectStatusChart ? <Doughnut data={projectStatusChart.data} options={projectStatusChart.options} /> : <EmptyChart />}</div>
                    </ChartCard>
                    <ChartCard title="Budget vs Spent by Status" className="lg:col-span-2">
                        <div className="h-64">{budgetChart ? <Bar data={budgetChart.data} options={budgetChart.options} /> : <EmptyChart />}</div>
                    </ChartCard>
                </div>

                {/* Charts Row 2 */}
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

                {/* Tables Row */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* My Tasks */}
                    <div className="rounded-2xl border border-white/10 bg-white/[0.07] shadow-lg shadow-black/20 backdrop-blur-xl">
                        <div className="flex items-center justify-between border-b border-white/10 px-5 py-4">
                            <div className="flex items-center gap-2">
                                <HiOutlineClipboardList className="h-5 w-5 text-emerald-200/50" />
                                <h3 className="text-sm font-semibold text-emerald-50">My Tasks</h3>
                            </div>
                            {can('tasks.view') && (
                                <Link to="/tasks" className="flex items-center gap-1 text-xs font-medium text-lime-300 hover:text-lime-200">
                                    View all <HiOutlineArrowSmRight className="h-3.5 w-3.5" />
                                </Link>
                            )}
                        </div>
                        <div className="divide-y divide-white/5">
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
                        </div>
                    </div>

                    {/* Recent Projects */}
                    {recentProjects.length > 0 && (
                        <div className="rounded-2xl border border-white/10 bg-white/[0.07] shadow-lg shadow-black/20 backdrop-blur-xl">
                            <div className="flex items-center justify-between border-b border-white/10 px-5 py-4">
                                <div className="flex items-center gap-2">
                                    <HiOutlineBriefcase className="h-5 w-5 text-emerald-200/50" />
                                    <h3 className="text-sm font-semibold text-emerald-50">Recent Projects</h3>
                                </div>
                                {can('projects.view') && (
                                    <Link to="/projects" className="flex items-center gap-1 text-xs font-medium text-lime-300 hover:text-lime-200">
                                        View all <HiOutlineArrowSmRight className="h-3.5 w-3.5" />
                                    </Link>
                                )}
                            </div>
                            <div className="divide-y divide-white/5">
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
                            </div>
                        </div>
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
