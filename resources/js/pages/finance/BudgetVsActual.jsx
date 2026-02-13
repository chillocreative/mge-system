import { useState, useEffect } from 'react';
import financeService from '@/services/financeService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { HiOutlineChartBar } from 'react-icons/hi';

function formatCurrency(val) {
    return 'PKR ' + Number(val || 0).toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

export default function BudgetVsActual() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        (async () => {
            try {
                const res = await financeService.getBudgetVsActual();
                setData(res.data);
            } catch {
                setData(null);
            } finally {
                setLoading(false);
            }
        })();
    }, []);

    if (loading) return <LoadingSpinner />;

    const projects = data?.projects || [];
    const totals = data?.totals || {};

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Budget vs Actual</h1>
                <p className="text-sm text-gray-500">Compare project budgets against actual spending</p>
            </div>

            {/* Summary Cards */}
            <div className="mb-6 grid gap-4 sm:grid-cols-4">
                <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p className="text-xs font-medium text-gray-500">Total Budget</p>
                    <p className="text-xl font-bold text-gray-900">{formatCurrency(totals.total_budget)}</p>
                </div>
                <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p className="text-xs font-medium text-gray-500">Total Spent</p>
                    <p className="text-xl font-bold text-gray-900">{formatCurrency(totals.total_spent)}</p>
                </div>
                <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p className="text-xs font-medium text-gray-500">Total Revenue</p>
                    <p className="text-xl font-bold text-green-600">{formatCurrency(totals.total_revenue)}</p>
                </div>
                <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p className="text-xs font-medium text-gray-500">Total Variance</p>
                    <p className={`text-xl font-bold ${totals.total_variance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                        {formatCurrency(totals.total_variance)}
                    </p>
                </div>
            </div>

            {projects.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineChartBar className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No projects with budgets found</p>
                </div>
            ) : (
                <div className="space-y-4">
                    {projects.map((p) => {
                        const pct = Math.min(p.utilization_percent, 100);
                        const barColor = p.over_budget ? 'bg-red-500' : pct > 80 ? 'bg-yellow-500' : 'bg-green-500';
                        return (
                            <div key={p.project_id} className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                                <div className="mb-3 flex items-center justify-between">
                                    <div>
                                        <h3 className="font-semibold text-gray-900">{p.project_name}</h3>
                                        <p className="text-xs text-gray-500">{p.project_code} &middot; {p.status}</p>
                                    </div>
                                    <div className="text-right">
                                        {p.over_budget ? (
                                            <span className="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">Over Budget</span>
                                        ) : (
                                            <span className="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Within Budget</span>
                                        )}
                                    </div>
                                </div>

                                {/* Progress Bar */}
                                <div className="mb-3">
                                    <div className="mb-1 flex justify-between text-xs text-gray-500">
                                        <span>{p.utilization_percent}% utilized</span>
                                        <span>{formatCurrency(p.actual_spent)} / {formatCurrency(p.budget)}</span>
                                    </div>
                                    <div className="h-2.5 w-full rounded-full bg-gray-100">
                                        <div
                                            className={`h-2.5 rounded-full ${barColor} transition-all`}
                                            style={{ width: `${Math.min(pct, 100)}%` }}
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-3 gap-4 border-t pt-3">
                                    <div>
                                        <p className="text-xs text-gray-500">Budget</p>
                                        <p className="text-sm font-medium text-gray-900">{formatCurrency(p.budget)}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-gray-500">Revenue</p>
                                        <p className="text-sm font-medium text-green-600">{formatCurrency(p.invoiced_revenue)}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-gray-500">Variance</p>
                                        <p className={`text-sm font-medium ${p.variance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                            {formatCurrency(p.variance)}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
