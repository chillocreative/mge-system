import { useState, useEffect } from 'react';
import apiClient from '@/services/apiClient';
import payrollService from '@/services/payrollService';
import toast from 'react-hot-toast';
import { HiOutlineDocumentText } from 'react-icons/hi';

export default function EaForm() {
    const [employees, setEmployees] = useState([]);
    const [employeeId, setEmployeeId] = useState('');
    const [year, setYear] = useState(new Date().getFullYear() - 1);

    useEffect(() => {
        apiClient.get('/employees', { params: { per_page: 200, status: 'active' } })
            .then((r) => setEmployees(r.data?.data?.data || r.data?.data || []))
            .catch(() => {});
    }, []);

    const open = () => {
        if (!employeeId) { toast.error('Select an employee'); return; }
        window.open(payrollService.eaFormUrl(employeeId, year), '_blank');
    };

    const years = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - i);

    return (
        <div className="mx-auto max-w-2xl">
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">EA Form (C.P.8A)</h1>
                <p className="text-sm text-gray-500">Generate the annual statement of remuneration for an employee.</p>
            </div>

            <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Employee</label>
                        <select value={employeeId} onChange={(e) => setEmployeeId(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <option value="">Select employee...</option>
                            {employees.map((e) => <option key={e.id} value={e.id}>{e.full_name} ({e.employee_no})</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Year</label>
                        <select value={year} onChange={(e) => setYear(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            {years.map((y) => <option key={y} value={y}>{y}</option>)}
                        </select>
                    </div>
                </div>
                <button onClick={open} className="mt-5 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                    <HiOutlineDocumentText className="h-5 w-5" /> Generate EA Form (PDF)
                </button>
                <p className="mt-3 text-xs text-gray-400">Aggregated from the employee's payroll records for the selected year. Verify against the official LHDN template before issuing.</p>
            </div>
        </div>
    );
}
