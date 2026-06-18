import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import payrollService from '@/services/payrollService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlineArrowLeft, HiOutlinePlus, HiOutlineTrash, HiOutlineDocumentDownload, HiOutlineMail } from 'react-icons/hi';

const fmt = (v) => 'RM ' + Number(v || 0).toLocaleString('en-MY', { minimumFractionDigits: 2 });
const deductionTypes = [
    { value: 'penalty', label: 'Penalty' },
    { value: 'cash_advance', label: 'Cash Advance' },
    { value: 'personal_loan', label: 'Personal Loan' },
    { value: 'other', label: 'Other' },
];

export default function PayslipDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { can } = useAuth();
    const [record, setRecord] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({ allowances: 0, bonus: 0, pcb: 0, zakat: 0 });
    const [items, setItems] = useState([]);

    const load = async () => {
        setLoading(true);
        try {
            const res = await payrollService.get(id);
            const r = res.data;
            setRecord(r);
            setForm({ allowances: r.allowances, bonus: r.bonus, pcb: r.pcb, zakat: r.zakat });
            setItems((r.deduction_items || []).map((d) => ({ type: d.type, description: d.description || '', amount: d.amount })));
        } catch {
            toast.error('Failed to load payslip');
            navigate('/hr/payroll');
        } finally {
            setLoading(false);
        }
    };
    useEffect(() => { load(); }, [id]);

    const addItem = () => setItems((p) => [...p, { type: 'penalty', description: '', amount: '' }]);
    const updateItem = (i, k, v) => setItems((p) => p.map((it, idx) => idx === i ? { ...it, [k]: v } : it));
    const removeItem = (i) => setItems((p) => p.filter((_, idx) => idx !== i));

    const save = async () => {
        setSaving(true);
        try {
            const res = await payrollService.recalculate(id, { ...form, deduction_items: items.filter((it) => it.amount) });
            setRecord(res.data);
            toast.success('Payslip recalculated');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to recalculate');
        } finally {
            setSaving(false);
        }
    };

    const emailPayslip = async () => {
        try { await payrollService.emailPayslip(id); toast.success('Payslip emailed'); load(); }
        catch (err) { toast.error(err.response?.data?.message || 'Email failed'); }
    };

    if (loading || !record) return <LoadingSpinner />;
    const editable = record.status === 'draft';
    const name = record.employee?.full_name || (record.user ? `${record.user.first_name} ${record.user.last_name || ''}` : `#${record.user_id}`);

    return (
        <div className="mx-auto max-w-4xl">
            <button onClick={() => navigate('/hr/payroll')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800">
                <HiOutlineArrowLeft className="h-4 w-4" /> Back to Payroll
            </button>

            <div className="mb-6 flex items-start justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Payslip — {name}</h1>
                    <p className="text-sm text-gray-500">{record.period_start} – {record.period_end} · <span className="capitalize">{record.status}</span></p>
                </div>
                <div className="flex gap-2">
                    <a href={payrollService.payslipUrl(id)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <HiOutlineDocumentDownload className="h-5 w-5" /> PDF
                    </a>
                    {can('payroll.email') && (
                        <button onClick={emailPayslip} className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <HiOutlineMail className="h-5 w-5" /> Email
                        </button>
                    )}
                </div>
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                {/* Earnings */}
                <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h3 className="mb-3 text-sm font-bold uppercase text-gray-500">Earnings</h3>
                    <Row label="Basic Salary" value={fmt(record.base_salary)} />
                    <Row label="Overtime" value={fmt(record.overtime_pay)} />
                    <EditRow label="Allowances" disabled={!editable} value={form.allowances} onChange={(v) => setForm((p) => ({ ...p, allowances: v }))} />
                    <EditRow label="Bonus" disabled={!editable} value={form.bonus} onChange={(v) => setForm((p) => ({ ...p, bonus: v }))} />
                    <div className="mt-2 flex justify-between border-t pt-2 text-sm font-bold"><span>Gross</span><span>{fmt(record.gross_salary)}</span></div>
                </div>

                {/* Deductions */}
                <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h3 className="mb-3 text-sm font-bold uppercase text-gray-500">Statutory Deductions (auto)</h3>
                    <Row label="EPF (Employee)" value={fmt(record.epf_employee)} />
                    <Row label="SOCSO (Employee)" value={fmt(record.socso_employee)} />
                    <Row label="EIS (Employee)" value={fmt(record.eis_employee)} />
                    <EditRow label="PCB (Tax)" disabled={!editable} value={form.pcb} onChange={(v) => setForm((p) => ({ ...p, pcb: v }))} />
                    <EditRow label="Zakat" disabled={!editable} value={form.zakat} onChange={(v) => setForm((p) => ({ ...p, zakat: v }))} />
                    <div className="mt-2 flex justify-between border-t pt-2 text-sm font-bold"><span>Total Deductions</span><span>{fmt(record.deductions)}</span></div>
                </div>
            </div>

            {/* Manual deductions */}
            <div className="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-sm font-bold uppercase text-gray-500">Other Deductions (Penalty / Cash Advance / Loan)</h3>
                    {editable && <button onClick={addItem} className="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700"><HiOutlinePlus className="h-4 w-4" /> Add</button>}
                </div>
                {items.length === 0 ? <p className="text-sm text-gray-400">No manual deductions.</p> : (
                    <div className="space-y-2">
                        {items.map((it, i) => (
                            <div key={i} className="flex items-center gap-2">
                                <select disabled={!editable} value={it.type} onChange={(e) => updateItem(i, 'type', e.target.value)} className="rounded-lg border border-gray-300 px-2 py-2 text-sm">
                                    {deductionTypes.map((d) => <option key={d.value} value={d.value}>{d.label}</option>)}
                                </select>
                                <input disabled={!editable} type="text" placeholder="Description" value={it.description} onChange={(e) => updateItem(i, 'description', e.target.value)} className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                <input disabled={!editable} type="number" step="0.01" min="0" placeholder="Amount" value={it.amount} onChange={(e) => updateItem(i, 'amount', e.target.value)} className="w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                {editable && <button onClick={() => removeItem(i)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><HiOutlineTrash className="h-4 w-4" /></button>}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <div className="mt-6 flex items-center justify-between rounded-xl bg-primary-700 p-5 text-white">
                <span className="text-lg font-bold">NET PAY</span>
                <span className="text-2xl font-bold">{fmt(record.net_salary)}</span>
            </div>

            {editable && can('payroll.generate') && (
                <div className="mt-4 flex justify-end">
                    <button onClick={save} disabled={saving} className="rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                        {saving ? 'Recalculating...' : 'Save & Recalculate'}
                    </button>
                </div>
            )}
        </div>
    );
}

function Row({ label, value }) {
    return <div className="flex justify-between py-1 text-sm"><span className="text-gray-600">{label}</span><span className="text-gray-900">{value}</span></div>;
}
function EditRow({ label, value, onChange, disabled }) {
    return (
        <div className="flex items-center justify-between py-1 text-sm">
            <span className="text-gray-600">{label}</span>
            <input type="number" step="0.01" min="0" disabled={disabled} value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-28 rounded border border-gray-300 px-2 py-1 text-right text-sm disabled:bg-gray-50 disabled:text-gray-500" />
        </div>
    );
}
