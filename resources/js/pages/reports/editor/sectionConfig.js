// Per-section editor configuration for the Monthly Progress Report editor.
//
// `type` selects which editor component renders the section
// (see MonthlyReportEditor.jsx): 'value' -> ValueSection, 'text' -> TextSection,
// 'image' -> ImageSection, 'chart' -> ChartSection, 'gantt' -> GanttAssetsPanel,
// everything else ('table', 'groups-table', 'groups-numbers', 'matrix',
// 'progress') -> TableSection, which dispatches internally on `type`.
//
// Table `columns` entries: { key, label, readOnly?, multiline?, type? }.
// `type` on a column is one of: 'text' (default), 'number', 'contacts'.
// Columns without `readOnly` are editable; the underlying (non-bold) system
// fields from section-shapes.md are marked readOnly.

// Sections that render landscape by default in the PDF when
// `report.options?.landscape_sections` is null — kept in sync with the
// server default (see MonthlyReportController / PDF template).
export const DEFAULT_LANDSCAPE = ['2.2', '2.3', '2.4', '2.5', '4.1', '4.2'];

export const SECTION_EDITORS = {
    cover: {
        type: 'value',
        fields: [{ key: 'project_title', label: 'Project Title' }],
        reportFields: [{ key: 'evaluation_date', label: 'Evaluation Date', type: 'date' }],
        signatories: true,
    },
    '1.1': {
        type: 'table',
        fixedRows: true,
        columns: [
            { key: 'label', label: 'Item', readOnly: true },
            { key: 'value', label: 'Value', multiline: true },
        ],
    },
    '1.2': {
        type: 'table',
        columns: [
            { key: 'party', label: 'Party', readOnly: true },
            { key: 'company', label: 'Company' },
            { key: 'address', label: 'Address', multiline: true },
            { key: 'contacts', label: 'Contacts', type: 'contacts' },
        ],
    },
    '1.3': { type: 'image', collection: 'images' },
    '1.4': { type: 'text' },
    '1.5': {
        type: 'table',
        columns: [
            { key: 'designation', label: 'Designation', readOnly: true },
            { key: 'nos', label: 'Nos.', type: 'number' },
        ],
    },
    '2.1': { type: 'progress' },
    '2.2': { type: 'chart', unit: '%' },
    '2.3': {
        type: 'table',
        columns: [
            { key: 'ipc_no', label: 'IPC No.', readOnly: true },
            { key: 'submission_date', label: 'Submission Date', readOnly: true },
            { key: 'evaluation_date', label: 'Evaluation Date', readOnly: true },
            { key: 'claim_amount', label: 'Claim Amount', type: 'number', readOnly: true },
            { key: 'certified', label: 'Certified', type: 'number', readOnly: true },
            { key: 'wjp_current', label: 'WJP (Current)', type: 'number', readOnly: true },
            { key: 'wjp_cumulative', label: 'WJP (Cumulative)', type: 'number', readOnly: true },
            { key: 'paid_current', label: 'Paid (Current)', type: 'number', readOnly: true },
            { key: 'paid_cumulative', label: 'Paid (Cumulative)', type: 'number', readOnly: true },
            { key: 'remarks', label: 'Remarks', multiline: true },
        ],
    },
    '2.4': { type: 'chart', unit: 'RM' },
    '2.5': { type: 'gantt' },
    '2.6': {
        type: 'table',
        columns: [
            { key: 'no', label: 'No.', readOnly: true },
            { key: 'title', label: 'Title' },
            { key: 'issue', label: 'Issue', multiline: true },
            { key: 'reg_number', label: 'Reg. Number', readOnly: true },
            { key: 'submitted', label: 'Submitted', readOnly: true },
            { key: 'reply', label: 'Reply', readOnly: true },
            { key: 'duration', label: 'Duration', readOnly: true },
            { key: 'status', label: 'Status', readOnly: true },
            { key: 'impact', label: 'Impact', multiline: true },
        ],
    },
    '3.1': { type: 'groups-numbers' },
    '3.2': {
        type: 'groups-table',
        columns: [
            { key: 'no', label: 'No.', readOnly: true },
            { key: 'reference', label: 'Reference', readOnly: true },
            { key: 'title', label: 'Title' },
            { key: 'issued', label: 'Issued', readOnly: true },
            { key: 'approved', label: 'Approved', readOnly: true },
            { key: 'reminder', label: 'Reminder', readOnly: true },
            { key: 'status', label: 'Status' },
        ],
    },
    '3.4': {
        type: 'table',
        columns: [
            { key: 'no', label: 'No.', readOnly: true },
            { key: 'ref_no', label: 'Ref. No.', readOnly: true },
            { key: 'name', label: 'Name', readOnly: true },
            { key: 'date', label: 'Date', readOnly: true },
            { key: 'result', label: 'Result', readOnly: true },
            { key: 'remarks', label: 'Remarks', multiline: true },
        ],
    },
    '3.6': {
        type: 'table',
        fixedRows: true,
        columns: [
            { key: 'no', label: 'No.', readOnly: true },
            { key: 'drawing_no', label: 'Drawing No.', readOnly: true },
            { key: 'title', label: 'Title', readOnly: true },
        ],
    },
    '3.7': {
        type: 'table',
        columns: [
            { key: 'no', label: 'No.', readOnly: true },
            { key: 'description', label: 'Description', multiline: true },
            { key: 'date', label: 'Date', readOnly: true },
            { key: 'location', label: 'Location' },
        ],
    },
    '4.1': { type: 'matrix', grouped: true },
    '4.2': { type: 'matrix', grouped: false },
    '4.3': { type: 'text' },
    '5.0': { type: 'image', collections: ['site_access', 'key_plan', 'pairs'] },
};

export default SECTION_EDITORS;
