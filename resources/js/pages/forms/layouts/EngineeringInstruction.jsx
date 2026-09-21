import FormPaper from '../FormPaper';
import { Table, Cell, Section, Check, Field, Area, SignBlock } from '../fields';

export default function EngineeringInstruction({ meta }) {
    return (
        <FormPaper meta={meta} page={1} totalPages={meta.pages}>
            <div className="space-y-2">
                <Table>
                    <tr>
                        <Cell head className="w-1/2">DIKELUARKAN KEPADA (ISSUED TO)</Cell>
                        <Cell head className="w-1/2">DISEDIAKAN OLEH (PREPARED BY)</Cell>
                    </tr>
                    <tr>
                        <Cell><Area path="issued_to" rows={3} /></Cell>
                        <Cell>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Nama :</span><Field path="prepared_by.nama" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Jawatan (Position) :</span><Field path="prepared_by.jawatan" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tarikh (Date) :</span><Field path="prepared_by.tarikh" type="date" /></div>
                            <div className="flex items-baseline gap-1"><span className="font-medium whitespace-nowrap">Tandatangan (Signature) :</span><Field path="prepared_by.tandatangan" /></div>
                        </Cell>
                    </tr>
                </Table>

                <Table>
                    <tr>
                        <Cell head className="w-56">NO. RUJUKAN (REF. NO.)</Cell>
                        <Cell><Field path="$.ref_no" /></Cell>
                        <Cell head className="w-56">TARIKH DIKELUARKAN (DATE ISSUED)</Cell>
                        <Cell><Field path="$.form_date" type="date" /></Cell>
                    </tr>
                    <tr>
                        <Cell head>RUJUKAN BERKAITAN (RELATED REF.)</Cell>
                        <Cell colSpan={3}>
                            <span className="mr-1 whitespace-nowrap">Lukisan / Spesifikasi / RFI / NCR No. :</span>
                            <Field path="related_ref" />
                        </Cell>
                    </tr>
                    <tr>
                        <Cell head>TAJUK (SUBJECT)</Cell>
                        <Cell colSpan={3}><Field path="$.title" /></Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>
                        <div className="flex items-center justify-between normal-case font-bold">
                            <span>1. BUTIRAN ARAHAN (INSTRUCTION DETAILS)</span>
                            <span className="font-normal normal-case"><Check path="details.lampiran" label="Lampiran" /></span>
                        </div>
                    </Section>
                    <tr>
                        <Cell><Area path="details.instruction" rows={5} /></Cell>
                    </tr>
                    <tr>
                        <Cell>
                            <span className="mr-1 whitespace-nowrap font-medium">Tarikh Pematuhan Dikehendaki (Compliance Required By) :</span>
                            <Field path="details.compliance_required_by" type="date" />
                        </Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>2. DIKELUARKAN OLEH MGE (ISSUED BY &mdash; MGE)</Section>
                    <tr>
                        <Cell><SignBlock base="issued_by_mge" inline /></Cell>
                    </tr>
                    <Section>3. PENGESAHAN TERIMA OLEH SUBKONTRAKTOR (ACKNOWLEDGEMENT OF RECEIPT &mdash; SUB-CONTRACTOR)</Section>
                    <tr>
                        <Cell><SignBlock base="ack_subcon" inline /></Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>4. STATUS PEMATUHAN (COMPLIANCE STATUS &mdash; FOR MGE QAQC / DOCUMENT CONTROL USE)</Section>
                    <tr>
                        <Cell head>Tarikh Dipatuhi (Date Complied)</Cell>
                        <Cell head>Tarikh Dihantar (Date Submitted)</Cell>
                        <Cell head>Status</Cell>
                        <Cell head>Catatan (Remarks)</Cell>
                    </tr>
                    <tr>
                        <Cell><Field path="compliance.date_complied" type="date" /></Cell>
                        <Cell><Field path="compliance.date_submitted" type="date" /></Cell>
                        <Cell>
                            <div className="flex flex-col gap-0.5">
                                <Check path="compliance.status_open" label="Open" />
                                <Check path="compliance.status_closed" label="Closed" />
                            </div>
                        </Cell>
                        <Cell><Field path="compliance.remarks" /></Cell>
                    </tr>
                </Table>

                <Table>
                    <Section>5. DISAHKAN SELESAI OLEH MGE (VERIFIED COMPLETE &mdash; MGE QAQC / SITE ENGINEER)</Section>
                    <tr>
                        <Cell><SignBlock base="verified_by_mge" inline /></Cell>
                    </tr>
                </Table>
            </div>
        </FormPaper>
    );
}
