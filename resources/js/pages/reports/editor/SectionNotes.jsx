const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

// Notes on a report section. Not part of the section's `overrides` payload —
// saved via the `notes` field on the same PUT /monthly-reports/{id}/sections/{key}
// request. The PDF prints this note under the section.
export default function SectionNotes({ value, onChange, disabled }) {
    return (
        <div className="mt-4 border-t border-gray-100 pt-4">
            <label className="mb-1 block text-sm font-medium text-gray-700">Note (printed under this section in the PDF)</label>
            <textarea
                value={value || ''}
                onChange={(e) => onChange(e.target.value)}
                disabled={disabled}
                rows={2}
                placeholder="This note is printed under this section in the PDF"
                className={`${input} disabled:bg-gray-100`}
            />
        </div>
    );
}
