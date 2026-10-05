import FormPaper from '../FormPaper';
import { Table, Cell, Check, Field, Area, SignBlock } from '../fields';

export default function Rfi({ meta }) {
    return (
        <FormPaper meta={meta} page={1} totalPages={meta.pages}>
            <Table className="text-[10px] [&_td]:!py-0.5">
                <tr>
                    <Cell className="w-1/2"><b>KEPADA (To):</b><Field path="kepada" /></Cell>
                    <Cell className="w-1/2"><b>TARIKH (Date):</b><Field path="$.form_date" type="date" /></Cell>
                </tr>
                <tr><Cell colSpan={2}>
                    <p className="font-semibold">1. PIHAK KAMI INGIN MENARIK PERHATIAN PIHAK TUAN UNTUK (We wish to bring to your attention for):</p>
                    <div className="flex flex-wrap gap-x-5"><Check path="attention.information_detail" label="Perincian Maklumat (Information Detail)" /><Check path="attention.document_review" label="Penyemakan Dokumen (Document Review)" /></div>
                </Cell></tr>
                <tr><Cell colSpan={2}>
                    <p className="font-semibold">2. PERMOHONAN MAKLUMAT / SEMAKAN DOKUMEN (Request for Information / Document Review)</p>
                    <Field path="$.title" />
                </Cell></tr>
                <tr><Cell colSpan={2}>
                    <p className="font-semibold">3. KETERANGAN (Description / Detail):</p>
                    <Area path="description" rows={4} bounded className="max-h-24" />
                    <Check path="attachment.details" label="Keterangan Lanjut Dilampirkan Bersama (Details as Attachment)" />
                </Cell></tr>
                <tr>
                    <Cell className="w-1/2"><b>5. RUJUKAN LUKISAN / DOKUMEN (Ref. Dwg. / Document):</b><Field path="ref_dwg" /></Cell>
                    <Cell className="w-1/2"><b>6. RUJUKAN SPESIFIKASI / BQ (Ref. Spec / BQ.):</b><Field path="ref_spec" /></Cell>
                </tr>
                <tr><Cell colSpan={2}>
                    <p className="font-semibold">7. DIPOHON OLEH (Requested by):</p>
                    <SignBlock base="requested_by" fields={['Nama', 'Jawatan', 'Tarikh', 'Tandatangan']} inline />
                </Cell></tr>
                <tr><Cell colSpan={2}>
                    <p className="font-semibold">8. MAKLUM BALAS PERUNDING / ARKITEK (Consultant / Architect Response)</p>
                    <Area path="consultant_response" rows={4} bounded className="max-h-24" />
                </Cell></tr>
                <tr><Cell colSpan={2}>
                    <p className="font-semibold">9. PENGESAHAN PEMILIK / PERUNDING (Confirmation Client / Consultant):</p>
                    <SignBlock base="confirmation" fields={['Nama', 'Jawatan', 'Tarikh', 'Tandatangan']} inline />
                </Cell></tr>
            </Table>
            <p className="mt-0.5 text-[9px] italic">Nota: **Potong yang tidak berkenaan (Strike out whichever is not applicable)</p>
        </FormPaper>
    );
}
