import FormPaper from '../FormPaper';
import { Table, Cell, Section, Check, Field, Area, SignBlock } from '../fields';

export default function MaterialApproval({ meta }) {
    return (
        <FormPaper meta={meta} page={1} totalPages={meta.pages}>
            <Table className="text-[10px] [&_td]:!py-0.5 [&_[role=checkbox]>span:last-child]:whitespace-normal">
                <tr>
                    <Cell className="w-1/2"><span className="font-semibold">KEPADA (To):</span><Field path="kepada" /></Cell>
                    <Cell className="w-1/2"><span className="font-semibold">TARIKH (Date):</span><Field path="$.form_date" type="date" /></Cell>
                </tr>
                <tr><Cell colSpan={2}>
                    <span className="font-semibold">BIDANG KERJA (Type of Work): </span>
                    <div className="flex flex-wrap gap-x-3 gap-y-0.5">
                        <Check path="work_type.civil" label="Sivil" /><Check path="work_type.structural" label="Struktur" />
                        <Check path="work_type.architectural" label="Senibina" /><Check path="work_type.mechanical" label="Mekanikal" />
                        <Check path="work_type.electrical" label="Elektrik" /><Check path="work_type.other" label="Lain-lain:" /><Field path="work_type.other_text" />
                    </div>
                </Cell></tr>
                <Section>1. PERMOHONAN KELULUSAN (REQUEST FOR APPROVAL)</Section>
                <tr><Cell colSpan={2}>
                    <div className="flex flex-wrap gap-x-4 gap-y-0.5">
                        <Check path="request_for.sample" label="Sample" /><Check path="request_for.material" label="Material" />
                        <Check path="request_for.submittal" label="Submittal" /><Check path="request_for.mock_up" label="Mock-Up" />
                    </div>
                    <div className="flex items-center gap-2"><span className="shrink-0">Lokasi Mock-Up:</span><Field path="request_for.mock_up_location" /><Check path="request_for.mock_up_not_applicable" label="TIDAK BERKAITAN" /></div>
                </Cell></tr>
                <Section>2. JENIS PENYERAHAN (SUBMITTAL)</Section>
                <tr><Cell colSpan={2}>
                    <div className="flex flex-wrap gap-x-3 gap-y-0.5">
                        <Check path="submittal.method_statement" label="Method Statement" />
                        <Check path="submittal.report_test" label="Laporan / Ujian (Report / Test)" />
                        <Check path="submittal.drawing" label="Lukisan (Drawing)" />
                        <Check path="submittal.other" label="Lain-lain (Others):" /><Field path="submittal.other_text" />
                        <Check path="submittal.not_applicable" label="TIDAK BERKAITAN" />
                    </div>
                </Cell></tr>
                <Section>2. BAHAN / PRODUK (MATERIAL / PRODUCT)</Section>
                <tr><Cell>Nama &amp; Jenama (Name &amp; Brand): <Field path="$.title" /></Cell><Cell>No. Model &amp; Pengilang (Model No. &amp; Manufacturer): <Field path="material.model_manufacturer" /></Cell></tr>
                <tr><Cell>Pembekal (Supplier): <Field path="material.supplier" /></Cell><Cell>Negara Asal (Country of Origin): <Field path="material.country_of_origin" /></Cell></tr>
                <Section>3. BAHAN / PRODUK / PENYERAHAN DOKUMEN UNTUK DIGUNAKAN BAGI (MATERIAL / PRODUCT / SUBMITTAL TO BE USED FOR)</Section>
                <tr><Cell colSpan={2}><Area path="used_for" rows={2} bounded className="max-h-12" /></Cell></tr>
                <tr><Cell><b>4. RUJUKAN LUKISAN / DOKUMEN (Drawing / Document Reference):</b><Field path="drawing_reference" /></Cell><Cell><b>5. RUJUKAN SPESIFIKASI / BQ (Specification / BQ Reference):</b><Field path="specification_reference" /></Cell></tr>
                <Section>6. LAMPIRAN (ATTACHMENT)</Section>
                <tr><Cell colSpan={2}><div className="grid grid-cols-2 gap-x-3 gap-y-0.5">
                    <Check path="attachment.sample" label="Sampel Bahan / Kerja (Sample)" />
                    <Check path="attachment.report" label="Laporan (Report)" />
                    <Check path="attachment.drawing" label="Lukisan (Drawing)" />
                    <Check path="attachment.test_certificate" label="Sijil Ujian (Mill Certificate)" />
                    <Check path="attachment.catalogue" label="Katalog / Brosur / Data Teknikal (Catalogue / Brochure / Technical Data)" />
                    <div className="flex items-center"><Check path="attachment.others" label="Lain-lain (Other):" /><Field path="attachment.others_text" /></div>
                </div></Cell></tr>
                <Section>7. PENGESAHAN KONTRAKTOR (CONTRACTOR CONFIRMATION)</Section>
                <tr><Cell colSpan={2}>
                    <p>Kontraktor mengesahkan bahawa bahan/produk yang dikenal pasti memenuhi keperluan spesifikasi/lukisan dalam semua aspek.</p>
                    <SignBlock base="contractor_confirmation" fields={['Nama', 'Jawatan', 'Tarikh', 'Tandatangan']} inline />
                </Cell></tr>
                <Section>8. MAKLUM BALAS PERUNDING / ARKITEK (CONSULTANT / ARCHITECT RESPONSE)</Section>
                <tr><Cell colSpan={2}>
                    <div className="flex flex-wrap gap-x-4"><Check path="consultant_response.agree" label="Mematuhi (Complied)" /><Check path="consultant_response.disagree" label="Tidak Mematuhi (Not Complied)" /></div>
                    <div className="flex items-center"><span className="shrink-0">Catatan (Remarks):</span><Field path="consultant_response.remarks" /></div>
                    <SignBlock base="consultant_response" fields={['Nama', 'Jawatan', 'Tarikh', 'Tandatangan']} inline />
                    <div className="flex items-center"><span className="shrink-0">No. Rujukan Surat (Letter Ref. No.):</span><Field path="consultant_response.letter_ref_no" /></div>
                </Cell></tr>
                <Section>9. MAKLUM BALAS S.O. / WAKIL S.O. (S.O. / S.O. REPRESENTATIVE RESPONSE)</Section>
                <tr><Cell colSpan={2}>
                    <div className="flex flex-wrap gap-x-4"><Check path="so_response.accepted" label="Dipersetujui (Approved)" /><Check path="so_response.not_accepted" label="Tidak Dipersetujui &amp; Kemukakan semula (Not Approved &amp; Resubmit)" /></div>
                    <div className="flex items-center"><span className="shrink-0">Catatan (Remarks):</span><Field path="so_response.remarks" /></div>
                    <p className="text-[9px]">Walaupun perkara di atas telah dipersetujui, adalah menjadi tanggungjawab kontraktor bagi memastikan perkara ini mematuhi kehendak spesifikasi dan berfungsi seperti yang telah dinyatakan di dalam kontrak.</p>
                    <SignBlock base="so_response" fields={['Nama', 'Jawatan', 'Tarikh', 'Tandatangan']} inline />
                </Cell></tr>
            </Table>
            <p className="mt-0.5 text-[9px] italic">*Sila Potong yang tidak berkaitan</p>
        </FormPaper>
    );
}
