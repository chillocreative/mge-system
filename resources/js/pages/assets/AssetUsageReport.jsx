import { useCallback, useEffect, useState } from 'react';
import assetService from '@/services/assetService';
import LoadingSpinner from '@/components/LoadingSpinner';
import useDragScroll from '@/hooks/useDragScroll';
import toast from 'react-hot-toast';
import { HiOutlineDocumentReport, HiOutlineDownload } from 'react-icons/hi';

const currentMonth = () => new Date().toISOString().slice(0, 7);
const label = (value) => value ? value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase()) : 'No Record';
const statusClass = (value) => {
    if (['overdue', 'expired'].includes(value)) return 'bg-red-100 text-red-700';
    if (['due_soon', 'expiring'].includes(value)) return 'bg-amber-100 text-amber-700';
    if (['valid', 'up_to_date', 'completed'].includes(value)) return 'bg-green-100 text-green-700';
    return 'bg-gray-100 text-gray-600';
};

export default function AssetUsageReport() {
    const [filters, setFilters] = useState({ month: currentMonth(), category: '' });
    const [report, setReport] = useState(null);
    const [loading, setLoading] = useState(true);
    const dragScrollRef = useDragScroll();

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const response = await assetService.usageReport(filters);
            setReport(response.data);
        } catch {
            toast.error('Unable to load the asset usage report');
        } finally {
            setLoading(false);
        }
    }, [filters]);

    useEffect(() => { load(); }, [load]);

    const download = (format) => window.open(assetService.usageReportExportUrl(filters, format), '_blank');
    const rows = report?.rows || [];
    const summary = report?.summary || {};

    return (
        <div>
            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900"><HiOutlineDocumentReport className="h-7 w-7 text-primary-600" /> Vehicle &amp; Machine Usage Report</h1>
                    <p className="text-sm text-gray-500">Assignment usage, maintenance due dates and road-tax expiry status.</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {['xlsx', 'docx', 'pdf'].map((format) => (
                        <button key={format} type="button" onClick={() => download(format)} className="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <HiOutlineDownload /> {format.toUpperCase()}
                        </button>
                    ))}
                </div>
            </div>

            <div className="mb-5 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-2">
                <label className="text-sm font-medium text-gray-700">Reporting Month<input type="month" value={filters.month} onChange={(event) => setFilters((previous) => ({ ...previous, month: event.target.value }))} className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2" /></label>
                <label className="text-sm font-medium text-gray-700">Asset Category<select value={filters.category} onChange={(event) => setFilters((previous) => ({ ...previous, category: event.target.value }))} className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2"><option value="">All Vehicles &amp; Machines</option><option value="vehicle">Vehicles</option><option value="machine">Machines</option></select></label>
            </div>

            <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                {[
                    ['Assets', summary.total_assets ?? 0],
                    ['Used This Month', summary.used_assets ?? 0],
                    ['Usage Days', summary.monthly_usage_days ?? 0],
                    ['Maintenance Due', summary.maintenance_due ?? 0],
                    ['Road Tax Attention', summary.road_tax_attention ?? 0],
                ].map(([name, value]) => <div key={name} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200"><p className="text-xs font-medium uppercase text-gray-500">{name}</p><p className="mt-1 text-2xl font-bold text-gray-900">{value}</p></div>)}
            </div>

            {loading ? <LoadingSpinner /> : (
                <div ref={dragScrollRef} className="overflow-x-auto cursor-grab rounded-xl bg-white shadow-sm ring-1 ring-gray-200 active:cursor-grabbing">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-gray-50 text-xs uppercase text-gray-500"><tr><th className="px-4 py-3">Asset</th><th className="px-4 py-3">Category</th><th className="px-4 py-3">Projects</th><th className="px-4 py-3 text-right">Month Usage</th><th className="px-4 py-3 text-right">Total Usage</th><th className="px-4 py-3">Maintenance</th><th className="px-4 py-3">Road Tax</th></tr></thead>
                        <tbody>
                            {rows.map((row) => <tr key={row.id} className="border-t"><td className="whitespace-nowrap px-4 py-3"><p className="font-medium text-gray-900">{row.asset_no}</p><p className="text-xs text-gray-500">{row.asset}</p></td><td className="px-4 py-3">{label(row.category)}</td><td className="min-w-52 px-4 py-3">{row.projects || '—'}</td><td className="whitespace-nowrap px-4 py-3 text-right"><span className="font-semibold">{row.monthly_usage_days} days</span><p className="text-xs text-gray-400">{row.monthly_assignment_count} assignment(s)</p></td><td className="whitespace-nowrap px-4 py-3 text-right font-semibold">{row.total_usage_days} days</td><td className="whitespace-nowrap px-4 py-3"><span className={`rounded-full px-2 py-1 text-xs font-medium ${statusClass(row.maintenance_status)}`}>{label(row.maintenance_status)}</span><p className="mt-1 text-xs text-gray-500">Due: {row.maintenance_due_date || '—'}</p></td><td className="whitespace-nowrap px-4 py-3"><span className={`rounded-full px-2 py-1 text-xs font-medium ${statusClass(row.road_tax_status)}`}>{label(row.road_tax_status)}</span><p className="mt-1 text-xs text-gray-500">{row.road_tax_expiry_date || '—'}{row.road_tax_days_remaining !== null ? ` (${row.road_tax_days_remaining} days)` : ''}</p></td></tr>)}
                            {!rows.length && <tr><td colSpan="7" className="px-4 py-12 text-center text-gray-500">No assets found for this filter.</td></tr>}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
