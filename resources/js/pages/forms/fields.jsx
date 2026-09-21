import { createContext, useContext, useRef, useEffect, useCallback } from 'react';
import siteFormService from '@/services/siteFormService';
import { HiOutlineX, HiOutlinePhotograph } from 'react-icons/hi';

/**
 * FormDataContext — a tiny controlled-data layer for the site-form paper
 * layouts. `get`/`set` operate on dot-paths against a single `data` object.
 *
 * Paths starting with `$.` are routed to the promoted record columns
 * (ref_no, form_date, title) via `recordField`, everything else reads/writes
 * inside the free-form `data` JSON blob.
 */
const FormDataContext = createContext(null);

function getByPath(obj, path) {
    if (!path) return undefined;
    const segments = path.split('.');
    let cur = obj;
    for (const seg of segments) {
        if (cur == null) return undefined;
        cur = cur[seg];
    }
    return cur;
}

function setByPath(obj, path, value) {
    const segments = path.split('.');
    const root = Array.isArray(obj) ? [...obj] : { ...(obj || {}) };
    let cur = root;
    for (let i = 0; i < segments.length - 1; i++) {
        const seg = segments[i];
        const nextSeg = segments[i + 1];
        const isArrayIndex = /^\d+$/.test(nextSeg);
        const existing = cur[seg];
        const cloned = existing != null
            ? (Array.isArray(existing) ? [...existing] : { ...existing })
            : (isArrayIndex ? [] : {});
        cur[seg] = cloned;
        cur = cloned;
    }
    cur[segments[segments.length - 1]] = value;
    return root;
}

/**
 * Provider: `data` is the SiteForm's free-form JSON blob, `onChange(nextData)`
 * is called with the updated blob on every `set`. `record` + `onRecordChange`
 * let `$.`-prefixed paths read/write promoted columns (ref_no, form_date, title).
 */
export function FormDataProvider({ data, onChange, record, onRecordChange, children }) {
    const get = useCallback((path) => {
        if (path?.startsWith('$.')) return getByPath(record, path.slice(2));
        return getByPath(data, path);
    }, [data, record]);

    const set = useCallback((path, value) => {
        if (path?.startsWith('$.')) {
            onRecordChange?.(setByPath(record, path.slice(2), value));
            return;
        }
        onChange?.(setByPath(data, path, value));
    }, [data, record, onChange, onRecordChange]);

    return (
        <FormDataContext.Provider value={{ data, get, set }}>
            {children}
        </FormDataContext.Provider>
    );
}

export function useFormData() {
    const ctx = useContext(FormDataContext);
    if (!ctx) throw new Error('useFormData must be used within a FormDataProvider');
    return ctx;
}

const baseInputClass = 'w-full min-w-[3rem] flex-1 bg-transparent outline-none text-[11px] px-1 py-0.5 focus:bg-yellow-50';

export function Field({ path, className = '', placeholder = '', type = 'text', align = 'left' }) {
    const { get, set } = useFormData();
    const value = get(path) ?? '';
    const filled = value !== '' && value !== null && value !== undefined;
    const alignClass = align === 'right' ? 'text-right' : align === 'center' ? 'text-center' : 'text-left';

    return (
        <input
            type={type}
            value={value}
            placeholder={placeholder}
            onChange={(e) => set(path, e.target.value)}
            className={`${baseInputClass} ${alignClass} ${filled ? 'border-transparent' : 'border-b border-dotted border-gray-400'} ${className}`}
        />
    );
}

export function Area({ path, rows = 3, className = '' }) {
    const { get, set } = useFormData();
    const value = get(path) ?? '';
    const filled = value !== '' && value !== null && value !== undefined;
    const ref = useRef(null);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = `${el.scrollHeight}px`;
    }, [value]);

    return (
        <textarea
            ref={ref}
            rows={rows}
            value={value}
            onChange={(e) => set(path, e.target.value)}
            className={`${baseInputClass} resize-none overflow-hidden ${filled ? 'border-transparent' : 'border-b border-dotted border-gray-400'} ${className}`}
        />
    );
}

export function Check({ path, label }) {
    const { get, set } = useFormData();
    const checked = !!get(path);

    const toggle = () => set(path, !checked);
    const onKeyDown = (e) => {
        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            toggle();
        }
    };

    return (
        <span
            role="checkbox"
            aria-checked={checked}
            tabIndex={0}
            onClick={toggle}
            onKeyDown={onKeyDown}
            className="inline-flex cursor-pointer items-center gap-1 select-none"
        >
            <span className="text-[13px] leading-none">{checked ? '☑' : '☐'}</span>
            <span className="whitespace-nowrap">{label}</span>
        </span>
    );
}

export function SignBlock({ base, fields = ['Nama', 'Jawatan', 'Tarikh'], line = true, inline = false }) {
    if (inline) {
        return (
            <table className="w-full border-collapse text-[11px]">
                <tbody>
                    <tr>
                        {fields.map((label) => (
                            <td key={label} className="border border-black px-1.5 py-1 align-top">
                                <span className="whitespace-nowrap font-medium">{label} :</span>{' '}
                                <Field path={`${base}.${label.toLowerCase().replace(/[^a-z0-9]+/g, '_')}`} />
                            </td>
                        ))}
                    </tr>
                </tbody>
            </table>
        );
    }

    return (
        <div className="space-y-1">
            {line && <div className="mt-4 mb-1 h-px w-2/3 bg-gray-400" />}
            {fields.map((label) => (
                <div key={label} className="flex items-baseline gap-1">
                    <span className="whitespace-nowrap font-medium">{label} :</span>
                    <Field path={`${base}.${label.toLowerCase().replace(/[^a-z0-9]+/g, '_')}`} />
                </div>
            ))}
        </div>
    );
}

export function Cell({ children, colSpan, rowSpan, className = '', head = false }) {
    return (
        <td
            colSpan={colSpan}
            rowSpan={rowSpan}
            className={`border border-black px-1.5 py-1 align-top ${head ? 'bg-gray-100 font-semibold' : ''} ${className}`}
        >
            {children}
        </td>
    );
}

export function Section({ children }) {
    return (
        <tr>
            <td colSpan={100} className="border border-black bg-gray-50 px-1.5 py-1 font-bold uppercase">
                {children}
            </td>
        </tr>
    );
}

export function Table({ children, className = '' }) {
    return (
        <table className={`w-full border-collapse border border-black text-[11px] ${className}`}>
            <tbody>{children}</tbody>
        </table>
    );
}

export function PhotoSlot({ n, record, onUploaded }) {
    const slot = `photo_${n}`;
    const attachment = record?.attachments?.find((a) => a.slot === slot);

    const onFile = async (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file || !record?.id) return;
        try {
            await siteFormService.uploadAttachment(record.id, slot, file);
            onUploaded?.();
        } catch { /* surfaced by caller's list/state refresh */ }
    };

    const removePhoto = async () => {
        if (!attachment) return;
        try {
            await siteFormService.removeAttachment(attachment.id);
            onUploaded?.();
        } catch { /* ignore */ }
    };

    if (!record?.id) {
        return (
            <div className="flex aspect-[4/3] w-full items-center justify-center rounded border border-dashed border-gray-400 bg-gray-50 text-center text-[10px] text-gray-400">
                Save form first to add photos
            </div>
        );
    }

    if (attachment) {
        return (
            <div className="relative aspect-[4/3] w-full overflow-hidden rounded border border-gray-400 bg-gray-50">
                <img src={siteFormService.attachmentUrl(attachment.id)} alt={slot} className="h-full w-full object-cover" />
                <button
                    type="button"
                    onClick={removePhoto}
                    title="Remove photo"
                    className="absolute right-1 top-1 rounded-full bg-white/90 p-0.5 text-gray-600 shadow hover:bg-red-50 hover:text-red-600"
                >
                    <HiOutlineX className="h-3.5 w-3.5" />
                </button>
            </div>
        );
    }

    return (
        <label className="flex aspect-[4/3] w-full cursor-pointer flex-col items-center justify-center gap-1 rounded border border-dashed border-gray-400 bg-gray-50 text-center text-[10px] text-gray-400 hover:border-primary-400 hover:text-primary-500">
            <HiOutlinePhotograph className="h-6 w-6" />
            <span className="px-2">Tampal / Sisip Gambar &mdash; Insert Photo Here</span>
            <input type="file" accept="image/*" className="hidden" onChange={onFile} />
        </label>
    );
}
