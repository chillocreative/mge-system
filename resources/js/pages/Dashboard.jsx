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
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Filler,
    Tooltip,
    Legend
);

// ── Color palette ──
const COLORS = {
    blue: { bg: 'rgba(37, 99, 235, 0.1)', border: '#2563eb', solid: '#2563eb' },
    sky: { bg: 'rgba(14, 165, 233, 0.1)', border: '#0ea5e9', solid: '#0ea5e9' },
    slate: { bg: 'rgba(100, 116, 139, 0.1)', border: '#64748b', solid: '#64748b' },
    emerald: { bg: 'rgba(16, 185, 129, 0.1)', border: '#10b981', solid: '#10b981' },
    amber: { bg: 'rgba(245, 158, 11, 0.1)', border: '#f59e0b', solid: '#f59e0b' },
    red: { bg: 'rgba(239, 68, 68, 0.1)', border: '#ef4444', solid: '#ef4444' },
    indigo: { bg: 'rgba(99, 102, 241, 0.1)', border: '#6366f1', solid: '#6366f1' },
    cyan: { bg: 'rgba(6, 182, 212, 0.1)', border: '#06b6d4', solid: '#06b6d4' },
};

const CHART_PALETTE = ['#2563eb', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#64748b', '#06b6d4'];

const STATUS_COLORS_MAP = {
    'Draft': '#94a3b8',
    'Planning': '#3b82f6',
    'In progress': '#f59e0b',
    'On hold': '#f97316',
    'Completed': '#10b981',
    'Cancelled': '#ef4444',
    'Pending': '#94a3b8',
    'In review': '#8b5cf6',
};

const priorityColors = {
    low: 'bg-slate-100 text-slate-600',
    medium: 'bg-blue-100 text-blue-700',
    high: 'bg-amber-100 text-amber-700',
    critical: 'bg-red-100 text-red-700',
};

const statusBadge = {
    draft: 'bg-slate-100 text-slate-600',
    planning: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
    on_hold: 'bg-orange-100 text-orange-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
    pending: 'bg-slate-100 text-slate-600',
    in_review: 'bg-violet-100 text-violet-700',
};

// ── Chart defaults ──
const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: { font: { size: 12, family: 'Inter, sans-serif' }, color: '#64748b', padding: 16, usePointStyle: true, pointStyleWidth: 8 },
        },
        tooltip: {
            backgroundColor: '#1e293b',
            titleFont: { size: 13, family: 'Inter, sans-serif' },
            bodyFont: { size: 12, family: 'Inter, sans-serif' },
            padding: 12,
            cornerRadius: 8,
            displayColors: true,
            boxPadding: 4,
        },
    },
};

function formatCurrency(value) {
    if (value >= 1_000_000) return `$${(value / 1_000_000).toFixed(1)}M`;
    if (value >= 1_000) return `$${(value / 1_000).toFixed(0)}K`;
    return `$${Number(value).toLocaleString()}`;
}

// ── Stat card component ──
function StatCard({ label, value, icon: Icon, color, subtitle, format }) {
    const displayValue = format === 'currency' ? formatCurrency(value ?? 0) : (value ?? 0);
    return (
        <div className="group relative overflow-hidden rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200/60 transition-all hover:shadow-md hover:ring-slate-300">
            <div className="flex items-start justify-between">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-slate-500">{label}</p>
                    <p className={`mt-2 text-2xl font-bold tracking-tight ${color?.text || 'text-slate-900'}`}>
                        {displayValue}
                    </p>
                    {subtitle && (
                        <p className="mt-1 text-xs text-slate-400">{subtitle}</p>
                    )}
                </div>
                <div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-lg ${color?.bg || 'bg-slate-50'}`}>
                    <Icon className={`h-5 w-5 ${color?.icon || 'text-slate-500'}`} />
                </div>
            </div>
            <div className={`absolute bottom-0 left-0 h-1 w-full ${color?.bar || 'bg-slate-200'}`} />
        </div>
    );
}

// ── Chart card wrapper ──
function ChartCard({ title, children, className = '' }) {
    return (
        <div className={`rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200/60 ${className}`}>
            <h3 className="mb-4 text-sm font-semibold text-slate-700">{title}</h3>
            {children}
        </div>
    );
}

export default function Dashboard() {
    const { user, can } = useAuth();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        dashboardService.getData()
            .then((res) => setData(res.data))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    // ── Stat card definitions ──
    const statCards = useMemo(() => {
        if (!data?.stats) return [];
        const s = data.stats;
        const cards = [
            {
                label: 'Total Projects', value: s.total_projects,
                icon: HiOutlineBriefcase,
                color: { bg: 'bg-blue-50', icon: 'text-blue-600', text: 'text-blue-700', bar: 'bg-blue-500' },
            },
            {
                label: 'Active Projects', value: s.active_projects,
                icon: HiOutlineLightningBolt,
                color: { bg: 'bg-sky-50', icon: 'text-sky-600', text: 'text-sky-700', bar: 'bg-sky-500' },
            },
            {
                label: 'Delayed Projects', value: s.delayed_projects,
                icon: HiOutlineExclamationCircle,
                color: { bg: 'bg-red-50', icon: 'text-red-500', text: 'text-red-600', bar: 'bg-red-500' },
                subtitle: 'Past deadline',
            },
        ];

        if (s.total_revenue !== undefined) {
            cards.push({
                label: 'Total Revenue', value: s.total_revenue, format: 'currency',
                icon: HiOutlineCurrencyDollar,
                color: { bg: 'bg-emerald-50', icon: 'text-emerald-600', text: 'text-emerald-700', bar: 'bg-emerald-500' },
                subtitle: 'Contract value',
            });
        }

        if (s.pending_invoices !== undefined) {
            cards.push({
                label: 'Pending Invoices', value: s.pending_invoices, format: 'currency',
                icon: HiOutlineDocumentText,
                color: { bg: 'bg-amber-50', icon: 'text-amber-600', text: 'text-amber-700', bar: 'bg-amber-500' },
                subtitle: 'Unbilled amount',
            });
        }

        if (s.staff_present_today !== undefined) {
            cards.push({
                label: 'Staff Today', value: `${s.staff_present_today}/${s.total_staff}`,
                icon: HiOutlineUserGroup,
                color: { bg: 'bg-indigo-50', icon: 'text-indigo-600', text: 'text-indigo-700', bar: 'bg-indigo-500' },
                subtitle: 'Active personnel',
            });
        }

        cards.push({
            label: 'Safety Incidents', value: s.safety_incidents,
            icon: HiOutlineShieldExclamation,
            color: {
                bg: s.safety_incidents > 0 ? 'bg-red-50' : 'bg-emerald-50',
                icon: s.safety_incidents > 0 ? 'text-red-500' : 'text-emerald-600',
                text: s.safety_incidents > 0 ? 'text-red-600' : 'text-emerald-700',
                bar: s.safety_incidents > 0 ? 'bg-red-500' : 'bg-emerald-500',
            },
            subtitle: 'This month',
        });

        return cards;
    }, [data?.stats]);

    // ── Project status doughnut chart ──
    const projectStatusChart = useMemo(() => {
        const items = data?.charts?.project_status || [];
        if (!items.length) return null;
        return {
            data: {
                labels: items.map((i) => i.label),
                datasets: [{
                    data: items.map((i) => i.value),
                    backgroundColor: items.map((i) => STATUS_COLORS_MAP[i.label] || '#94a3b8'),
                    borderWidth: 0,
                    hoverOffset: 4,
                }],
            },
            options: {
                ...chartDefaults,
                cutout: '68%',
                plugins: {
                    ...chartDefaults.plugins,
                    legend: { ...chartDefaults.plugins.legend, position: 'bottom' },
                },
            },
        };
    }, [data?.charts?.project_status]);

    // ── Task status doughnut chart ──
    const taskStatusChart = useMemo(() => {
        const items = data?.charts?.task_status || [];
        if (!items.length) return null;
        return {
            data: {
                labels: items.map((i) => i.label),
                datasets: [{
                    data: items.map((i) => i.value),
                    backgroundColor: items.map((i) => STATUS_COLORS_MAP[i.label] || '#94a3b8'),
                    borderWidth: 0,
                    hoverOffset: 4,
                }],
            },
            options: {
                ...chartDefaults,
                cutout: '68%',
                plugins: {
                    ...chartDefaults.plugins,
                    legend: { ...chartDefaults.plugins.legend, position: 'bottom' },
                },
            },
        };
    }, [data?.charts?.task_status]);

    // ── Budget vs Spent bar chart ──
    const budgetChart = useMemo(() => {
        const items = data?.charts?.budget_by_status || [];
        if (!items.length) return null;
        return {
            data: {
                labels: items.map((i) => i.label),
                datasets: [
                    {
                        label: 'Budget',
                        data: items.map((i) => i.budget),
                        backgroundColor: 'rgba(37, 99, 235, 0.8)',
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.6,
                        categoryPercentage: 0.7,
                    },
                    {
                        label: 'Spent',
                        data: items.map((i) => i.spent),
                        backgroundColor: 'rgba(14, 165, 233, 0.6)',
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.6,
                        categoryPercentage: 0.7,
                    },
                ],
            },
            options: {
                ...chartDefaults,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11, family: 'Inter, sans-serif' }, color: '#94a3b8' },
                    },
                    y: {
                        grid: { color: 'rgba(148, 163, 184, 0.1)' },
                        ticks: {
                            font: { size: 11, family: 'Inter, sans-serif' }, color: '#94a3b8',
                            callback: (v) => formatCurrency(v),
                        },
                    },
                },
                plugins: {
                    ...chartDefaults.plugins,
                    legend: { ...chartDefaults.plugins.legend, position: 'top' },
                    tooltip: {
                        ...chartDefaults.plugins.tooltip,
                        callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatCurrency(ctx.raw)}` },
                    },
                },
            },
        };
    }, [data?.charts?.budget_by_status]);

    // ── Monthly projects line chart ──
    const monthlyChart = useMemo(() => {
        const items = data?.charts?.monthly_projects || [];
        if (!items.length) return null;
        return {
            data: {
                labels: items.map((i) => i.label),
                datasets: [{
                    label: 'Projects Created',
                    data: items.map((i) => i.value),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }],
            },
            options: {
                ...chartDefaults,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10, family: 'Inter, sans-serif' }, color: '#94a3b8', maxRotation: 45 },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.1)' },
                        ticks: {
                            font: { size: 11, family: 'Inter, sans-serif' }, color: '#94a3b8',
                            stepSize: 1,
                        },
                    },
                },
                plugins: {
                    ...chartDefaults.plugins,
                    legend: { display: false },
                },
            },
        };
    }, [data?.charts?.monthly_projects]);

    // ── Top projects horizontal bar ──
    const topProjectsChart = useMemo(() => {
        const items = data?.charts?.top_projects || [];
        if (!items.length) return null;
        return {
            data: {
                labels: items.map((i) => i.name.length > 20 ? i.name.slice(0, 20) + '...' : i.name),
                datasets: [
                    {
                        label: 'Budget',
                        data: items.map((i) => i.budget),
                        backgroundColor: 'rgba(37, 99, 235, 0.8)',
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'Spent',
                        data: items.map((i) => i.spent),
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                ],
            },
            options: {
                ...chartDefaults,
                indexAxis: 'y',
                scales: {
                    x: {
                        grid: { color: 'rgba(148, 163, 184, 0.1)' },
                        ticks: {
                            font: { size: 11, family: 'Inter, sans-serif' }, color: '#94a3b8',
                            callback: (v) => formatCurrency(v),
                        },
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 11, family: 'Inter, sans-serif' }, color: '#64748b' },
                    },
                },
                plugins: {
                    ...chartDefaults.plugins,
                    legend: { ...chartDefaults.plugins.legend, position: 'top' },
                    tooltip: {
                        ...chartDefaults.plugins.tooltip,
                        callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatCurrency(ctx.raw)}` },
                    },
                },
            },
        };
    }, [data?.charts?.top_projects]);

    if (loading) return <LoadingSpinner />;

    const myTasks = data?.my_tasks || [];
    const recentProjects = data?.recent_projects || [];

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900">
                        Dashboard
                    </h1>
                    <p className="text-sm text-slate-500">
                        Welcome back, {user?.first_name}. Here is your project overview.
                    </p>
                </div>
                <div className="flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-500">
                    <HiOutlineTrendingUp className="h-3.5 w-3.5" />
                    Live overview
                </div>
            </div>

            {/* Stat Cards */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
                {statCards.map((card) => (
                    <StatCard key={card.label} {...card} />
                ))}
            </div>

            {/* Charts Row 1 — Project Status + Budget vs Spent */}
            <div className="grid gap-6 lg:grid-cols-3">
                <ChartCard title="Project Status Distribution">
                    <div className="h-64">
                        {projectStatusChart ? (
                            <Doughnut data={projectStatusChart.data} options={projectStatusChart.options} />
                        ) : (
                            <EmptyChart />
                        )}
                    </div>
                </ChartCard>

                <ChartCard title="Budget vs Spent by Status" className="lg:col-span-2">
                    <div className="h-64">
                        {budgetChart ? (
                            <Bar data={budgetChart.data} options={budgetChart.options} />
                        ) : (
                            <EmptyChart />
                        )}
                    </div>
                </ChartCard>
            </div>

            {/* Charts Row 2 — Monthly Trend + Task Status */}
            <div className="grid gap-6 lg:grid-cols-3">
                <ChartCard title="Project Creation Trend (12 Months)" className="lg:col-span-2">
                    <div className="h-64">
                        {monthlyChart ? (
                            <Line data={monthlyChart.data} options={monthlyChart.options} />
                        ) : (
                            <EmptyChart />
                        )}
                    </div>
                </ChartCard>

                <ChartCard title="Task Status Breakdown">
                    <div className="h-64">
                        {taskStatusChart ? (
                            <Doughnut data={taskStatusChart.data} options={taskStatusChart.options} />
                        ) : (
                            <EmptyChart />
                        )}
                    </div>
                </ChartCard>
            </div>

            {/* Top Projects by Budget */}
            {topProjectsChart && (
                <ChartCard title="Top Projects by Budget">
                    <div className="h-64">
                        <Bar data={topProjectsChart.data} options={topProjectsChart.options} />
                    </div>
                </ChartCard>
            )}

            {/* Tables Row — My Tasks + Recent Projects */}
            <div className="grid gap-6 lg:grid-cols-2">
                {/* My Tasks */}
                <div className="rounded-xl bg-white shadow-sm ring-1 ring-slate-200/60">
                    <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div className="flex items-center gap-2">
                            <HiOutlineClipboardList className="h-5 w-5 text-slate-400" />
                            <h3 className="text-sm font-semibold text-slate-700">My Tasks</h3>
                        </div>
                        {can('tasks.view') && (
                            <Link
                                to="/tasks"
                                className="flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-500"
                            >
                                View all <HiOutlineArrowSmRight className="h-3.5 w-3.5" />
                            </Link>
                        )}
                    </div>
                    <div className="divide-y divide-slate-100">
                        {myTasks.length === 0 ? (
                            <p className="px-5 py-10 text-center text-sm text-slate-400">
                                No pending tasks
                            </p>
                        ) : (
                            myTasks.map((task) => (
                                <div key={task.id} className="flex items-center justify-between px-5 py-3 transition-colors hover:bg-slate-50/50">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-slate-800">
                                            {task.title}
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            {task.project?.name}
                                            {task.due_date && (
                                                <span className="ml-2 text-slate-400">Due {task.due_date}</span>
                                            )}
                                        </p>
                                    </div>
                                    <div className="ml-4 flex items-center gap-2">
                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${priorityColors[task.priority]}`}>
                                            {task.priority}
                                        </span>
                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${statusBadge[task.status] || 'bg-slate-100 text-slate-600'}`}>
                                            {task.status.replace(/_/g, ' ')}
                                        </span>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                {/* Recent Projects */}
                {recentProjects.length > 0 && (
                    <div className="rounded-xl bg-white shadow-sm ring-1 ring-slate-200/60">
                        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div className="flex items-center gap-2">
                                <HiOutlineBriefcase className="h-5 w-5 text-slate-400" />
                                <h3 className="text-sm font-semibold text-slate-700">Recent Projects</h3>
                            </div>
                            {can('projects.view') && (
                                <Link
                                    to="/projects"
                                    className="flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-500"
                                >
                                    View all <HiOutlineArrowSmRight className="h-3.5 w-3.5" />
                                </Link>
                            )}
                        </div>
                        <div className="divide-y divide-slate-100">
                            {recentProjects.map((project) => (
                                <Link
                                    key={project.id}
                                    to={`/projects/${project.id}`}
                                    className="flex items-center justify-between px-5 py-3 transition-colors hover:bg-slate-50/50"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-slate-800">
                                            {project.name}
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            {project.code}
                                            {project.client && <span className="ml-1">— {project.client}</span>}
                                        </p>
                                    </div>
                                    <div className="ml-4 flex items-center gap-3">
                                        <div className="w-20">
                                            <div className="mb-1 text-right text-[10px] font-medium text-slate-400">
                                                {project.progress}%
                                            </div>
                                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                                <div
                                                    className="h-full rounded-full bg-blue-500 transition-all"
                                                    style={{ width: `${project.progress}%` }}
                                                />
                                            </div>
                                        </div>
                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${statusBadge[project.status] || 'bg-slate-100 text-slate-600'}`}>
                                            {project.status.replace(/_/g, ' ')}
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

function EmptyChart() {
    return (
        <div className="flex h-full items-center justify-center">
            <p className="text-sm text-slate-400">No data available</p>
        </div>
    );
}
