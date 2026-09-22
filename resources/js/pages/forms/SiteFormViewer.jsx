import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import siteFormService from '@/services/siteFormService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { bySlug } from './formTypes';
import { FormDataProvider } from './fields';
import { captureSheets, downloadJpg, downloadPdf, downloadDocx, sanitizeBaseName } from './exportSheets';
import {
    HiOutlineDownload, HiOutlineDocument, HiOutlinePhotograph, HiOutlinePrinter, HiOutlineX, HiOutlineDocumentText,
} from 'react-icons/hi';

const today = () => new Date().toISOString().split('T')[0];

export default function SiteFormViewer() {
    const { type, id } = useParams();
    const navigate = useNavigate();
    const meta = bySlug[type];
    const printRef = useRef(null);

    const [record, setRecord] = useState(null);
    const [loading, setLoading] = useState(true);
    const [notFound, setNotFound] = useState(false);
    const [exporting, setExporting] = useState(null);

    useEffect(() => {
        if (!meta) {
            setLoading(false);
            setNotFound(true);
            return;
        }

        let cancelled = false;
        setLoading(true);
        siteFormService.get(id)
            .then((res) => {
                if (cancelled) return;
                const loaded = res.data;
                setRecord({
                    id: loaded.id,
                    ref_no: loaded.ref_no || '',
                    form_date: loaded.form_date ? String(loaded.form_date).slice(0, 10) : today(),
                    title: loaded.title || '',
                    status: loaded.status || 'draft',
                    data: loaded.data || {},
                    attachments: loaded.attachments || [],
                    project: loaded.project || null,
                });
            })
            .catch(() => {
                if (!cancelled) setNotFound(true);
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => { cancelled = true; };
    }, [id, meta]);

    const withExport = useCallback((key, fn) => async () => {
        if (exporting) return;
        setExporting(key);
        try {
            await fn();
        } catch {
            toast.error('Export failed. Please try again.');
        } finally {
            setExporting(null);
        }
    }, [exporting]);

    const baseName = record ? sanitizeBaseName(record.ref_no, record.id) : 'site-form';

    const onDownloadPdf = withExport('pdf', async () => {
        const canvases = await captureSheets(printRef.current);
        await downloadPdf(canvases, baseName);
    });

    const onDownloadJpg = withExport('jpg', async () => {
        const canvases = await captureSheets(printRef.current);
        downloadJpg(canvases, baseName);
    });

    const onDownloadDocx = withExport('docx', async () => {
        const canvases = await captureSheets(printRef.current);
        await downloadDocx(canvases, record, baseName);
    });

    const onClose = () => {
        window.close();
        // window.close() only works on a tab opened by script; otherwise it's a silent
        // no-op and we're still here a moment later — fall back to normal navigation.
        setTimeout(() => {
            navigate(-1);
        }, 150);
    };

    if (loading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-gray-100">
                <LoadingSpinner />
            </div>
        );
    }

    if (notFound || !meta || !record) {
        return (
            <div className="flex min-h-screen flex-col items-center justify-center gap-3 bg-gray-100 text-center">
                <HiOutlineDocumentText className="h-12 w-12 text-gray-300" />
                <p className="text-lg font-semibold text-gray-600">Form not found</p>
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                    Close
                </button>
            </div>
        );
    }

    const Layout = meta.Layout;

    return (
        <div className="min-h-screen bg-gray-100">
            <div className="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-3 shadow-sm print:hidden">
                <div className="min-w-0">
                    <p className="truncate text-sm font-bold text-gray-900">
                        {meta.title}{meta.subtitle ? ` ${meta.subtitle}` : ''}
                    </p>
                    <p className="truncate text-xs text-gray-500">
                        {record.ref_no || '-'}{record.project?.name ? ` · ${record.project.name}` : ''}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        onClick={onDownloadPdf}
                        disabled={!!exporting}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                        <HiOutlineDownload className="h-4 w-4" /> {exporting === 'pdf' ? 'Exporting...' : 'PDF'}
                    </button>
                    <button
                        type="button"
                        onClick={onDownloadDocx}
                        disabled={!!exporting}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                        <HiOutlineDocument className="h-4 w-4" /> {exporting === 'docx' ? 'Exporting...' : 'DOCX'}
                    </button>
                    <button
                        type="button"
                        onClick={onDownloadJpg}
                        disabled={!!exporting}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                        <HiOutlinePhotograph className="h-4 w-4" /> {exporting === 'jpg' ? 'Exporting...' : 'JPG'}
                    </button>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        <HiOutlinePrinter className="h-4 w-4" /> Print
                    </button>
                    <button
                        type="button"
                        onClick={onClose}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-gray-800 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-900"
                    >
                        <HiOutlineX className="h-4 w-4" /> Close
                    </button>
                </div>
            </div>

            <div ref={printRef} className="site-form-print-area py-6">
                <FormDataProvider data={record.data} record={record} readOnly staticView>
                    <Layout meta={meta} record={record} onReload={() => {}} />
                </FormDataProvider>
            </div>
        </div>
    );
}
