import SiteMemo from './layouts/SiteMemo';
import EngineeringInstruction from './layouts/EngineeringInstruction';
import PermitToWork from './layouts/PermitToWork';
import DailySiteDiary from './layouts/DailySiteDiary';
import MaterialApproval from './layouts/MaterialApproval';
import Ncr from './layouts/Ncr';
import Rfi from './layouts/Rfi';
import Rfwi from './layouts/Rfwi';

/**
 * Metadata for every Site Form type. `slug` is the URL segment used at
 * `/forms/:type`; `type` is the backend `form_type` value (SiteForm::TYPES).
 *
 * `Layout` is left `null` for types not yet built as a paper layout — the
 * lead / a follow-up work order fills those in (Permit To Work, Daily Site
 * Diary, Material Approval, NCR).
 */
export const formTypes = [
    {
        slug: 'site-memo',
        type: 'site_memo',
        title: 'SITE MEMO',
        subtitle: '',
        docNo: 'MGE/FORM/GF/4',
        revision: 1,
        effectiveDate: '01 September 2018',
        pages: 1,
        labels: 'gf',
        Layout: SiteMemo,
    },
    {
        slug: 'engineering-instruction',
        type: 'engineering_instruction',
        title: 'ARAHAN KEJURUTERAAN',
        subtitle: '(ENGINEERING INSTRUCTION - EI)',
        docNo: 'MGE/FORM/GF/5',
        revision: 1,
        effectiveDate: '17 September 2026',
        pages: 1,
        labels: 'gf',
        Layout: EngineeringInstruction,
    },
    {
        slug: 'permit-to-work',
        type: 'permit_to_work',
        title: 'PERMIT UNTUK BEKERJA',
        subtitle: '(PERMIT TO WORK - PTW)',
        docNo: 'MGE/FORM/GF/7',
        revision: 0,
        effectiveDate: '17 September 2026',
        pages: 2,
        labels: 'gf',
        Layout: PermitToWork,
    },
    {
        slug: 'daily-site-diary',
        type: 'daily_site_diary',
        title: 'BORANG HARIAN TAPAK BINA (BHTB)',
        subtitle: 'Daily Site Diary',
        docNo: 'MGE-QF-01',
        revision: 0,
        effectiveDate: '17 Sept 2026',
        pages: 3,
        labels: 'qf',
        Layout: DailySiteDiary,
    },
    {
        slug: 'material-approval',
        type: 'material_approval',
        title: 'BORANG KELULUSAN BAHAN',
        subtitle: 'Request for Approval (RFA)',
        docNo: 'MGE-QF-02',
        revision: 0,
        effectiveDate: '17 Sept 2026',
        pages: 1,
        labels: 'qf',
        Layout: MaterialApproval,
    },
    {
        slug: 'ncr',
        type: 'ncr',
        title: 'LAPORAN KETIDAKAKURAN',
        subtitle: '(NON-CONFORMANCE REPORT - NCR)',
        subtitle2: 'To Sub-Contractor / Supplier',
        docNo: 'MGE-QF-03',
        revision: 0,
        effectiveDate: '17 Sept 2026',
        pages: 1,
        labels: 'qf',
        Layout: Ncr,
    },
    {
        slug: 'rfi',
        type: 'rfi',
        title: 'PERMOHONAN MAKLUMAT',
        subtitle: '(REQUEST FOR INFORMATION - RFI)',
        docNo: 'MGE-QF-04',
        revision: 0,
        effectiveDate: '17 Sept 2026',
        pages: 1,
        labels: 'qf',
        Layout: Rfi,
    },
    {
        slug: 'rfwi',
        type: 'rfwi',
        title: 'PERMOHONAN PEMERIKSAAN KERJA',
        subtitle: '(REQUEST FOR WORK INSPECTION - RFWI)',
        docNo: 'MGE-QF-05',
        revision: 0,
        effectiveDate: '17 Sept 2026',
        pages: 1,
        labels: 'qf',
        Layout: Rfwi,
    },
];

export const bySlug = formTypes.reduce((acc, meta) => {
    acc[meta.slug] = meta;
    return acc;
}, {});

export default formTypes;
