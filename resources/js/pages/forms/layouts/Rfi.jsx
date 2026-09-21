import FormPaper from '../FormPaper';
import { Table, Cell, Check, Field, Area } from '../fields';

export default function Rfi({ meta }) {
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
                            <p className="mb-1 font-semibold">1. PIHAK KAMI INGIN MENARIK PERHATIAN PIHAK TUAN UNTUK (We wish to bring to your attention for):</p>
                            <div className="flex flex-wrap items-start gap-4">
                                <div className="flex items-baseline gap-1">
                                    <Check path="attention.information_detail" label="Perincian Maklumat (Information Detail)" />
                                    <span className="ml-1 whitespace-nowrap">Tarikh / Tempoh Semakan Dikehendaki (Date / Duration Reply Required) :</span>
                                    <Field path="attention.reply_required_by" />
                                </div>
                                <Check path="attention.document_review" label="Penyemakan Dokumen (Document Review)" />
                            </div>
                        </Cell>
                    </tr>

                    <tr>
                        <Cell colSpan={2}>
                            <p className="mb-1 font-semibold">2. PERMOHONAN MAKLUMAT / SEMAKAN DOKUMEN (Request for Information / Document Review)</p>
                            <Area path="$.title" rows={2} />
                        </Cell>
                    </tr>

                    <tr>
                        <Cell colSpan={2}>
                            <p className="mb-1 font-semibold">3. KETERANGAN (Description / Detail):</p>
                            <Area path="description" rows={4} />
                            <Check path="description.attachment" label="Keterangan Lanjut Dilampirkan Bersama (Details as Attachment)" />
                        </Cell>
                    </tr>

                    <tr>
                        <Cell className="w-1/3">
                            <span className="whitespace-nowrap font-medium">RUJUKAN LUKISAN (Ref. Dwg.) :</span>
                            <Field path="ref_dwg" />
                        </Cell>
                        <Cell className="w-1/3">
                            <span className="whitespace-nowrap font-medium">RUJUKAN SPESIFIKASI (Ref. Spec.) :</span>
                            <Field path="ref_spec" />
                        </Cell>
                    </tr>

                    <tr>
                        <Cell colSpan={2}>
                            <p className="mb-1 font-semibold">4. Dipohon Oleh (Requested by):</p>
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
                            <p className="mb-1 font-semibold">5. MAKLUM BALAS PERUNDING / ARKITEK (Consultant / Architect Response)</p>
                            <Area path="consultant_response" rows={4} />
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
