import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import payrollService from '@/services/payrollService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineCog, HiOutlineEye, HiOutlineCheck, HiOutlineCash, HiOutlineMail, HiOutlineDocumentDownload } from 'react-icons/hi';

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    approved: 'bg-blue-100 text-blue-700',
    paid: 'bg-green-100 text-green-700',
};
const fmt = (v) => 'RM ' + Number(v || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 });
const monthStart = () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0]; };
const monthEnd = () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0]; };

export default function PayrollList() {
    const { can } = useAuth();
    const [records, setRecords] = useState([]);
    const [loading, setLoading] = useState(true);
    const [pagination, setPagination] = useState({});
    const [statusFilter, setStatusFilter] = useState('');
    const [showGen, setShowGen] = useState(false);
    const [gen, setGen] = useState({ period_start: monthStart(), period_end: monthEnd() });
    const [busy, setBusy] = useState(false);

    const fetchRecords = async (page = 1) => {
        setLoading(true);
        try {
            const params = { page };
            if (statusFilter) params.status = statusFilter;
            const res = await payrollService.list(params);
            setRecords(res.data?.data || []);
            setPagination(res.data || {});
        } catch {
            setRecords([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchRecords(); }, [statusFilter]);

    const handleGenerate = async (e) => {
        e.preventDefault();
        setBusy(true);
        try {
            const res = await payrollService.generate(gen);
            toast.success(res.message || `Generated ${res.data?.generated ?? ''} record(s)`);
            setShowGen(false);
            fetchRecords();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Generation failed');
        } finally {
            setBusy(false);
        }
    };

    const act = async (fn, id, ok) => {
        try { await fn(id); toast.success(ok); fetchRecords(); }
        catch (err) { toast.error(err.response?.data?.message || 'Action failed'); }
    };

    const emailOne = async (id) => {
        try { await payrollService.emailPayslip(id); toast.success('Payslip emailed'); fetchRecords(); }
        catch (err) { toast.error(err.response?.data?.message || 'Email failed'); }
    };

    const batchEmail = async () => {
        if (!confirm('Email payslips to all employees in this month?')) return;
        try {
            const res = await payrollService.batchEmail({ period_start: monthStart(), period_end: monthEnd() });
            toast.success(res.message || 'Payslips emailed');
        } catch (err) { toast.error(err.response?.data?.message || 'Batch email failed'); }
    };

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Payroll</h1>
                    <p className="text-sm text-gray-500">Generate payroll, payslips &amp; statutory deductions</p>
                </div>
                <div className="flex gap-2">
                    {can('payroll.email') && (
                        <button onClick={batchEmail} className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            <HiOutlineMail className="h-5 w-5" /> Email All
                        </button>
                    )}
                    {can('payroll.generate') && (
                        <button onClick={() => setShowGen(true)} className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                            <HiOutlineCog className="h-5 w-5" /> Generate Payroll
                        </button>
                    )}
                </div>
            </div>

            <div className="mb-6">
                <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="approved">Approved</option>
                    <option value="paid">Paid</option>
                </select>
            </div>

            {loading ? <LoadingSpinner /> : records.length === 0 ? (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm ring-1 ring-gray-200">
                    <HiOutlineCash className="mx-auto h-12 w-12 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-500">No payroll records. Generate payroll for a period to begin.</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Employee</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Period</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Gross</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Net</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {records.map((r) => (
                                    <tr key={r.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">
                                            {r.employee?.full_name || (r.user ? `${r.user.first_name} ${r.user.last_name || ''}` : `#${r.user_id}`)}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.period_start} – {r.period_end}</td>
                                        <td className="px-4 py-3 text-right text-sm text-gray-900">{fmt(r.gross_salary)}</td>
                                        <td className="px-4 py-3 text-right text-sm font-semibold text-gray-900">{fmt(r.net_salary)}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[r.status]}`}>{r.status}</span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link to={`/hr/payroll/${r.id}`} className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="View"><HiOutlineEye className="h-4 w-4" /></Link>
                                                <a href={payrollService.payslipUrl(r.id)} target="_blank" rel="noreferrer" className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Payslip PDF"><HiOutlineDocumentDownload className="h-4 w-4" /></a>
                                                {can('payroll.approve') && r.status === 'draft' && (
                                                    <button onClick={() => act(payrollService.approve, r.id, 'Approved')} className="rounded p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600" title="Approve"><HiOutlineCheck className="h-4 w-4" /></button>
                                                )}
                                                {can('payroll.approve') && r.status === 'approved' && (
                                                    <button onClick={() => act(payrollService.markPaid, r.id, 'Marked paid')} className="rounded p-1.5 text-gray-400 hover:bg-green-50 hover:text-green-600" title="Mark Paid"><HiOutlineCash className="h-4 w-4" /></button>
                                                )}
                                                {can('payroll.email') && (
                                                    <button onClick={() => emailOne(r.id)} className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Email payslip"><HiOutlineMail className="h-4 w-4" /></button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {pagination.last_page > 1 && (
                        <div className="flex items-center justify-between border-t px-4 py-3">
                            <p className="text-sm text-gray-500">Showing {pagination.from}-{pagination.to} of {pagination.total}</p>
                            <div className="flex gap-1">
                                {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map((page) => (
                                    <button key={page} onClick={() => fetchRecords(page)}
                                        className={`rounded px-3 py-1 text-sm ${page === pagination.current_page ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}>{page}</button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {showGen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowGen(false)}>
                    <div className="mx-4 max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="mb-1 text-lg font-semibold text-gray-900">Generate Payroll</h3>
                        <p className="mb-4 text-xs text-gray-500">Aggregates attendance for the period and computes EPF/SOCSO/EIS automatically.</p>
                        <form onSubmit={handleGenerate} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Period Start</label>
                                    <input type="date" value={gen.period_start} onChange={(e) => setGen((p) => ({ ...p, period_start: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Period End</label>
                                    <input type="date" value={gen.period_end} onChange={(e) => setGen((p) => ({ ...p, period_end: e.target.value }))} required className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={() => setShowGen(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" disabled={busy} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{busy ? 'Generating...' : 'Generate'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
