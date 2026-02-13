import { useState, useEffect } from 'react';
import financeService from '@/services/financeService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { HiOutlineChartBar } from 'react-icons/hi';

function formatCurrency(val) {
    return 'PKR ' + Number(val || 0).toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

const months = [
    { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },
    { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },
    { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },
    { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },
];

export default function MonthlySummary() {
    const now = new Date();
    const [year, setYear] = useState(now.getFullYear());
    const [month, setMonth] = useState(now.getMonth() + 1);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);

    const fetchSummary = async () => {
        setLoading(true);
        try {
            const res = await financeService.getMonthlySummary({ year, month });
            setData(res.data);
        } catch {
            setData(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchSummary(); }, [year, month]);

    const summary = data?.summary || {};
    const invoices = data?.invoices || {};
    const expenses = data?.expenses || {};
    const payroll = data?.payroll || {};
    const expenseByCategory = data?.expense_by_category || [];

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Monthly Summary</h1>
                    <p className="text-sm text-gray-500">Detailed financial report by month</p>
                </div>
                <div className="flex items-center gap-2">
                    <select
                        value={month}
                        onChange={(e) => setMonth(Number(e.target.value))}
                        className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    >
                        {months.map((m) => (
                            <option key={m.value} value={m.value}>{m.label}</option>
                        ))}
                    </select>
                    <select
                        value={year}
                        onChange={(e) => setYear(Number(e.target.value))}
                        className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                    >
                        {Array.from({ length: 5 }, (_, i) => now.getFullYear() - 2 + i).map((y) => (
                            <option key={y} value={y}>{y}</option>
                        ))}
                    </select>
                </div>
            </div>

            {loading ? (
                <LoadingSpinner />
            ) : !data ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineChartBar className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No data available for this period</p>
                </div>
            ) : (
                <div className="space-y-6">
                    {/* Profit/Loss Summary */}
                    <div className="grid gap-4 sm:grid-cols-4">
                        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                            <p className="text-xs font-medium text-gray-500">Total Income</p>
                            <p className="text-xl font-bold text-green-600">{formatCurrency(summary.total_income)}</p>
                        </div>
                        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                            <p className="text-xs font-medium text-gray-500">Total Expenses</p>
                            <p className="text-xl font-bold text-red-600">{formatCurrency(summary.total_expenses)}</p>
                        </div>
                        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                            <p className="text-xs font-medium text-gray-500">Net Profit</p>
                            <p className={`text-xl font-bold ${summary.net_profit >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                {formatCurrency(summary.net_profit)}
                            </p>
                        </div>
                        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                            <p className="text-xs font-medium text-gray-500">Profit Margin</p>
                            <p className={`text-xl font-bold ${summary.profit_margin >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                {summary.profit_margin}%
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Invoices */}
                        <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                            <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Invoices</h2>
                            <div className="space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Total Invoices</span>
                                    <span className="text-sm font-medium text-gray-900">{invoices.total_invoices}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Total Invoiced</span>
                                    <span className="text-sm font-medium text-gray-900">{formatCurrency(invoices.total_invoiced)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Collected</span>
                                    <span className="text-sm font-medium text-green-600">{formatCurrency(invoices.total_collected)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Outstanding</span>
                                    <span className="text-sm font-medium text-red-600">{formatCurrency(invoices.total_outstanding)}</span>
                                </div>
                                <div className="flex justify-between border-t pt-2">
                                    <span className="text-sm text-gray-600">Paid / Unpaid / Overdue</span>
                                    <span className="text-sm text-gray-700">
                                        <span className="text-green-600">{invoices.paid_count}</span>
                                        {' / '}
                                        <span className="text-yellow-600">{invoices.unpaid_count}</span>
                                        {' / '}
                                        <span className="text-red-600">{invoices.overdue_count}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Expenses */}
                        <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                            <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Expenses</h2>
                            <div className="space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Total Expenses</span>
                                    <span className="text-sm font-medium text-gray-900">{expenses.total_expenses}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Total Amount</span>
                                    <span className="text-sm font-medium text-gray-900">{formatCurrency(expenses.total_amount)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Approved</span>
                                    <span className="text-sm font-medium text-green-600">{formatCurrency(expenses.approved_amount)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Pending</span>
                                    <span className="text-sm font-medium text-yellow-600">{formatCurrency(expenses.pending_amount)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Payroll */}
                        <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                            <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Payroll</h2>
                            <div className="space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Records</span>
                                    <span className="text-sm font-medium text-gray-900">{payroll.total_records}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Total Payroll</span>
                                    <span className="text-sm font-medium text-gray-900">{formatCurrency(payroll.total_payroll)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Overtime Pay</span>
                                    <span className="text-sm font-medium text-gray-700">{formatCurrency(payroll.total_overtime)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-600">Deductions</span>
                                    <span className="text-sm font-medium text-red-600">{formatCurrency(payroll.total_deductions)}</span>
                                </div>
                                <div className="flex justify-between border-t pt-2">
                                    <span className="text-sm text-gray-600">Paid / Unpaid</span>
                                    <span className="text-sm text-gray-700">
                                        <span className="text-green-600">{payroll.paid_count}</span>
                                        {' / '}
                                        <span className="text-yellow-600">{payroll.unpaid_count}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Expense Breakdown */}
                        <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                            <h2 className="mb-4 text-sm font-semibold uppercase text-gray-500">Expense Breakdown</h2>
                            {expenseByCategory.length === 0 ? (
                                <p className="text-sm text-gray-500">No approved expenses this month</p>
                            ) : (
                                <div className="space-y-3">
                                    {expenseByCategory.map((cat) => {
                                        const total = expenseByCategory.reduce((s, c) => s + Number(c.total), 0);
                                        const pct = total > 0 ? (Number(cat.total) / total) * 100 : 0;
                                        return (
                                            <div key={cat.category}>
                                                <div className="mb-1 flex justify-between text-sm">
                                                    <span className="text-gray-600 capitalize">{cat.category}</span>
                                                    <span className="font-medium text-gray-900">{formatCurrency(cat.total)} ({cat.count})</span>
                                                </div>
                                                <div className="h-1.5 w-full rounded-full bg-gray-100">
                                                    <div className="h-1.5 rounded-full bg-primary-500" style={{ width: `${pct}%` }} />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
