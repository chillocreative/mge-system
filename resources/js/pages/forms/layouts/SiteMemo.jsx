import FormPaper from '../FormPaper';
import { Table, Cell, Check, Field, Area } from '../fields';

export default function SiteMemo({ meta }) {
    return (
        <FormPaper meta={meta} page={1} totalPages={meta.pages}>
            <div className="space-y-2">
                <Table>
                    <tr>
                        <Cell><Check path="dist.urgent" label="URGENT" /></Cell>
                        <Cell><Check path="dist.for_your_record_1" label="FOR YOUR RECORD" /></Cell>
                        <Cell><Check path="dist.for_your_record_2" label="FOR YOUR RECORD" /></Cell>
                    </tr>
                    <tr>
                        <Cell><Check path="dist.for_your_attention" label="FOR YOUR ATTENTION" /></Cell>
                        <Cell><Check path="dist.for_your_comments" label="FOR YOUR COMMENTS" /></Cell>
                        <Cell><Check path="dist.please_sign_return" label="PLEASE SIGN & RETURN" /></Cell>
                    </tr>
                    <tr>
                        <Cell><Check path="dist.for_your_action" label="FOR YOUR ACTION" /></Cell>
                        <Cell><Check path="dist.for_your_approval" label="FOR YOUR APPROVAL" /></Cell>
                        <Cell><Check path="dist.please_acknowledge" label="PLEASE ACKNOWLEDGE RECEIPT" /></Cell>
                    </tr>
                </Table>

                <Table>
                    <tr>
                        <Cell head className="w-32">KEPADA (TO)</Cell>
                        <Cell><Field path="to" /></Cell>
                        <Cell head className="w-40">NO. RUJUKAN (REF. NO.)</Cell>
                        <Cell><Field path="$.ref_no" /></Cell>
                    </tr>
                    <tr>
                        <Cell head>SALINAN (CC)</Cell>
                        <Cell><Field path="cc" /></Cell>
                        <Cell head>TARIKH (DATE)</Cell>
                        <Cell><Field path="$.form_date" type="date" /></Cell>
                    </tr>
                    <tr>
                        <Cell head>PERKARA (SUBJECT)</Cell>
                        <Cell><Field path="$.title" /></Cell>
                        <Cell head>SKOP / PAKEJ</Cell>
                        <Cell><Field path="scope" /></Cell>
                    </tr>
                </Table>

                <Table>
                    <tr>
                        <Cell>
                            <Area path="body" rows={14} />
                        </Cell>
                    </tr>
                </Table>

                <Table>
                    <tr>
                        <Cell>
                            <p>Yang benar, / Bagi pihak / For / MULTI GREEN ENGINEERING SDN. BHD.</p>
                            <div className="mt-6 mb-1 h-px w-48 bg-gray-400" />
                            <div className="flex items-baseline gap-1">
                                <span className="whitespace-nowrap font-medium">Nama (Name) :</span>
                                <Field path="signoff.nama" />
                            </div>
                            <div className="flex items-baseline gap-1">
                                <span className="whitespace-nowrap font-medium">Jawatan (Position) :</span>
                                <Field path="signoff.jawatan" />
                            </div>
                        </Cell>
                    </tr>
                </Table>
            </div>
        </FormPaper>
    );
}
