// Maps each report section key to the place its underlying data is entered,
// so the editor can point the user there when a section has nothing to show
// yet (see MonthlyReportEditor's empty-state card) or explain where its
// "system data" comes from. `to(projectId)` returns the deep-link path —
// see the project page's tab/panel query params (tabs: report-data,
// site-logs, documents, calendar; panels: particulars, parties, org-chart,
// progress, programme, registers, categories, images) and the standalone
// `/projects/contracts` and `/projects/correspondence` routes.
//
// Sections not listed here (e.g. `cover`) have no single external source to
// link to and are skipped by the empty-state card.

const parties = { label: 'Parties Involved', to: (p) => `/projects/${p}?tab=report-data&panel=parties` };
const images = { label: 'Site Images', to: (p) => `/projects/${p}?tab=report-data&panel=images` };
const orgChart = { label: 'Organisation Chart', to: (p) => `/projects/${p}?tab=report-data&panel=org-chart` };
const progress = { label: 'Progress', to: (p) => `/projects/${p}?tab=report-data&panel=progress` };
const programme = { label: 'Work Programme', to: (p) => `/projects/${p}?tab=report-data&panel=programme` };
const registers = { label: 'Registers', to: (p) => `/projects/${p}?tab=report-data&panel=registers` };
const correspondence = { label: 'Correspondence', to: () => '/projects/correspondence' };
const siteLogs = { label: 'Site Logs', to: (p) => `/projects/${p}?tab=site-logs` };

export const SECTION_SOURCES = {
    '1.1': { label: 'Contract Particulars', to: (p) => `/projects/${p}?tab=report-data&panel=particulars` },
    '1.2': parties,
    '1.3': images,
    '1.4': orgChart,
    '1.5': orgChart,
    '2.1': progress,
    '2.2': progress,
    '2.4': progress,
    '2.3': { label: 'Claims (Contracts)', to: (p) => `/projects/contracts` },
    '2.5': programme,
    '2.6': registers,
    '3.1': correspondence,
    '3.2': correspondence,
    '3.4': registers,
    '3.6': { label: 'Drawings (Documents)', to: (p) => `/projects/${p}?tab=documents` },
    '3.7': { label: 'Calendar', to: (p) => `/projects/${p}?tab=calendar` },
    '4.1': siteLogs,
    '4.2': siteLogs,
    '4.3': siteLogs,
    '5.0': images,
};

export default SECTION_SOURCES;
