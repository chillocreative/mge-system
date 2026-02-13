import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import financeService from '@/services/financeService';
import LoadingSpinner from '@/components/LoadingSpinner';
import {
    HiOutlineCurrencyDollar,
    HiOutlineDocumentText,
    HiOutlineExclamationCircle,
    HiOutlineClock,
    HiOutlineChartBar,
    HiOutlineReceiptTax,
} from 'react-icons/hi';

const statCards = [
    { key: 'total_receivables', label: 'Total Receivables', icon: HiOutlineCurrencyDollar, color: 'blue' },
    { key: 'total_revenue_this_month', label: 'Revenue This Month', icon: HiOutlineChartBar, color: 'green' },
    { key: 'overdue_invoices', label: 'Overdue Invoices', icon: HiOutlineExclamationCircle, color: 'red', isCurrency: false },
    { key: 'pending_expenses', label: 'Pending Expenses', icon: HiOutlineClock, color: 'yellow', isCurrency: false },
    { key: 'total_expenses_this_month', label: 'Expenses This Month', icon: HiOutlineReceiptTax, color: 'orange' },
    { key: 'total_payroll_pending', label: 'Payroll Pending', icon: HiOutlineDocumentText, color: 'purple' },
];

const colorMap = {
    blue: 'bg-blue-50 text-blue-600',
    green: 'bg-green-50 text-green-600',
    red: 'bg-red-50 text-red-600',
    yellow: 'bg-yellow-50 text-yellow-600',
    orange: 'bg-orange-50 text-orange-600',
    purple: 'bg-purple-50 text-purple-600',
};

function formatCurrency(val) {
    return 'PKR ' + Number(val || 0).toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

export default function FinanceOverview() {
    const { can } = useAuth();
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        (async () => {
            try {
                const res = await financeService.getOverview();
                setStats(res.data);
            } catch {
                setStats(null);
            } finally {
                setLoading(false);
            }
        })();
    }, []);

    if (loading) return <LoadingSpinner />;

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Finance Overview</h1>
                <p className="text-sm text-gray-500">Financial dashboard and quick stats</p>
            </div>

            {/* KPI Cards */}
            <div className="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {statCards.map((card) => (
                    <div key={card.key} className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                        <div className="flex items-center gap-3">
                            <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${colorMap[card.color]}`}>
                                <card.icon className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500">{card.label}</p>
                                <p className="text-xl font-bold text-gray-900">
                                    {card.isCurrency === false
                                        ? (stats?.[card.key] ?? 0)
                                        : formatCurrency(stats?.[card.key])}
                                </p>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Quick Actions */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    to="/finance/invoices"
                    className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:ring-primary-300"
                >
                    <HiOutlineDocumentText className="mb-2 h-8 w-8 text-primary-600" />
                    <h3 className="font-semibold text-gray-900">Invoices</h3>
                    <p className="text-xs text-gray-500">Create and manage invoices</p>
                </Link>

                <Link
                    to="/finance/expenses"
                    className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:ring-primary-300"
                >
                    <HiOutlineReceiptTax className="mb-2 h-8 w-8 text-orange-500" />
                    <h3 className="font-semibold text-gray-900">Expenses</h3>
                    <p className="text-xs text-gray-500">Track and approve expenses</p>
                </Link>

                <Link
                    to="/finance/budget-vs-actual"
                    className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:ring-primary-300"
                >
                    <HiOutlineChartBar className="mb-2 h-8 w-8 text-green-500" />
                    <h3 className="font-semibold text-gray-900">Budget vs Actual</h3>
                    <p className="text-xs text-gray-500">Project budget comparison</p>
                </Link>

                <Link
                    to="/finance/monthly-summary"
                    className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:ring-primary-300"
                >
                    <HiOutlineCurrencyDollar className="mb-2 h-8 w-8 text-purple-500" />
                    <h3 className="font-semibold text-gray-900">Monthly Summary</h3>
                    <p className="text-xs text-gray-500">Financial reports by month</p>
                </Link>
            </div>
        </div>
    );
}
