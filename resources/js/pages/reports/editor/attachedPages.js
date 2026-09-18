// Maps section keys to the report-asset `kind` used for "attached pages"
// uploads (PDF/PNG/JPG pages that replace a generated chart/table in the
// PDF and Word exports). Shared by AttachedPagesPanel's callers
// (ChartSection for 2.2/2.4, the 2.5 Gantt host) and by
// MonthlyReportEditor's exportWord flow, which needs to know whether a
// section already has attached pages before snapshotting its generated
// chart.
const ATTACHED_PAGE_KINDS = {
    '2.2': 'scurve_physical_page',
    '2.4': 'scurve_financial_page',
    '2.5': 'gantt_page',
};

export default ATTACHED_PAGE_KINDS;
