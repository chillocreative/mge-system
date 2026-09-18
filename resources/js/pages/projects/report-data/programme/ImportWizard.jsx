import { useRef, useState } from 'react';
import toast from 'react-hot-toast';
import reportDataService from '@/services/reportDataService';
import { HiOutlineX, HiOutlineUpload } from 'react-icons/hi';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

const XLSX_FIELDS = [
    { key: 'name', label: 'Task name', required: true },
    { key: 'duration', label: 'Duration' },
    { key: 'start', label: 'Start' },
    { key: 'finish', label: 'Finish' },
    { key: 'actual_pct', label: 'Actual %' },
    { key: 'plan_pct', label: 'Plan %' },
    { key: 'outline_level', label: 'Outline level' },
];

function stripExt(name) {
    if (!name) return '';
    const i = name.lastIndexOf('.');
    return i > 0 ? name.slice(0, i) : name;
}

// Import wizard for the Work Programme panel.
// mode="xlsx": two-step (upload -> preview/mapping -> import).
// mode="mspdi": single-step (upload MS Project XML directly).
export default function ImportWizard({ projectId, mode, onClose, onImported }) {
    const fileInputRef = useRef(null);
    const [step, setStep] = useState(1);
    const [uploading, setUploading] = useState(false);
    const [importing, setImporting] = useState(false);
    const [preview, setPreview] = useState(null);
    const [mapping, setMapping] = useState({});
    const [label, setLabel] = useState('');
    const [statusDate, setStatusDate] = useState('');
    const [setCurrent, setSetCurrent] = useState(true);
    const [mspdiFile, setMspdiFile] = useState(null);
    const [errors, setErrors] = useState({});

    const isMspdi = mode === 'mspdi';

    const onPickFile = () => fileInputRef.current?.click();

    const onXlsxFileSelected = async (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;
        setUploading(true);
        setErrors({});
        try {
            const res = await reportDataService.previewProgrammeXlsx(projectId, file);
            const data = res.data;
            setPreview(data);
            const suggested = data.suggested || {};
            const initialMapping = {};
            XLSX_FIELDS.forEach(({ key }) => {
                initialMapping[key] = suggested[key] ?? '';
            });
            setMapping(initialMapping);
            setLabel(stripExt(data.file_name));
            setStep(2);
        } catch (err) {
            toast.error(err.response?.data?.errors?.file?.[0] || err.response?.data?.message || 'Failed to read file');
        } finally {
            setUploading(false);
        }
    };

    const onMspdiFileSelected = (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;
        setMspdiFile(file);
        setLabel((l) => l || stripExt(file.name));
    };

    const setMappingField = (key) => (e) => {
        const v = e.target.value;
        setMapping((m) => ({ ...m, [key]: v === '' ? '' : Number(v) }));
    };

    const submitXlsx = async () => {
        if (mapping.name === '' || mapping.name === undefined) {
            toast.error('Task name column is required');
            return;
        }
        if (!label.trim()) {
            toast.error('Label is required');
            return;
        }
        setImporting(true);
        setErrors({});
        try {
            const payload = {
                token: preview.token,
                mapping: Object.fromEntries(
                    XLSX_FIELDS.map(({ key }) => [key, mapping[key] === '' || mapping[key] === undefined ? null : mapping[key]])
                ),
                label: label.trim(),
                status_date: statusDate || null,
                set_current: setCurrent,
                file_name: preview.file_name,
            };
            const res = await reportDataService.importProgrammeXlsx(projectId, payload);
            toast.success('Programme imported');
            onImported?.(res.data);
        } catch (err) {
            const respErrors = err.response?.data?.errors;
            if (respErrors) {
                setErrors(respErrors);
                const first = respErrors.file?.[0] || Object.values(respErrors.mapping || {})[0]?.[0];
                toast.error(first || err.response?.data?.message || 'Failed to import programme');
            } else {
                toast.error(err.response?.data?.message || 'Failed to import programme');
            }
        } finally {
            setImporting(false);
        }
    };

    const submitMspdi = async () => {
        if (!mspdiFile) {
            toast.error('Choose an MS Project XML file');
            return;
        }
        if (!label.trim()) {
            toast.error('Label is required');
            return;
        }
        setImporting(true);
        setErrors({});
        try {
            const res = await reportDataService.importProgrammeMspdi(projectId, mspdiFile, {
                label: label.trim(),
                status_date: statusDate || null,
                set_current: setCurrent ? 1 : 0,
            });
            toast.success('Programme imported');
            onImported?.(res.data);
        } catch (err) {
            const respErrors = err.response?.data?.errors;
            if (respErrors) {
                setErrors(respErrors);
                toast.error(respErrors.file?.[0] || err.response?.data?.message || 'Failed to import programme');
            } else {
                toast.error(err.response?.data?.message || 'Failed to import programme');
            }
        } finally {
            setImporting(false);
        }
    };

    const selectedColumns = new Set(Object.values(mapping).filter((v) => v !== '' && v !== undefined && v !== null));

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-6 shadow-lg">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-gray-900">
                        {isMspdi ? 'Import MS Project XML' : `Import Excel${step === 2 ? ' — map columns' : ''}`}
                    </h3>
                    <button type="button" onClick={onClose} className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                        <HiOutlineX className="h-4 w-4" />
                    </button>
                </div>

                {isMspdi ? (
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">File (.xml)</label>
                            <input ref={fileInputRef} type="file" accept=".xml" onChange={onMspdiFileSelected} className={input} />
                            {errors.file && <p className="mt-1 text-xs text-red-600">{errors.file[0]}</p>}
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Label</label>
                                <input value={label} onChange={(e) => setLabel(e.target.value)} className={input} />
                                {errors.label && <p className="mt-1 text-xs text-red-600">{errors.label[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Status date</label>
                                <input type="date" value={statusDate} onChange={(e) => setStatusDate(e.target.value)} className={input} />
                            </div>
                        </div>
                        <label className="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" checked={setCurrent} onChange={(e) => setSetCurrent(e.target.checked)} />
                            Set as current
                        </label>
                        <div className="flex justify-end gap-2 pt-2">
                            <button type="button" onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="button" onClick={submitMspdi} disabled={importing} className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                {importing ? 'Importing…' : 'Import'}
                            </button>
                        </div>
                    </div>
                ) : step === 1 ? (
                    <div className="space-y-4">
                        <p className="text-sm text-gray-600">Choose an Excel or CSV export of the work programme (Task Name, Duration, Start, Finish, % Complete, …).</p>
                        <input ref={fileInputRef} type="file" accept=".xlsx,.xls,.csv" onChange={onXlsxFileSelected} className={input} disabled={uploading} />
                        {uploading && <p className="text-xs text-gray-500">Reading file…</p>}
                        <div className="flex justify-end gap-2 pt-2">
                            <button type="button" onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            {XLSX_FIELDS.map(({ key, label: fLabel, required }) => (
                                <div key={key}>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">
                                        {fLabel}{required ? ' *' : ''}
                                    </label>
                                    <select value={mapping[key] ?? ''} onChange={setMappingField(key)} className={input}>
                                        <option value="">{required ? 'Select column…' : 'Not mapped'}</option>
                                        {(preview.headers || []).map((h, i) => (
                                            <option key={i} value={i}>{h || `Column ${i + 1}`}</option>
                                        ))}
                                    </select>
                                    {errors.mapping?.[key] && <p className="mt-1 text-xs text-red-600">{errors.mapping[key][0]}</p>}
                                </div>
                            ))}
                        </div>

                        <div>
                            <p className="mb-1 text-xs font-semibold uppercase text-gray-500">Sample ({(preview.sample || []).length} of {preview.row_count} rows)</p>
                            <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
                                <table className="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            {(preview.headers || []).map((h, i) => (
                                                <th key={i} className={`px-2 py-1.5 text-left font-semibold uppercase ${selectedColumns.has(i) ? 'bg-primary-50 text-primary-700' : 'text-gray-500'}`}>
                                                    {h || `Col ${i + 1}`}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {(preview.sample || []).map((row, ri) => (
                                            <tr key={ri}>
                                                {(preview.headers || []).map((_, i) => (
                                                    <td key={i} className={`px-2 py-1.5 ${selectedColumns.has(i) ? 'bg-primary-50/50' : ''}`}>{row[i] ?? ''}</td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Label</label>
                                <input value={label} onChange={(e) => setLabel(e.target.value)} className={input} />
                                {errors.label && <p className="mt-1 text-xs text-red-600">{errors.label[0]}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Status date</label>
                                <input type="date" value={statusDate} onChange={(e) => setStatusDate(e.target.value)} className={input} />
                            </div>
                        </div>
                        <label className="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" checked={setCurrent} onChange={(e) => setSetCurrent(e.target.checked)} />
                            Set as current
                        </label>

                        <div className="flex justify-end gap-2 pt-2">
                            <button type="button" onClick={() => { setStep(1); setPreview(null); }} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back</button>
                            <button type="button" onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="button" onClick={submitXlsx} disabled={importing} className="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
                                <HiOutlineUpload className="h-4 w-4" /> {importing ? 'Importing…' : 'Import'}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
