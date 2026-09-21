import FormPaper from '../FormPaper';
import { Table, Cell, Check, Field, Area } from '../fields';

const workTypes = [
    'Survey Works', 'Dilapidation Survey', 'Site Investigation', 'Site Clearance',
    'Earthwork', 'Grouting Work', 'Pipe Jacking Works', 'Geotechnical Works',
    'Rebar Works', 'Formworks', 'Concrete Works', 'Drainage Works',
    'Demolition Works', 'Road Works', 'Ujian Tapak / Ujian Lab',
];

const slugify = (label) => label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');

export default function Rfwi({ meta }) {
    return (
        <FormPaper meta={meta} page={1} totalPages={meta.pages}>
            <div className="space-y-2">
                <Table>
                    <tr>
                        <Cell head className="w-1/2">Kepada (To):</Cell>
                        <Cell head className="w-1/2">Disediakan Oleh (Prepared By):</Cell>
                    </tr>
                    <tr>
                        <Cell><Area path="kepada" rows={3} /></Cell>
                        <Cell>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Nama :</span><Field path="prepared_by.nama" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Jawatan (Position) :</span><Field path="prepared_by.jawatan" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tarikh (Date) :</span><Field path="$.form_date" type="date" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tandatangan (Signature) :</span><Field path="prepared_by.tandatangan" /></div>
                        </Cell>
                    </tr>

                    <tr>
                        <Cell colSpan={2}>
                            <p className="mb-1 font-semibold">1. PIHAK KAMI INGIN MENARIK PERHATIAN PIHAK TUAN UNTUK (We wish to bring to your attention for): Kehadiran (Attendance):</p>
                            <div className="flex flex-wrap items-start gap-x-6 gap-y-1">
                                <Check path="attention.attendance" label="Kehadiran (Attendance)" />
                                <Check path="attention.document_review" label="Penyemakan Dokumen (Document Review)" />
                                <Check path="attention.information_detail" label="Perincian Maklumat (Information Detail)" />
                            </div>
                            <div className="mt-1 flex flex-wrap items-baseline gap-x-6 gap-y-1">
                                <div className="flex items-baseline gap-1">
                                    <Check path="attention.full_time" label="Sepenuh Masa (Full Time)" />
                                    <span className="ml-1 whitespace-nowrap">Masa (Time) :</span>
                                    <Field path="attention.time" />
                                </div>
                                <div className="flex flex-wrap items-baseline gap-1">
                                    <Check path="attention.witness_at_location" label="Saksi Lokasi (Witness at Location)" />
                                    <span className="ml-1 whitespace-nowrap">Tarikh Semakan (Inspection Date) :</span>
                                    <Field path="attention.inspection_date" type="date" />
                                    <span className="ml-1 whitespace-nowrap">Lokasi (Location) :</span>
                                    <Field path="attention.location" />
                                </div>
                            </div>
                            <p className="mt-1">Permohonan: Pihak kontraktor perlu memaklumkan kepada pihak yang berkenaan dalam tempoh masa tidak kurang daripada 24 jam</p>
                        </Cell>
                    </tr>

                    <tr>
                        <Cell colSpan={2} className="font-semibold">2. JENIS-JENIS KERJA (TYPES OF WORK)</Cell>
                    </tr>
                    <tr>
                        <Cell colSpan={2}>
                            <div className="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-4">
                                {workTypes.map((label) => (
                                    <Check key={label} path={`work_types.${slugify(label)}`} label={label} />
                                ))}
                                <div className="flex items-baseline gap-1 sm:col-span-4">
                                    <Check path="work_types.lain_lain" label="Lain-lain (Others):" />
                                    <Field path="work_types.lain_lain_text" />
                                </div>
                            </div>
                        </Cell>
                    </tr>

                    <tr>
                        <Cell colSpan={2}>
                            <p className="mb-1 font-semibold">3. PERINCIAN MAKLUMAT MENGENAI KERJA-KERJA YANG MEMERLUKAN PEMERIKSAAN (Details of Works Requiring Inspection)</p>
                            <div className="mb-1 flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tajuk (Title) :</span><Field path="$.title" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Keterangan (Details) :</span></div>
                            <Area path="details" rows={3} />
                            <Check path="details.attachment" label="Keterangan Lanjut Dilampirkan Bersama (Details as Attachment)" />
                            <p className="mt-1 text-[10px] italic">* Penyediaan Laporan Penyemakan: Kerani Tapak perlu mengemaskini maklumat tersebut.</p>
                        </Cell>
                    </tr>

                    <tr>
                        <Cell colSpan={2}>
                            <p className="mb-1 font-semibold">4. DIPOHON OLEH (Requested by):</p>
                            <div className="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                                <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Nama :</span><Field path="requested_by.nama" /></div>
                                <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Jawatan :</span><Field path="requested_by.jawatan" placeholder="QAQC" /></div>
                                <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tarikh :</span><Field path="requested_by.tarikh" type="date" /></div>
                                <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tandatangan :</span><Field path="requested_by.tandatangan" /></div>
                            </div>
                        </Cell>
                    </tr>

                    <tr>
                        <Cell colSpan={2}>
                            <p className="mb-1 font-semibold">5. KEPUTUSAN PEMERIKSAAN PERUNDING / PEMILIK (INSPECTION DECISION)</p>
                            <div className="mb-1 flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">KETERANGAN (Details) :</span></div>
                            <p className="mb-1 text-[10.5px]">Sila nyatakan sebab dan alasan permohonan ditolak berserta rujukan seperti spesifikasi, lukisan pembinaan atau lain-lain maklumat yang berkaitan dengan kerja-kerja tersebut:</p>
                            <Area path="decision.details" rows={3} />
                            <div className="mt-1 flex flex-wrap gap-4">
                                <Check path="decision.accepted" label="Terima (Accepted)" />
                                <Check path="decision.rejected" label="Ditolak (Rejected)" />
                                <Check path="decision.postponed" label="Ditangguhkan (Postponed)" />
                            </div>
                        </Cell>
                    </tr>

                    <tr>
                        <Cell colSpan={2}>
                            <p className="mb-1 font-semibold">6. PENGESAHAN KLIEN / PERUNDING (Confirmation Client / Consultant):</p>
                            <div className="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                                <div className="flex items-baseline gap-1"><span className="whitespace-nowrap font-medium">Tarikh Pengesahan (Date Confirmed) :</span><Field path="confirmation.tarikh" type="date" /></div>
                                <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Nama (Name) :</span><Field path="confirmation.nama" /></div>
                                <div className="flex items-baseline gap-1"><span className="whitespace-nowrap font-medium">Jawatan (Position) :</span><Field path="confirmation.jawatan" /></div>
                                <div className="flex items-baseline gap-1"><span className="whitespace-nowrap font-medium">Tandatangan (Signature) :</span><Field path="confirmation.tandatangan" /></div>
                            </div>
                        </Cell>
                    </tr>
                </Table>

                <p className="mt-1 text-[10px] italic text-gray-600">Nota: **Potong yang tidak berkenaan (Strike out whichever is not applicable)</p>
            </div>
        </FormPaper>
    );
}
