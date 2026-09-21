import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import correspondenceService from '@/services/correspondenceService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import statusColors from './statusColors';
import toast from 'react-hot-toast';
import { HiOutlineDocumentText, HiOutlinePaperClip, HiOutlineDownload } from 'react-icons/hi';

const STORAGE_KEY = 'correspondence-list.project';

export default function CorrespondenceList() {
    const [projects, setProjects] = useState([]);
    const [types, setTypes] = useState([]);
    const [projectId, setProjectId] = useState(() => localStorage.getItem(STORAGE_KEY) || '');
    const [typeCode, setTypeCode] = useState('');
    const [context, setContext] = useState(null);
    const [loading, setLoading] = useState(false);
    const [downloading, setDownloading] = useState('');

    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
        correspondenceService.types().then((r) => setTypes((r.data || []).filter((t) => t.is_active).sort((a, b) => a.sort_order - b.sort_order))).catch(() => {});
    }, []);

    useEffect(() => {
        if (!typeCode && types.length > 0) {
            setTypeCode(types[0].code);
        }
    }, [types, typeCode]);

    useEffect(() => {
        if (projectId) {
            localStorage.setItem(STORAGE_KEY, projectId);
        } else {
            localStorage.removeItem(STORAGE_KEY);
        }
    }, [projectId]);

    const fetchRegister = useCallback(async () => {
        if (!projectId || !typeCode) {
            setContext(null);
            return;
        }
        setLoading(true);
        try {
            const res = await correspondenceService.register({ project_id: projectId, type: typeCode });
            setContext(res.data);
        } catch {
            setContext(null);
            toast.error('Failed to load register');
        } finally {
            setLoading(false);
        }
    }, [projectId, typeCode]);

    useEffect(() => {
        fetchRegister();
    }, [fetchRegister]);

    const handleDownload = async (format) => {
        if (!projectId || !typeCode) return;
        setDownloading(format);
        try {
            await correspondenceService.downloadRegister({ project_id: projectId, type: typeCode }, format);
        } catch {
            toast.error(`Failed to download ${format.toUpperCase()}`);
        } finally {
            setDownloading('');
        }
    };

    const selectedProject = projects.find((p) => String(p.id) === String(projectId));
    const canDownload = Boolean(projectId && typeCode);

    return (
        <div>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Correspondence List</h1>
                    <p className="text-sm text-gray-500">Register of correspondence by project and type</p>
                </div>
                <div className="flex gap-2">
                    <button
                        onClick={() => handleDownload('xlsx')}
                        disabled={!canDownload || downloading !== ''}
                        className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <HiOutlineDownload className="h-5 w-5" /> {downloading === 'xlsx' ? 'Downloading…' : 'Download Excel'}
                    </button>
                    <button
                        onClick={() => handleDownload('pdf')}
                        disabled={!canDownload || downloading !== ''}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <HiOutlineDownload className="h-5 w-5" /> {downloading === 'pdf' ? 'Downloading…' : 'Download PDF'}
                    </button>
                </div>
            </div>

            {/* Toolbar */}
            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <select
                    value={projectId}
                    onChange={(e) => setProjectId(e.target.value)}
                    className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:min-w-[16rem]"
                >
                    <option value="">Select project</option>
                    {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>

                <div className="flex flex-wrap gap-2">
                    {types.map((t) => (
                        <button
                            key={t.code}
                            onClick={() => setTypeCode(t.code)}
                            title={t.full_name}
                            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${typeCode === t.code ? 'bg-primary-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'}`}
                        >
                            {t.name}
                        </button>
                    ))}
                </div>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                {!projectId || !typeCode ? (
                    <div className="py-12 text-center">
                        <HiOutlineDocumentText className="mx-auto h-12 w-12 text-gray-300" />
                        <p className="mt-2 text-sm text-gray-500">Select a project and a correspondence type to view its register</p>
                    </div>
                ) : loading ? (
                    <LoadingSpinner />
                ) : !context ? (
                    <div className="py-12 text-center">
                        <HiOutlineDocumentText className="mx-auto h-12 w-12 text-gray-300" />
                        <p className="mt-2 text-sm text-gray-500">Unable to load register</p>
                    </div>
                ) : (
                    <>
                        {/* Header strip mirroring the sheet header */}
                        <div className="flex items-center gap-4 border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <img src="/logo.png" alt="Logo" className="h-10 w-auto" onError={(e) => { e.target.style.display = 'none'; }} />
                            <div>
                                <p className="text-lg font-bold text-gray-900">PROJECT : {selectedProject?.name?.toUpperCase() || ''}</p>
                                <p className="text-sm font-semibold text-gray-600">
                                    {context.type?.full_name} ({context.type?.code?.toUpperCase()})
                                </p>
                            </div>
                        </div>

                        {context.rows.length === 0 ? (
                            <div className="py-12 text-center">
                                <HiOutlineDocumentText className="mx-auto h-12 w-12 text-gray-300" />
                                <p className="mt-2 text-sm text-gray-500">No correspondence records for this project and type</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th rowSpan={2} className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 align-bottom">Bil</th>
                                            <th rowSpan={2} className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 align-bottom">Reference Number</th>
                                            <th rowSpan={2} className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 align-bottom">Title</th>
                                            <th rowSpan={2} className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 align-bottom">Date Issued</th>
                                            <th colSpan={1} className="px-4 py-1 text-center text-xs font-semibold uppercase text-gray-500">Date</th>
                                            <th colSpan={1} className="px-4 py-1 text-center text-xs font-semibold uppercase text-gray-500">Date</th>
                                            <th rowSpan={2} className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 align-bottom">Status</th>
                                            <th rowSpan={2} className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 align-bottom">Remarks</th>
                                            <th rowSpan={2} className="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500 align-bottom">Attachment</th>
                                        </tr>
                                        <tr>
                                            <th className="px-4 py-1 text-center text-xs font-semibold uppercase text-gray-500">Inspection</th>
                                            <th className="px-4 py-1 text-center text-xs font-semibold uppercase text-gray-500">Closed</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {context.rows.map((row) => (
                                            <tr key={row.id} className="hover:bg-gray-50">
                                                <td className="px-4 py-3 text-sm text-gray-600">{row.bil}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm">
                                                    <Link
                                                        to={`/projects/correspondence?project_id=${projectId}&type=${typeCode}&search=${encodeURIComponent(row.reference_no || '')}`}
                                                        className="text-primary-600 hover:underline"
                                                    >
                                                        {row.reference_no || '-'}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="text-sm font-medium text-gray-900">{row.title}</p>
                                                    {(row.from || row.to) && (
                                                        <p className="text-xs text-gray-500">{row.from || '—'} → {row.to || '—'}</p>
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{formatDate(row.date_issued)}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{formatDate(row.date_inspection)}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{formatDate(row.date_closed)}</td>
                                                <td className="px-4 py-3">
                                                    <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[row.status] || 'bg-gray-100 text-gray-600'}`}>
                                                        {row.status}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-sm text-gray-600">{row.remarks || '-'}</td>
                                                <td className="px-4 py-3 text-center text-sm text-gray-600">
                                                    {row.attachments > 0 ? (
                                                        <span className="inline-flex items-center gap-1">
                                                            <HiOutlinePaperClip className="h-4 w-4 text-gray-400" /> {row.attachments}
                                                        </span>
                                                    ) : '-'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
