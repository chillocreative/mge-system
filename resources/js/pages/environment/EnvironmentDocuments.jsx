import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import toast from 'react-hot-toast';
import { HiOutlineSearch, HiOutlinePlus, HiOutlineEye, HiOutlineTrash, HiOutlineDocumentText } from 'react-icons/hi';
import { useAuth } from '@/context/AuthContext';
import { useConfirm } from '@/context/ConfirmContext';
import LoadingSpinner from '@/components/LoadingSpinner';
import environmentDocumentService from '@/services/environmentDocumentService';

const CATEGORIES = {
    'env-report': 'Env. Report',
    'env-monitoring': 'Env. Monitoring',
    'bmp-inspections': 'BMP Inspections',
    'scheduled-waste': 'Scheduled Waste & e-SWIS',
    'gse-diesel': 'GSE / Diesel Records',
    'training-awareness': 'Training & Awareness',
    'doe-compliance': 'DOE & Environmental Compliance',
    'permits-certificates': 'Permits / Certificates',
};

export default function EnvironmentDocuments() {
    const { category } = useParams();
    const { can } = useAuth();
    const confirm = useConfirm();
    const title = CATEGORIES[category];
    const [records, setRecords] = useState([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [pagination, setPagination] = useState(null);
    const [form, setForm] = useState({ ref_number: '', document_title: '', file: null });

    useEffect(() => { setPage(1); }, [category, search]);
    useEffect(() => {
        if (!title) return;
        let active = true;
        setLoading(true);
        environmentDocumentService.list({ category, search, page })
            .then(({ data }) => { if (active) { setRecords(data.data || []); setPagination(data); } })
            .catch(() => toast.error('Failed to load environmental records'))
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, [category, search, page, title]);

    const canCreate = can('environmental.create');
    const canManage = can('environmental.manage');

    const submit = async (event) => {
        event.preventDefault();
        if (!form.file) return toast.error('Please choose a document to upload');
        const data = new FormData();
        data.append('category', category);
        data.append('ref_number', form.ref_number);
        data.append('document_title', form.document_title);
        data.append('file', form.file);
        try {
            await environmentDocumentService.create(data);
            setForm({ ref_number: '', document_title: '', file: null });
            event.target.reset();
            toast.success('Document uploaded');
            setPage(1);
            const { data: result } = await environmentDocumentService.list({ category, search, page: 1 });
            setRecords(result.data || []); setPagination(result);
        } catch (error) {
            toast.error(error.response?.data?.message || 'Failed to upload document');
        }
    };

    const view = async (record) => {
        try {
            const response = await environmentDocumentService.download(record.id);
            const url = URL.createObjectURL(response.data);
            window.open(url, '_blank', 'noopener,noreferrer');
            setTimeout(() => URL.revokeObjectURL(url), 60_000);
        } catch { toast.error('Unable to open document'); }
    };

    const remove = async (record) => {
        const accepted = await confirm({ title: 'Delete document?', message: `Delete “${record.document_title}” permanently?`, confirmText: 'Delete', danger: true });
        if (!accepted) return;
        try {
            await environmentDocumentService.remove(record.id);
            toast.success('Document deleted');
            const { data } = await environmentDocumentService.list({ category, search, page });
            if (!data.data.length && page > 1) setPage(page - 1);
            else { setRecords(data.data || []); setPagination(data); }
        } catch { toast.error('Failed to delete document'); }
    };

    if (!title) return <div className="p-8 text-gray-600">Unknown environmental document category.</div>;

    return (
        <div className="space-y-6 p-6">
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div><h1 className="text-2xl font-bold text-gray-900">{title}</h1><p className="mt-1 text-gray-500">Upload and keep environmental records in one place.</p></div>
            </header>

            {canCreate && <form onSubmit={submit} className="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-4">
                <input value={form.ref_number} onChange={e => setForm({ ...form, ref_number: e.target.value })} placeholder="Ref. Number" maxLength="100" className="rounded-lg border border-gray-300 px-3 py-2" />
                <input value={form.document_title} onChange={e => setForm({ ...form, document_title: e.target.value })} placeholder="Document Title" required maxLength="255" className="rounded-lg border border-gray-300 px-3 py-2" />
                <input type="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" onChange={e => setForm({ ...form, file: e.target.files?.[0] || null })} className="min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                <button className="inline-flex items-center justify-center gap-2 rounded-lg bg-teal-700 px-4 py-2 font-medium text-white hover:bg-teal-800"><HiOutlinePlus /> Upload</button>
            </form>}

            <div className="flex max-w-xl items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2">
                <HiOutlineSearch className="h-5 w-5 text-gray-400" />
                <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search reference number or title..." className="w-full border-0 p-0 outline-none focus:ring-0" />
            </div>

            <section className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                {loading ? <div className="p-12"><LoadingSpinner /></div> : <>
                    <div className="overflow-x-auto"><table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50"><tr>{['Bil.', 'Ref. Number', 'Document Title', 'Action'].map(label => <th key={label} className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{label}</th>)}</tr></thead>
                        <tbody className="divide-y divide-gray-100">
                            {records.map((record, index) => <tr key={record.id} className="hover:bg-gray-50">
                                <td className="whitespace-nowrap px-5 py-4 text-sm text-gray-500">{((page - 1) * 20) + index + 1}</td>
                                <td className="whitespace-nowrap px-5 py-4 text-sm text-gray-700">{record.ref_number || '—'}</td>
                                <td className="px-5 py-4"><div className="flex items-center gap-2"><HiOutlineDocumentText className="h-5 w-5 shrink-0 text-teal-700" /><div><div className="font-medium text-gray-900">{record.document_title}</div><div className="text-xs text-gray-500">{record.file_name}</div></div></div></td>
                                <td className="whitespace-nowrap px-5 py-4"><div className="flex gap-2"><button onClick={() => view(record)} title="View" className="rounded p-2 text-teal-700 hover:bg-teal-50"><HiOutlineEye className="h-5 w-5" /></button>{canManage && <button onClick={() => remove(record)} title="Delete" className="rounded p-2 text-red-600 hover:bg-red-50"><HiOutlineTrash className="h-5 w-5" /></button>}</div></td>
                            </tr>)}
                            {!records.length && <tr><td colSpan="4" className="px-5 py-14 text-center text-gray-500">No records found.</td></tr>}
                        </tbody>
                    </table></div>
                    {pagination?.last_page > 1 && <div className="flex items-center justify-between border-t px-5 py-3 text-sm text-gray-600"><span>Page {pagination.current_page} of {pagination.last_page}</span><div className="flex gap-2"><button disabled={page <= 1} onClick={() => setPage(page - 1)} className="rounded border px-3 py-1 disabled:opacity-40">Previous</button><button disabled={page >= pagination.last_page} onClick={() => setPage(page + 1)} className="rounded border px-3 py-1 disabled:opacity-40">Next</button></div></div>}
                </>}
            </section>
        </div>
    );
}
