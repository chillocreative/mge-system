import { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import reportDataService from '@/services/reportDataService';
import LoadingSpinner from '@/components/LoadingSpinner';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';
const emptyInsurance = () => ({ type: '', insurer: '', policy_no: '', period_from: '', period_to: '', maintenance_from: '', maintenance_to: '' });
const FIELDS = [
    ['contract_sum', 'Contract Sum (RM)', 'number'], ['performance_bond_amount', 'Performance Guarantee / WJP (RM)', 'number'],
    ['duration_months', 'Duration of Completion (months)', 'number'], ['dlp_months', 'DLP (months)', 'number'],
    ['lad_per_day', 'LAD per day (RM)', 'number'], ['cidb_registration', 'CIDB Registration', 'text'],
    ['possession_date', 'Possession Date', 'date'], ['completion_date', 'Completion Date', 'date'],
    ['dlp_start_date', 'DLP Start', 'date'], ['dlp_end_date', 'DLP End', 'date'],
];

export default function ContractParticularsPanel({ project, canEdit }) {
    const [loading, setLoading] = useState(true);
    const [missing, setMissing] = useState(false);
    const [words, setWords] = useState({});
    const [form, setForm] = useState(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setLoading(true);
        reportDataService.getContractParticulars(project.id)
            .then((res) => {
                const c = res.data.contract;
                setForm({
                    ...Object.fromEntries(FIELDS.map(([k]) => [k, c[k] ?? ''])),
                    insurances: c.insurances?.length ? c.insurances : [emptyInsurance()],
                });
                setWords({ sum: res.data.contract_sum_words, bond: res.data.performance_bond_words });
            })
            .catch((err) => { if (err.response?.status === 404) setMissing(true); else toast.error('Failed to load particulars'); })
            .finally(() => setLoading(false));
    }, [project.id]);

    const save = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = { ...form, insurances: form.insurances.filter((i) => i.type) };
            const res = await reportDataService.updateContractParticulars(project.id, payload);
            setWords({ sum: res.data.contract_sum_words, bond: res.data.performance_bond_words });
            toast.success('Contract particulars saved');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <LoadingSpinner />;
    if (missing) {
        return (
            <div className="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">
                No main contract is set for this project. Open <b>Projects › Contracts</b>, edit the contract and tick <b>Main contract</b>.
            </div>
        );
    }

    const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));
    const setIns = (i, k) => (e) => setForm((f) => ({ ...f, insurances: f.insurances.map((row, idx) => (idx === i ? { ...row, [k]: e.target.value } : row)) }));

    return (
        <form onSubmit={save} className="space-y-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">Contract Particulars</h2>
            <div className="grid gap-4 sm:grid-cols-2">
                {FIELDS.map(([k, label, type]) => (
                    <div key={k}>
                        <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
                        <input type={type} step={type === 'number' ? '0.01' : undefined} value={form[k] ?? ''} onChange={set(k)} disabled={!canEdit} className={input} />
                        {k === 'contract_sum' && words.sum && <p className="mt-1 text-xs text-gray-500">{words.sum}</p>}
                        {k === 'performance_bond_amount' && words.bond && <p className="mt-1 text-xs text-gray-500">{words.bond}</p>}
                    </div>
                ))}
            </div>

            <div>
                <div className="mb-2 flex items-center justify-between">
                    <p className="text-sm font-semibold text-gray-900">Insurance</p>
                    {canEdit && <button type="button" onClick={() => setForm((f) => ({ ...f, insurances: [...f.insurances, emptyInsurance()] }))} className="text-sm font-medium text-primary-600 hover:text-primary-700">+ Add policy</button>}
                </div>
                <div className="space-y-3">
                    {form.insurances.map((ins, i) => (
                        <div key={i} className="grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-4">
                            <input placeholder="Type (e.g. Contractor's All Risk)" value={ins.type} onChange={setIns(i, 'type')} disabled={!canEdit} className={`${input} sm:col-span-2`} />
                            <input placeholder="Insurer" value={ins.insurer} onChange={setIns(i, 'insurer')} disabled={!canEdit} className={input} />
                            <input placeholder="Policy No." value={ins.policy_no} onChange={setIns(i, 'policy_no')} disabled={!canEdit} className={input} />
                            <label className="text-xs text-gray-500">Period from<input type="date" value={ins.period_from || ''} onChange={setIns(i, 'period_from')} disabled={!canEdit} className={input} /></label>
                            <label className="text-xs text-gray-500">Period to<input type="date" value={ins.period_to || ''} onChange={setIns(i, 'period_to')} disabled={!canEdit} className={input} /></label>
                            <label className="text-xs text-gray-500">Maintenance from<input type="date" value={ins.maintenance_from || ''} onChange={setIns(i, 'maintenance_from')} disabled={!canEdit} className={input} /></label>
                            <label className="text-xs text-gray-500">Maintenance to<input type="date" value={ins.maintenance_to || ''} onChange={setIns(i, 'maintenance_to')} disabled={!canEdit} className={input} /></label>
                            {canEdit && <button type="button" onClick={() => setForm((f) => ({ ...f, insurances: f.insurances.filter((_, idx) => idx !== i) }))} className="text-left text-xs text-red-600 hover:underline sm:col-span-4">Remove policy</button>}
                        </div>
                    ))}
                </div>
            </div>

            {canEdit && (
                <div className="flex justify-end">
                    <button type="submit" disabled={saving} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">{saving ? 'Saving…' : 'Save particulars'}</button>
                </div>
            )}
        </form>
    );
}
