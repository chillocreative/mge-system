import { useState, useEffect } from 'react';
import apiClient from '@/services/apiClient';
import leaveService from '@/services/leaveService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { HiOutlineScale } from 'react-icons/hi';

export default function LeaveBalance() {
    const currentYear = new Date().getFullYear();
    const [employees, setEmployees] = useState([]);
    const [employeeId, setEmployeeId] = useState('');
    const [year, setYear] = useState(currentYear);
    const [balances, setBalances] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        apiClient.get('/employees', { params: { per_page: 100 } })
            .then((r) => setEmployees(r.data?.data?.data || r.data?.data || []))
            .catch(() => {});
    }, []);

    useEffect(() => {
        if (!employeeId) {
            setBalances([]);
            return;
        }
        setLoading(true);
        leaveService.balance({ employee_id: employeeId, year })
            .then((r) => setBalances(r.data || []))
            .catch(() => setBalances([]))
            .finally(() => setLoading(false));
    }, [employeeId, year]);

    const years = Array.from({ length: 5 }, (_, i) => currentYear - i);

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Leave Balances</h1>
                <p className="text-sm text-gray-500">View leave entitlement and usage per employee</p>
            </div>

            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <select
                    value={employeeId}
                    onChange={(e) => setEmployeeId(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    <option value="">Select employee</option>
                    {employees.map((emp) => (
                        <option key={emp.id} value={emp.id}>{emp.full_name || `${emp.first_name} ${emp.last_name}`}</option>
                    ))}
                </select>
                <select
                    value={year}
                    onChange={(e) => setYear(Number(e.target.value))}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
                    {years.map((y) => (
                        <option key={y} value={y}>{y}</option>
                    ))}
                </select>
            </div>

            {!employeeId ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineScale className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">Select an employee to view balances</p>
                </div>
            ) : loading ? (
                <LoadingSpinner />
            ) : balances.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineScale className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No balance records for {year}</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Leave Type</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Entitled</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Used</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Remaining</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {balances.map((bal) => (
                                    <tr key={bal.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">{bal.leave_type?.name || '-'}</td>
                                        <td className="px-4 py-3 text-right text-sm text-gray-600">{bal.entitled_days}</td>
                                        <td className="px-4 py-3 text-right text-sm text-gray-600">{bal.used_days}</td>
                                        <td className="px-4 py-3 text-right text-sm font-semibold text-green-600">{bal.remaining_days}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );
}
