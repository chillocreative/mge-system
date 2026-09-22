import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Navigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import usePermission from '@/hooks/usePermission';
import { useConfirm } from '@/context/ConfirmContext';
import siteFormService from '@/services/siteFormService';
import projectService from '@/services/projectService';
import LoadingSpinner from '@/components/LoadingSpinner';
import { formatDate } from '@/utils/date';
import { bySlug } from './formTypes';
import { FormDataProvider } from './fields';
import {
    HiOutlinePlus, HiOutlineSearch, HiOutlineDocumentText, HiOutlinePencil, HiOutlineTrash, HiOutlinePrinter, HiOutlineEye,
} from 'react-icons/hi';

const today = () => new Date().toISOString().split('T')[0];

const emptyRecord = () => ({
    id: null,
    ref_no: '',
    form_date: today(),
    title: '',
    status: 'draft',
    data: {},
    attachments: [],
});

export default function SiteFormPage() {
    const { type } = useParams();
    const meta = bySlug[type];
    const { can } = usePermission();
    const canEdit = can('projects.edit');
    const confirm = useConfirm();
    const topRef = useRef(null);

    const [projects, setProjects] = useState([]);
    const [projectId, setProjectId] = useState('');
    const [record, setRecord] = useState(emptyRecord());
    const [mode, setMode] = useState('edit');
    const [list, setList] = useState([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        projectService.list({ per_page: 100 }).then((r) => setProjects(r.data?.data || [])).catch(() => {});
    }, []);

    const fetchList = useCallback(async () => {
        if (!meta) return;
        setLoading(true);
        try {
            const params = { form_type: meta.type };
            if (projectId) params.project_id = projectId;
            if (search) params.search = search;
            const res = await siteFormService.list(params);
            setList(res.data?.data || []);
        } catch {
            setList([]);
        } finally {
            setLoading(false);
        }
    }, [meta, projectId, search]);

    useEffect(() => {
        const t = setTimeout(() => fetchList(), 300);
        return () => clearTimeout(t);
    }, [fetchList]);

    // Reset the working record when the form type changes.
    useEffect(() => {
        setRecord(emptyRecord());
        setProjectId('');
    }, [type]);

    const onProjectChange = async (value) => {
        setProjectId(value);
        if (record.id || !value || !meta) return; // never overwrite an existing record's header

        try {
            const project = projects.find((p) => String(p.id) === String(value))
                || (await projectService.get(value)).data;

            setRecord((prev) => ({
                ...prev,
                data: {
                    ...prev.data,
                    header: {
                        client: project.client?.company_name || '',
                        contractor: 'MULTI GREEN ENGINEERING SDN. BHD.',
                        project: project.name || '',
                        contract_no: project.latest_contract_no || '',
                    },
                },
            }));

            const refRes = await siteFormService.nextRef({ form_type: meta.type, project_id: value });
            setRecord((prev) => ({ ...prev, ref_no: refRes.data?.ref_no || prev.ref_no }));
        } catch {
            toast.error('Failed to prepare form for the selected project');
        }
    };

    const newForm = () => {
        setRecord(emptyRecord());
        setProjectId('');
        setMode('edit');
        topRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    const loadRecord = async (id, nextMode = 'edit') => {
        setLoading(true);
        try {
            const res = await siteFormService.get(id);
            const loaded = res.data;
            setRecord({
                id: loaded.id,
                ref_no: loaded.ref_no || '',
                form_date: loaded.form_date ? String(loaded.form_date).slice(0, 10) : today(),
                title: loaded.title || '',
                status: loaded.status || 'draft',
                data: loaded.data || {},
                attachments: loaded.attachments || [],
            });
            setProjectId(loaded.project_id ? String(loaded.project_id) : '');
            setMode(nextMode === 'view' || !canEdit ? 'view' : 'edit');
            topRef.current?.scrollIntoView({ behavior: 'smooth' });
        } catch {
            toast.error('Failed to load form');
        } finally {
            setLoading(false);
        }
    };

    const save = async () => {
        if (!meta) return;
        if (!projectId) {
            toast.error('Select a project first');
            return;
        }
        setSaving(true);
        try {
            const payload = {
                form_type: meta.type,
                project_id: projectId,
                ref_no: record.ref_no || meta.title,
                form_date: record.form_date || null,
                title: record.title || null,
                status: record.status || 'draft',
                data: record.data || {},
            };
            let res;
            if (record.id) res = await siteFormService.update(record.id, payload);
            else res = await siteFormService.create(payload);

            const saved = res.data;
            setRecord((prev) => ({ ...prev, id: saved.id, ref_no: saved.ref_no || prev.ref_no }));
            toast.success(record.id ? 'Form updated' : 'Form saved');
            fetchList();
        } catch (err) {
            if (err.response?.status === 422) {
                const errors = err.response.data?.errors || {};
                const firstError = Object.values(errors)[0]?.[0];
                toast.error(firstError || 'Please check the form for errors');
            } else {
                toast.error(err.response?.data?.message || 'Failed to save form');
            }
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        if (!record.id) return;
        if (!(await confirm({ title: 'Delete this form?', message: `Delete ${record.ref_no || 'this form'}? This cannot be undone.` }))) return;
        try {
            await siteFormService.remove(record.id);
            toast.success('Form deleted');
            newForm();
            fetchList();
        } catch {
            toast.error('Failed to delete form');
        }
    };

    if (!meta) {
        return <Navigate to="/forms/site-memo" replace />;
    }

    const Layout = meta.Layout;
    const readOnly = mode === 'view' || !canEdit;

    const setData = (nextData) => setRecord((prev) => ({ ...prev, data: nextData }));
    const setRecordFields = (nextRecord) => setRecord((prev) => ({ ...prev, ...nextRecord }));

    return (
        <div ref={topRef}>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <h1 className="text-2xl font-bold text-gray-900">{meta.title}{meta.subtitle ? ` ${meta.subtitle}` : ''}</h1>
                        {mode === 'view' && (
                            <span className="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-600">Viewing</span>
                        )}
                    </div>
                    <p className="text-sm text-gray-500">{meta.docNo} &middot; Rev {meta.revision}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <select
                        value={projectId}
                        onChange={(e) => onProjectChange(e.target.value)}
                        disabled={mode === 'view'}
                        className="rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100"
                    >
                        <option value="">Select project...</option>
                        {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </select>
                    <button
                        type="button"
                        onClick={newForm}
                        className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        <HiOutlinePlus className="h-5 w-5" /> New
                    </button>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        <HiOutlinePrinter className="h-5 w-5" /> Print
                    </button>
                    {mode === 'view' && canEdit && (
                        <button
                            type="button"
                            onClick={() => setMode('edit')}
                            className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700"
                        >
                            <HiOutlinePencil className="h-5 w-5" /> Edit
                        </button>
                    )}
                    {mode === 'edit' && canEdit && (
                        <button
                            type="button"
                            onClick={save}
                            disabled={saving}
                            className="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-50"
                        >
                            {saving ? 'Saving...' : 'Save'}
                        </button>
                    )}
                    {canEdit && record.id && (
                        <button
                            type="button"
                            onClick={remove}
                            className="inline-flex items-center gap-2 rounded-lg border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50"
                        >
                            <HiOutlineTrash className="h-5 w-5" /> Delete
                        </button>
                    )}
                </div>
            </div>
            {!projectId && mode !== 'view' && (
                <p className="mb-4 text-sm text-amber-600">Select a project to save this form.</p>
            )}

            <div className="site-form-print-area">
                <FormDataProvider data={record.data} onChange={setData} record={record} onRecordChange={setRecordFields} readOnly={readOnly}>
                    <Layout meta={meta} record={record} setRecord={setRecordFields} onReload={() => record.id && loadRecord(record.id, mode)} />
                </FormDataProvider>
            </div>

            <div className="mt-8 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-sm font-bold uppercase tracking-wide text-gray-500">Saved {meta.title.toLowerCase()} forms</h2>
                    <div className="relative max-w-xs">
                        <HiOutlineSearch className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search ref no / title..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        />
                    </div>
                </div>

                {loading ? <LoadingSpinner /> : list.length === 0 ? (
                    <div className="py-10 text-center">
                        <HiOutlineDocumentText className="mx-auto h-10 w-10 text-gray-300" />
                        <p className="mt-2 text-sm text-gray-500">No forms found</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Ref No</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Prepared by</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Updated</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {list.map((f) => (
                                    <tr key={f.id} className="cursor-pointer hover:bg-gray-50" onClick={() => loadRecord(f.id, 'view')}>
                                        <td className="px-3 py-2 text-sm font-medium text-gray-900">{f.ref_no || '-'}</td>
                                        <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{formatDate(f.form_date)}</td>
                                        <td className="px-3 py-2 text-sm text-gray-600">{f.title || '-'}</td>
                                        <td className="px-3 py-2 text-sm text-gray-600">{f.project?.name || '-'}</td>
                                        <td className="px-3 py-2 text-sm text-gray-600">{f.creator ? `${f.creator.first_name || ''} ${f.creator.last_name || ''}`.trim() : '-'}</td>
                                        <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{formatDate(f.updated_at)}</td>
                                        <td className="whitespace-nowrap px-3 py-2 text-right" onClick={(e) => e.stopPropagation()}>
                                            <div className="flex items-center justify-end gap-1">
                                                <button onClick={() => loadRecord(f.id, 'view')} title="View" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                                    <HiOutlineEye className="h-4 w-4" />
                                                </button>
                                                {canEdit && (
                                                    <button onClick={() => loadRecord(f.id, 'edit')} title="Edit" className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                                        <HiOutlinePencil className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {canEdit && (
                                                    <button
                                                        onClick={async () => {
                                                            if (!(await confirm({ title: 'Delete form?', message: `Delete ${f.ref_no || `#${f.id}`}?` }))) return;
                                                            try {
                                                                await siteFormService.remove(f.id);
                                                                toast.success('Form deleted');
                                                                if (record.id === f.id) newForm();
                                                                fetchList();
                                                            } catch {
                                                                toast.error('Failed to delete form');
                                                            }
                                                        }}
                                                        title="Delete"
                                                        className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                    >
                                                        <HiOutlineTrash className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}

