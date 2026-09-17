import { useState } from 'react';
import toast from 'react-hot-toast';
import monthlyReportService from '@/services/monthlyReportService';
import { DEFAULT_LANDSCAPE } from './sectionConfig';

// Layout options dialog: pick which sections print landscape in the PDF.
// `report.options?.landscape_sections` is `null` (use server defaults) or an
// explicit list of section keys. The cover section is always portrait and is
// excluded from the list.
export default function ReportOptionsDialog({ report, onClose, onSaved }) {
    const sections = (report.sections || []).filter((s) => s.key !== 'cover');
    const [selected, setSelected] = useState(() => new Set(report.options?.landscape_sections ?? DEFAULT_LANDSCAPE));
    const [saving, setSaving] = useState(false);

    const toggle = (key) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(key)) next.delete(key);
            else next.add(key);
            return next;
        });
    };

    const save = async (landscapeSections) => {
        setSaving(true);
        try {
            const res = await monthlyReportService.update(report.id, {
                options: { ...(report.options || {}), landscape_sections: landscapeSections },
            });
            toast.success('Layout options saved');
            onSaved?.(res.data);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to save layout options');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
            onClick={() => !saving && onClose()}
        >
            <div
                className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="report-options-title"
            >
                <h3 id="report-options-title" className="text-base font-semibold text-gray-900">Layout options</h3>
                <p className="mt-1 text-sm text-gray-500">Choose which sections print in landscape orientation.</p>

                <ul className="mt-4 max-h-80 space-y-1 overflow-y-auto">
                    {sections.map((s) => (
                        <li key={s.key} className="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50">
                            <input
                                id={`landscape-${s.key}`}
                                type="checkbox"
                                checked={selected.has(s.key)}
                                onChange={() => toggle(s.key)}
                                disabled={saving}
                                className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                            <label htmlFor={`landscape-${s.key}`} className="flex-1 text-sm text-gray-700">
                                {s.title} <span className="text-gray-400">({s.key})</span>
                            </label>
                        </li>
                    ))}
                </ul>

                <div className="mt-6 flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        onClick={() => save(null)}
                        disabled={saving}
                        className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                        Reset to defaults
                    </button>
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={saving}
                        className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={() => save(Array.from(selected))}
                        disabled={saving}
                        className="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
                    >
                        {saving ? 'Saving…' : 'Save'}
                    </button>
                </div>
            </div>
        </div>
    );
}
