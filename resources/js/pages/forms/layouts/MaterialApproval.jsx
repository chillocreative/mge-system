import FormPaper from '../FormPaper';
import { Table, Cell, Section, Check, Field, Area, SignBlock } from '../fields';

export default function MaterialApproval({ meta }) {
    return (
        <FormPaper meta={meta} page={1} totalPages={meta.pages}>
            <div className="space-y-2">
                <Table>
                    <tr>
                        <Cell head className="w-1/2">Kepada (To):</Cell>
                        <Cell head className="w-1/2">Disediakan Oleh (Prepared By):</Cell>
                    </tr>
                    <tr>
                        <Cell className="align-top">
                            <Field path="kepada" />
                            <div className="mt-1 flex items-baseline gap-1">
                                <span className="whitespace-nowrap font-medium">Tarikh Permohonan (Date of Request) :</span>
                                <Field path="$.form_date" type="date" />
                            </div>
                        </Cell>
                        <Cell className="align-top">
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Nama :</span><Field path="prepared_by.nama" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Jawatan (Position) :</span><Field path="prepared_by.jawatan" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tarikh (Date) :</span><Field path="prepared_by.tarikh" type="date" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tandatangan (Signature) :</span><Field path="prepared_by.tandatangan" /></div>
                        </Cell>
                    </tr>
                </Table>

                <p className="font-semibold">
                    KELULUSAN BAHAN (REQUEST FOR APPROVAL):{' '}
                    <Check path="request_for.product" label="Product" />{' '}
                    <Check path="request_for.material" label="Material" />{' '}
                    <Check path="request_for.submittal" label="Submittal" />
                </p>

                <Table>
                    <Section>1. PENYERAHAN OLEH (SUBMISSION BY)</Section>
                    <tr>
                        <Cell colSpan={4}>
                            <div className="flex flex-wrap gap-4">
                                <Check path="submission_by.main_contractor" label="Main Contractor" />
                                <Check path="submission_by.sub_contractor" label="Sub-Contractor" />
                                <Check path="submission_by.supplier" label="Supplier" />
                            </div>
                        </Cell>
                    </tr>
                    <tr>
                        <Cell colSpan={4}>
                            <div className="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                                <div className="flex items-baseline gap-1"><span className="whitespace-nowrap font-medium">Nama Syarikat (Company Name) :</span><Field path="submission_by.company_name" /></div>
                                <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Nama (Name) :</span><Field path="submission_by.nama" /></div>
                                <div className="flex items-baseline gap-1"><span className="whitespace-nowrap font-medium">Jawatan (Position) :</span><Field path="submission_by.jawatan" /></div>
                                <div className="flex items-baseline gap-1"><span className="whitespace-nowrap font-medium">Tandatangan (Signature) :</span><Field path="submission_by.tandatangan" /></div>
                            </div>
                        </Cell>
                    </tr>

                    <Section>2. BAHAN / PRODUK (MATERIAL / PRODUCT)</Section>
                    <tr>
                        <Cell head className="w-72">Nama &amp; Jenama (Name &amp; Brand)</Cell>
                        <Cell colSpan={3}><Field path="$.title" /></Cell>
                    </tr>
                    <tr>
                        <Cell head>No. Model &amp; Pengilang (Model No. &amp; Manufacturer)</Cell>
                        <Cell colSpan={3}><Field path="material.model_manufacturer" /></Cell>
                    </tr>
                    <tr>
                        <Cell head>Pembekal (Supplier)</Cell>
                        <Cell colSpan={3}><Field path="material.supplier" /></Cell>
                    </tr>
                    <tr>
                        <Cell head>Negara Asal (Country of Origin)</Cell>
                        <Cell colSpan={3}><Field path="material.country_of_origin" /></Cell>
                    </tr>

                    <Section>3. BAHAN / PRODUK / PENYERAHAN DOKUMEN UNTUK DIGUNAKAN BAGI (MATERIAL / PRODUCT / SUBMITTAL TO BE USED FOR)</Section>
                    <tr>
                        <Cell colSpan={4}><Area path="used_for" rows={3} /></Cell>
                    </tr>

                    <tr>
                        <Cell head className="w-1/2">4. RUJUKAN LUKISAN (DRAWING REFERENCE)</Cell>
                        <Cell head className="w-1/2">5. RUJUKAN SPESIFIKASI / BQ (SPECIFICATION / BQ REFERENCE)</Cell>
                    </tr>
                    <tr>
                        <Cell><Field path="drawing_reference" /></Cell>
                        <Cell><Field path="specification_reference" /></Cell>
                    </tr>

                    <Section>LAMPIRAN (ATTACHMENT)</Section>
                    <tr>
                        <Cell><Check path="attachment.sample" label="Sampel (Sample)" /></Cell>
                        <Cell><Check path="attachment.catalogue" label="Katalog / Brosur / Data Teknikal (Catalogue / Brochure / Technical Data)" /></Cell>
                    </tr>
                    <tr>
                        <Cell><Check path="attachment.test_certificate" label="Sijil Ujian (Test Certificate)" /></Cell>
                        <Cell>
                            <div className="flex items-baseline gap-1">
                                <Check path="attachment.others" label="Lain-lain (Other):" />
                                <Field path="attachment.others_text" />
                            </div>
                        </Cell>
                    </tr>

                    <Section>6. PENGESAHAN KONTRAKTOR (CONTRACTOR CONFIRMATION)</Section>
                    <tr>
                        <Cell>
                            <p className="mb-1">Kontraktor mengesahkan bahawa bahan/produk yang dikenal pasti memenuhi keperluan spesifikasi/lukisan dalam semua aspek.</p>
                            <SignBlock base="contractor_confirmation" fields={['Nama', 'Tarikh', 'Tandatangan']} inline />
                        </Cell>
                    </tr>

                    <Section>7. MAKLUM BALAS PERUNDING / ARKITEK (CONSULTANT / ARCHITECT RESPONSE)</Section>
                    <tr>
                        <Cell>
                            <p className="mb-1 font-medium">Catatan (Remarks):</p>
                            <Area path="consultant_response.remarks" rows={2} />
                            <div className="my-1 flex flex-wrap gap-4">
                                <Check path="consultant_response.agree" label="Bersetuju (Agree)" />
                                <Check path="consultant_response.disagree" label="Tidak Bersetuju (Disagree)" />
                            </div>
                            <SignBlock base="consultant_response" fields={['Nama', 'Tarikh', 'Tandatangan']} inline />
                        </Cell>
                    </tr>

                    <Section>8. MAKLUM BALAS S.O. / WAKIL S.O. (S.O. / S.O. REPRESENTATIVE RESPONSE)</Section>
                    <tr>
                        <Cell>
                            <p className="mb-1 font-medium">Catatan (Remarks):</p>
                            <Area path="so_response.remarks" rows={2} />
                            <div className="my-1 flex flex-wrap gap-4">
                                <Check path="so_response.accepted" label="Diterima (Accepted)" />
                                <Check path="so_response.not_accepted" label="Tidak Diterima (Not Accepted)" />
                            </div>
                            <SignBlock base="so_response" fields={['Nama', 'Tarikh', 'Tandatangan']} inline />
                        </Cell>
                    </tr>
                </Table>
            </div>
        </FormPaper>
    );
}
