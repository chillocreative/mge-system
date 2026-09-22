import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import environmentReportService, { REPORT_STATUS_FINAL } from '@/services/environmentReportService';
import LoadingSpinner from '@/components/LoadingSpinner';
import toast from 'react-hot-toast';
import {
    HiOutlineArrowLeft, HiOutlinePencil, HiOutlinePrinter, HiOutlineDownload,
} from 'react-icons/hi';

const statusColors = {
    draft: 'bg-gray-100 text-gray-600',
    final: 'bg-green-100 text-green-700',
};

export default function EnvironmentReportViewer() {
    const { id } = useParams();
    const { can } = useAuth();
    const canManage = can('environmental.manage');
    const iframeRef = useRef(null);

    const [report, setReport] = useState(null);
    const [loading, setLoading] = useState(true);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await environmentReportService.get(id);
            setReport(res.data);
        } catch {
            toast.error('Failed to load report');
        } finally {
            setLoading(false);
        }
    }, [id]);

    useEffect(() => { load(); }, [load]);

    const handlePrint = () => {
        try {
            iframeRef.current?.contentWindow?.print();
        } catch {
            window.open(environmentReportService.pdfUrl(id, true), '_blank');
        }
    };

    const downloadPdf = async () => {
        try { await environmentReportService.downloadPdf(id); }
        catch { toast.error('Failed to download PDF'); }
    };
    const downloadDocx = async () => {
        try { await environmentReportService.downloadDocx(id); }
        catch { toast.error('Failed to download DOCX'); }
    };

    if (loading) return <LoadingSpinner />;
    if (!report) {
        return (
            <div className="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200">
                Report not found.
            </div>
        );
    }

    const isFinal = report.status === REPORT_STATUS_FINAL;

    return (
        <div>
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Link to="/environment/reports" className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <HiOutlineArrowLeft className="h-4 w-4" /> Back to Monthly Reports
                </Link>
                <div className="flex flex-wrap items-center gap-2">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[report.status]}`}>{report.status}</span>
                    {canManage && !isFinal && (
                        <Link to={`/environment/reports/${id}/edit`} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <HiOutlinePencil className="h-4 w-4" /> Edit
                        </Link>
                    )}
                    <button type="button" onClick={handlePrint} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <HiOutlinePrinter className="h-4 w-4" /> Print
                    </button>
                    <button type="button" onClick={downloadPdf} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <HiOutlineDownload className="h-4 w-4" /> PDF
                    </button>
                    <button type="button" onClick={downloadDocx} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <HiOutlineDownload className="h-4 w-4" /> DOCX
                    </button>
                </div>
            </div>

            <iframe
                ref={iframeRef}
                title="Report"
                src={environmentReportService.pdfUrl(id, true)}
                className="h-[calc(100vh-10rem)] w-full rounded-lg bg-white shadow"
            />
        </div>
    );
}
