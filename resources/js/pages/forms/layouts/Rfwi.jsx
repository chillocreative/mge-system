import FormPaper from '../FormPaper';
import { Table, Cell, Check, Field, Area, SignBlock } from '../fields';

const workTypes = [
    'Survey Works', 'Dilapidation Survey', 'Site Investigation', 'Site Clearance',
    'Earthwork', 'Grouting Work', 'Pipe Jacking Works', 'Geotechnical Works',
    'Rebar Works', 'Formworks', 'Concrete Works', 'Drainage Works',
    'Demolition Works', 'Road Works', 'Ujian Tapak', 'Ujian Lab',
];
const slugify = (label) => label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');

export default function Rfwi({ meta }) {
    return (
        <FormPaper meta={meta} page={1} totalPages={meta.pages}>
            <Table className="text-[10px] [&_td]:!py-0.5 [&_[role=checkbox]>span:last-child]:whitespace-normal">
                <tr>
                    <Cell className="w-1/2"><b>KEPADA (To):</b><Field path="kepada" /></Cell>
                    <Cell className="w-1/2"><b>TARIKH (Date):</b><Field path="$.form_date" type="date" /></Cell>
                </tr>
                <tr><Cell colSpan={2}>
                    <p className="font-semibold">1. PIHAK KAMI INGIN MENARIK PERHATIAN PIHAK TUAN UNTUK (We wish to bring to your attention for):</p>
                    <div className="flex flex-wrap gap-x-4"><Check path="attention.attendance" label="Kehadiran (Attendance)" /><Check path="attention.witness" label="Saksi / Wakil (Witness)" /></div>
                    <div className="flex flex-wrap gap-x-4"><span>Tujuan (Purposed):</span><Check path="purpose.inspection" label="Penyemakan (Inspection)" /><Check path="purpose.testing" label="Ujian (Testing)" /></div>
                    <div className="flex flex-wrap gap-x-3"><span>Kehadiran (Attendance):</span><Check path="attention.full_time" label="Sepenuh Masa (Full Time)" /></div>
                    <div className="grid grid-cols-3 gap-2">
                        <div>Tarikh (Date):<Field path="attention.inspection_date" type="date" /></div>
                        <div>Masa (Time):<Field path="attention.time" /></div>
                        <div>Lokasi (Location):<Field path="attention.location" /></div>
                    </div>
                    <p className="text-[9px]">Permohonan: Pihak kontraktor perlu memaklumkan kepada pihak yang berkenaan dalam tempoh masa tidak kurang daripada 24 jam</p>
                </Cell></tr>
                <tr><Cell colSpan={2}>
                    <p className="font-semibold">2. JENIS-JENIS KERJA (TYPES OF WORK)</p>
                    <div className="grid grid-cols-4 gap-x-2 gap-y-0.5">
                        {workTypes.map((label) => <Check key={label} path={`work_types.${slugify(label)}`} label={label} />)}
                    </div>
                    <div className="flex items-center"><Check path="work_types.lain_lain" label="Lain-lain (Others):" /><Field path="work_types.lain_lain_text" /></div>
                </Cell></tr>
                <tr>
                    <Cell className="w-1/2"><b>3. DOKUMEN BERKAITAN (Related Form) – Dilampirkan bersama:</b><Field path="related_form" /></Cell>
                    <Cell className="w-1/2"><b>4. RUJUKAN LUKISAN (Drawing Ref.No.):</b><Field path="drawing_reference" /></Cell>
                </tr>
                <tr><Cell colSpan={2}>
                    <p className="font-semibold">5. KATEGORI / BAHAN &amp; JENIS UJIAN (Category / Material &amp; Test/Inspection Type)</p>
                    <div className="grid grid-cols-2 gap-2">
                        <div>Kategori / Bahan (Material):<Field path="category_material" /></div>
                        <div>Jenis Ujian / Pemeriksaan (Test/Inspection Type):<Field path="test_inspection_type" /></div>
                    </div>
                </Cell></tr>
                <tr><Cell colSpan={2}>
                    <p className="font-semibold">6. PERINCIAN MAKLUMAT MENGENAI KERJA-KERJA YANG MEMERLUKAN PEMERIKSAAN (Details of Works Requiring Inspection)</p>
                    <div className="flex items-center"><span className="shrink-0">Tajuk (Title):</span><Field path="$.title" /></div>
                    <span>Keterangan (Details):</span><Area path="details" rows={2} bounded className="max-h-12" />
                    <Check path="attachment.details" label="Keterangan Lanjut Dilampirkan Bersama (Details as Attachment)" />
                    <p className="text-[9px] italic">* Penyediaan Laporan Penyemakan: Kerani Tapak perlu mengemaskini maklumat tersebut.</p>
                </Cell></tr>
                <tr><Cell colSpan={2}><p className="font-semibold">7. DIPOHON OLEH (Requested by):</p><SignBlock base="requested_by" fields={['Nama', 'Jawatan', 'Tarikh', 'Tandatangan']} inline /></Cell></tr>
                <tr><Cell colSpan={2}>
                    <p className="font-semibold">8. KEPUTUSAN PEMERIKSAAN PERUNDING / PEMILIK (INSPECTION DECISION)</p>
                    <div className="flex flex-wrap gap-x-4"><Check path="decision.accepted" label="Mematuhi (Complied)" /><Check path="decision.rejected" label="Tidak Mematuhi (Not Complied)" /><Check path="decision.postponed" label="Ditangguhkan (Postponed)" /></div>
                    <p>KETERANGAN / SEBAB (Details / Reason):</p>
                    <Area path="decision.details" rows={2} bounded className="max-h-12" />
                </Cell></tr>
                <tr><Cell colSpan={2}><p className="font-semibold">9. PENGESAHAN KLIEN / PERUNDING (Confirmation Client / Consultant):</p><SignBlock base="confirmation" fields={['Nama', 'Jawatan', 'Tandatangan', 'Tarikh']} inline /></Cell></tr>
                <tr><Cell colSpan={2}><p className="font-semibold">10. MAKLUMAN KONTRAKTOR (Acknowledge Contractor):</p><SignBlock base="contractor_acknowledgement" fields={['Nama', 'Jawatan', 'Tandatangan', 'Tarikh']} inline /></Cell></tr>
            </Table>
            <p className="mt-0.5 text-[9px] italic">Nota: **Potong yang tidak berkenaan (Strike out whichever is not applicable)</p>
        </FormPaper>
    );
}
