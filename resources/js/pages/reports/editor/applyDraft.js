// Client-side mirror of App\Services\MonthlyReport\SectionMerger::merge().
// Used to compute the value editors should seed from: the section's
// generated `merged` data with the *in-progress, possibly-unsaved* draft
// overrides layered on top. Without this, switching away from a section and
// back (which remounts its editor) would re-seed from the stale `merged`
// value and silently discard unsaved edits — worse, the next edit would then
// push that stale snapshot back into `sectionDrafts`, overwriting the good
// draft. See SectionMerger.php for the authoritative (server-side) version.
function isPlainObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

export function applyDraft(merged, overrides) {
    if (!overrides || Object.keys(overrides).length === 0) {
        return merged;
    }

    const result = { ...(merged || {}) };
    const rest = { ...overrides };

    if (Object.prototype.hasOwnProperty.call(rest, '_rows') && Array.isArray(rest._rows)) {
        result.rows = rest._rows;
        delete rest._rows;
    }

    Object.keys(rest).forEach((key) => {
        if (!(key in result)) return;
        const value = rest[key];
        if (isPlainObject(value) && isPlainObject(result[key])) {
            result[key] = applyDraft(result[key], value);
        } else {
            result[key] = value;
        }
    });

    return result;
}

export default applyDraft;
