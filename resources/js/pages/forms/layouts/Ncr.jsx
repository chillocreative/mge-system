import FormPaper from '../FormPaper';
import { Table, Cell, Section, Check, Field, Area, SignBlock } from '../fields';

const issuedSign = ['Nama', 'Jawatan', 'Tandatangan / Tarikh'];

export default function Ncr({ meta }) {
    return (
        <FormPaper meta={meta} page={1} totalPages={meta.pages}>
            <div className="space-y-2">
                <Table>
                    <tr>
                        <Cell head className="w-1/2">DIKELUARKAN KEPADA (ISSUED TO)</Cell>
                        <Cell head className="w-1/2">DISEDIAKAN OLEH (PREPARED BY)</Cell>
                    </tr>
                    <tr>
                        <Cell className="align-top">
                            <div className="flex items-baseline gap-1"><span className="whitespace-nowrap font-medium">Nama Subkontraktor (Sub-Contractor) :</span><Field path="issued_to.sub_contractor" /></div>
                            <div className="flex items-baseline gap-1"><span className="whitespace-nowrap font-medium">Skop Kerja / Pakej (Trade / Package) :</span><Field path="issued_to.trade_package" /></div>
                        </Cell>
                        <Cell className="align-top">
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Nama :</span><Field path="prepared_by.nama" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Jawatan (Position) :</span><Field path="prepared_by.jawatan" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tarikh (Date) :</span><Field path="$.form_date" type="date" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tandatangan (Signature) :</span><Field path="prepared_by.tandatangan" /></div>
                        </Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>1. BUTIRAN KETIDAKAKURAN (NON-CONFORMANCE DETAILS)</Section>
                    <tr>
                        <Cell colSpan={3}>
                            <p className="mb-1 font-medium">Kategori (Category):</p>
                            <div className="flex flex-wrap gap-4">
                                <Check path="category.bahan" label="Bahan (Material)" />
                                <Check path="category.mutu_kerja" label="Mutu Kerja (Workmanship)" />
                                <Check path="category.keselamatan" label="Keselamatan (Safety)" />
                                <Check path="category.dokumentasi" label="Dokumentasi" />
                                <Check path="category.lain_lain" label="Lain-lain" />
                            </div>
                        </Cell>
                    </tr>
                    <tr>
                        <Cell head className="w-1/3">Lokasi / Zon (Location / Zone)</Cell>
                        <Cell head className="w-1/3">Rujukan Lukisan / Spesifikasi</Cell>
                        <Cell head className="w-1/3">Rujukan Ujian (jika ada)</Cell>
                    </tr>
                    <tr>
                        <Cell><Field path="location_zone" /></Cell>
                        <Cell><Field path="drawing_spec_reference" /></Cell>
                        <Cell><Field path="test_reference" /></Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>2. PERIHAL KETIDAKAKURAN (DESCRIPTION OF NON-CONFORMANCE)</Section>
                    <tr>
                        <Cell>
                            <div className="mb-1 flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tajuk (Title) :</span><Field path="$.title" /></div>
                            <Area path="description" rows={3} />
                            <Check path="photo_evidence_attached" label="Foto / Bukti dilampirkan (Photo evidence attached)" />
                        </Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>3. TAHAP KRITIKAL (CRITICALITY)</Section>
                    <tr>
                        <Cell>
                            <div className="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                                <Check path="criticality.critical" label="Kritikal (Critical)" />
                                <Check path="criticality.non_critical" label="Tidak Kritikal (Non-Critical)" />
                                <span className="whitespace-nowrap font-medium">Date Line for Rectification:</span>
                                <Field path="criticality.rectification_deadline" type="date" />
                            </div>
                        </Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>4. DIKELUARKAN OLEH &mdash; DISEMAK DAN DISAHKAN OLEH (ISSUED / VERIFIED BY)</Section>
                    <tr>
                        <Cell>
                            <table className="w-full border-collapse text-[11px]">
                                <tbody>
                                    <tr>
                                        <td className="border border-black px-1.5 py-1 align-top"><span className="whitespace-nowrap font-medium">Nama :</span> <Field path="issued_verified_by.nama" /></td>
                                        <td className="border border-black px-1.5 py-1 align-top"><span className="whitespace-nowrap font-medium">Jawatan (QAQC / Site Engineer) :</span> <Field path="issued_verified_by.jawatan" /></td>
                                        <td className="border border-black px-1.5 py-1 align-top"><span className="whitespace-nowrap font-medium">Tandatangan / Tarikh :</span> <Field path="issued_verified_by.tandatangan_tarikh" /></td>
                                    </tr>
                                </tbody>
                            </table>
                        </Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>5. PENGESAHAN TERIMA OLEH SUBKONTRAKTOR (ACKNOWLEDGEMENT OF RECEIPT BY SUB-CONTRACTOR)</Section>
                    <tr>
                        <Cell><SignBlock base="ack_subcontractor" fields={issuedSign} inline /></Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>6. TINDAKAN PEMBETULAN DICADANGKAN OLEH SUBKONTRAKTOR (CORRECTIVE ACTION PROPOSED BY SUB-CONTRACTOR)</Section>
                    <tr>
                        <Cell>
                            <div className="mb-1 flex items-baseline gap-1"><span className="whitespace-nowrap font-medium">Punca Ketidakakuran (Root Cause) :</span></div>
                            <Area path="corrective_action.root_cause" rows={2} />
                            <div className="mt-1 flex items-baseline gap-1"><span className="whitespace-nowrap font-medium">Tarikh Dijangka Selesai (Target Completion Date) :</span><Field path="corrective_action.target_completion_date" type="date" /></div>
                            <div className="mt-1"><SignBlock base="corrective_action" fields={issuedSign} inline /></div>
                        </Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>7. SEMAKAN &amp; KELULUSAN TINDAKAN PEMBETULAN OLEH MGE (REVIEW &amp; APPROVAL OF CORRECTIVE ACTION BY MGE)</Section>
                    <tr>
                        <Cell>
                            <p className="mb-1 font-medium">Ulasan (Remarks):</p>
                            <Area path="review.remarks" rows={2} />
                            <div className="my-1 flex flex-wrap gap-4">
                                <Check path="review.accepted" label="Diterima (Accepted)" />
                                <Check path="review.not_accepted" label="Tidak Diterima &mdash; Perlu Semak Semula (Not Accepted &mdash; Resubmit)" />
                            </div>
                            <SignBlock base="review" fields={issuedSign} inline />
                        </Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>8. PENGESAHAN PENUTUPAN (CLOSE-OUT VERIFICATION)</Section>
                    <tr>
                        <Cell>
                            <div className="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                                <Check path="closeout.solved_satisfactorily" label="Diselesaikan Memuaskan (Solved Satisfactorily)" />
                                <span className="whitespace-nowrap font-medium">Tarikh Ditutup (Date Closed) :</span>
                                <Field path="closeout.date_closed" type="date" />
                                <Check path="closeout.no" label="Tidak (No)" />
                                <Check path="closeout.na" label="T/B (N/A)" />
                            </div>
                        </Cell>
                    </tr>
                    <tr>
                        <Cell>
                            <div className="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                                <span className="whitespace-nowrap font-medium">Disahkan Oleh (Verified by &mdash; QAQC / PM, MGE) :</span>
                                <Field path="closeout.verified_by" />
                                <span className="whitespace-nowrap font-medium">Tandatangan / Tarikh :</span>
                                <Field path="closeout.tandatangan_tarikh" />
                            </div>
                        </Cell>
                    </tr>
                </Table>
            </div>
        </FormPaper>
    );
}
