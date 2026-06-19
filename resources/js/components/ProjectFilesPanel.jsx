import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import projectService from '@/services/projectService';
import toast from 'react-hot-toast';
import { HiOutlineDocumentText, HiOutlineDownload, HiOutlineTrash, HiOutlinePaperClip } from 'react-icons/hi';

const fmtSize = (b) => {
    if (!b && b !== 0) return '';
    if (b < 1024) return `${b} B`;
    if (b < 1048576) return `${(b / 1024).toFixed(0)} KB`;
    return `${(b / 1048576).toFixed(1)} MB`;
};

/**
 * Shared per-project file list. Used in the Projects table modal (editable)
 * and dropped into other module forms (readOnly) to view/download the same
 * project's files.
 */
export default function ProjectFilesPanel({ projectId, readOnly = false }) {
    const { can } = useAuth();
    const editable = !readOnly && can('projects.edit');
    const [files, setFiles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);

    const fetchFiles = useCallback(async () => {
        if (!projectId) { setFiles([]); setLoading(false); return; }
        setLoading(true);
        try {
            const res = await projectService.getDocuments(projectId, { per_page: 100 });
            setFiles(res.data?.data || []);
        } catch {
            setFiles([]);
        } finally {
            setLoading(false);
        }
    }, [projectId]);

    useEffect(() => { fetchFiles(); }, [fetchFiles]);

    const onAdd = async (e) => {
        const picked = Array.from(e.target.files);
        e.target.value = '';
        if (!picked.length) return;
        setUploading(true);
        try {
            await projectService.uploadDocumentsBulk(projectId, picked);
            toast.success(`${picked.length} file(s) uploaded`);
            fetchFiles();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Upload failed');
        } finally {
            setUploading(false);
        }
    };

    const onDelete = async (doc) => {
        if (!window.confirm(`Delete "${doc.file_name}"?`)) return;
        try {
            await projectService.deleteDocument(projectId, doc.id);
            toast.success('File deleted');
            setFiles((p) => p.filter((f) => f.id !== doc.id));
        } catch {
            toast.error('Failed to delete');
        }
    };

    if (!projectId) {
        return <p className="text-sm text-gray-400">Select a project to see its files.</p>;
    }

    return (
        <div>
            {editable && (
                <label className="mb-3 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:border-primary-400 hover:text-primary-700">
                    <HiOutlinePaperClip className="h-4 w-4" />
                    {uploading ? 'Uploading...' : 'Add files'}
                    <input type="file" multiple className="hidden" onChange={onAdd} disabled={uploading} />
                </label>
            )}

            {loading ? (
                <p className="py-4 text-sm text-gray-400">Loading files...</p>
            ) : files.length === 0 ? (
                <div className="rounded-lg border border-dashed border-gray-200 py-6 text-center">
                    <HiOutlineDocumentText className="mx-auto h-8 w-8 text-gray-300" />
                    <p className="mt-1 text-sm text-gray-400">No files for this project yet</p>
                </div>
            ) : (
                <ul className="divide-y divide-gray-100 rounded-lg border border-gray-200">
                    {files.map((f) => (
                        <li key={f.id} className="flex items-center gap-3 px-3 py-2.5">
                            <HiOutlineDocumentText className="h-5 w-5 shrink-0 text-gray-400" />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-gray-900">{f.file_name}</p>
                                <p className="text-xs text-gray-400">
                                    {fmtSize(f.file_size)}
                                    {f.uploader ? ` · ${f.uploader.first_name} ${f.uploader.last_name}` : ''}
                                    {f.created_at ? ` · ${String(f.created_at).split('T')[0]}` : ''}
                                </p>
                            </div>
                            <a href={projectService.getDocumentDownloadUrl(projectId, f.id)} target="_blank" rel="noreferrer"
                                className="rounded p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600" title="Download">
                                <HiOutlineDownload className="h-4 w-4" />
                            </a>
                            {editable && (
                                <button type="button" onClick={() => onDelete(f)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                    <HiOutlineTrash className="h-4 w-4" />
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
