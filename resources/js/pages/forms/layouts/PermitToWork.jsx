import FormPaper from '../FormPaper';
import { Table, Cell, Section, Check, Field, Area, SignBlock } from '../fields';

const closureSign = ['Nama', 'Jawatan', 'Tandatangan / Tarikh-Masa'];

export default function PermitToWork({ meta }) {
    return (
        <div className="space-y-6">
            <FormPaper meta={meta} page={1} totalPages={meta.pages}>
                <div className="space-y-2">
                    <Table>
                        <tr>
                            <Cell head className="w-56">NO. PERMIT (PERMIT NO.)</Cell>
                            <Cell><Field path="$.ref_no" /></Cell>
                            <Cell head className="w-56">TARIKH DIKELUARKAN (DATE ISSUED)</Cell>
                            <Cell><Field path="$.form_date" type="date" /></Cell>
                        </tr>
                        <tr>
                            <Cell head>TEMPOH SAH (VALIDITY PERIOD)</Cell>
                            <Cell>
                                <span className="mr-1 whitespace-nowrap font-medium">Dari (From) :</span>
                                <Field path="validity.from" type="date" />
                            </Cell>
                            <Cell>
                                <span className="mr-1 whitespace-nowrap font-medium">Hingga (To) :</span>
                                <Field path="validity.to" type="date" />
                            </Cell>
                            <Cell>
                                <span className="mr-1 whitespace-nowrap font-medium">Tempoh (Duration) :</span>
                                <Field path="validity.duration" />
                            </Cell>
                        </tr>
                        <tr>
                            <Cell head>LOKASI / ZON KERJA (WORK LOCATION / ZONE)</Cell>
                            <Cell colSpan={3}><Field path="$.title" /></Cell>
                        </tr>
                        <tr>
                            <Cell head>KONTRAKTOR / SUBKONTRAKTOR PELAKSANA</Cell>
                            <Cell colSpan={2}><Field path="contractor_executing" /></Cell>
                            <Cell head>
                                <div className="flex items-center gap-1">
                                    <span className="whitespace-nowrap">BIL. PEKERJA TERLIBAT</span>
                                    <Field path="workers_count" type="number" align="center" />
                                </div>
                            </Cell>
                        </tr>
                    </Table>

                    <Table>
                        <Section>JENIS KERJA BERISIKO (TYPE OF HIGH-RISK WORK)</Section>
                        <tr>
                            <Cell><Check path="high_risk.hot_work" label="Kerja Panas (Hot Work)" /></Cell>
                            <Cell><Check path="high_risk.work_at_height" label="Kerja Ketinggian (Work at Height)" /></Cell>
                            <Cell><Check path="high_risk.confined_space" label="Ruang Terkurung (Confined Space)" /></Cell>
                        </tr>
                        <tr>
                            <Cell><Check path="high_risk.excavation" label="Kerja Penggalian (Excavation)" /></Cell>
                            <Cell><Check path="high_risk.lifting_operation" label="Operasi Mengangkat (Lifting Operation)" /></Cell>
                            <Cell><Check path="high_risk.electrical_work" label="Kerja Elektrik (Electrical Work)" /></Cell>
                        </tr>
                        <tr>
                            <Cell><Check path="high_risk.work_near_water" label="Kerja Berhampiran Air / Sungai (Work Near Water)" /></Cell>
                            <Cell><Check path="high_risk.blasting" label="Kerja Blasting / Letupan" /></Cell>
                            <Cell>
                                <div className="flex items-baseline gap-1">
                                    <Check path="high_risk.others" label="Lain-lain (Others):" />
                                    <Field path="high_risk.others_text" />
                                </div>
                            </Cell>
                        </tr>
                    </Table>

                    <Table>
                        <Section>1. PERIHAL KERJA (DESCRIPTION OF WORK)</Section>
                        <tr>
                            <Cell><Area path="description_of_work" rows={3} /></Cell>
                        </tr>
                        <Section>
                            <div className="flex items-center justify-between normal-case font-bold">
                                <span>2. BAHAYA DIKENAL PASTI (HAZARDS IDENTIFIED) - HIRARC</span>
                                <span className="font-normal normal-case"><Check path="hazards.lampiran" label="Lampiran Disediakan" /></span>
                            </div>
                        </Section>
                        <tr>
                            <Cell><Area path="hazards.details" rows={3} /></Cell>
                        </tr>
                    </Table>

                    <Table>
                        <Section>3. LANGKAH KAWALAN / PPE DIPERLUKAN (CONTROL MEASURES / PPE REQUIRED)</Section>
                        <tr>
                            <Cell><Check path="controls.helmet" label="Topi Keledar (Helmet)" /></Cell>
                            <Cell><Check path="controls.safety_harness" label="Tali Keledar Keselamatan (Safety Harness)" /></Cell>
                            <Cell><Check path="controls.gas_detector" label="Pengesan Gas (Gas Detector)" /></Cell>
                        </tr>
                        <tr>
                            <Cell><Check path="controls.fire_extinguisher" label="Alat Pemadam Api (Fire Extinguisher)" /></Cell>
                            <Cell><Check path="controls.barricade_signage" label="Halangan / Papan Tanda (Barricade / Signage)" /></Cell>
                            <Cell><Check path="controls.isolation_loto" label="Pengasingan / LOTO (Isolation / LOTO)" /></Cell>
                        </tr>
                        <tr>
                            <Cell><Check path="controls.standby_person" label="Pemerhati Sedia (Standby Person)" /></Cell>
                            <Cell><Check path="controls.rescue_plan" label="Pelan Penyelamatan (Rescue Plan)" /></Cell>
                            <Cell>
                                <div className="flex items-baseline gap-1">
                                    <Check path="controls.others" label="Lain-lain (Others):" />
                                    <Field path="controls.others_text" />
                                </div>
                            </Cell>
                        </tr>
                    </Table>

                    <Table>
                        <Section>4. PENGESAHAN SEBELUM KERJA (PRE-WORK CERTIFICATION)</Section>
                        <tr>
                            <Cell><Area path="pre_work_certification" rows={2} /></Cell>
                        </tr>
                    </Table>

                    <Table>
                        <Section>5. Dipohon Oleh (Requested by &mdash; Penyelia Subkontraktor)</Section>
                        <tr>
                            <Cell><SignBlock base="requested_by" fields={closureSign} inline /></Cell>
                        </tr>
                        <Section>6. Disemak Oleh (Verified by &mdash; Pegawai Keselamatan / HSE, MGE)</Section>
                        <tr>
                            <Cell><SignBlock base="verified_by" fields={closureSign} inline /></Cell>
                        </tr>
                    </Table>
                </div>
            </FormPaper>

            <FormPaper meta={meta} page={2} totalPages={meta.pages}>
                <div className="space-y-2">
                    <Table>
                        <Section>7. Diluluskan Oleh (Approved by &mdash; Pengurus Tapak / PM, MGE)</Section>
                        <tr>
                            <Cell><SignBlock base="approved_by" fields={closureSign} inline /></Cell>
                        </tr>
                    </Table>

                    <Table>
                        <Section>8. PENGESAHAN TERIMA OLEH PEKERJA BERTANGGUNGJAWAB (ACCEPTANCE BY PERMIT RECEIVER)</Section>
                        <tr>
                            <Cell>
                                <p className="mb-1">Saya faham dan akan mematuhi semua syarat keselamatan yang dinyatakan dalam permit ini.</p>
                                <SignBlock base="acceptance_by_receiver" fields={closureSign} inline />
                            </Cell>
                        </tr>
                    </Table>

                    <Table>
                        <Section>9. PENUTUPAN PERMIT (PERMIT CLOSURE / COMPLETION)</Section>
                        <tr>
                            <Cell>
                                <div className="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                                    <span className="font-medium whitespace-nowrap">Kerja Selesai (Work Completed) :</span>
                                    <Check path="closure.work_completed_yes" label="Ya (Yes)" />
                                    <Check path="closure.work_completed_no" label="Tidak (No)" />
                                </div>
                            </Cell>
                        </tr>
                        <tr>
                            <Cell>
                                <div className="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                                    <span className="font-medium whitespace-nowrap">Kawasan Dibersihkan &amp; Selamat (Area Cleared &amp; Safe) :</span>
                                    <Check path="closure.area_cleared_yes" label="Ya (Yes)" />
                                    <Check path="closure.area_cleared_no" label="Tidak (No)" />
                                </div>
                            </Cell>
                        </tr>
                        <Section>Ditutup / Disahkan Oleh (Closed / Verified by &mdash; Pegawai Keselamatan, MGE)</Section>
                        <tr>
                            <Cell><SignBlock base="closed_by" fields={closureSign} inline /></Cell>
                        </tr>
                        <Section>Diterima Oleh (Received by &mdash; Permit Receiver)</Section>
                        <tr>
                            <Cell><SignBlock base="received_by" fields={closureSign} inline /></Cell>
                        </tr>
                    </Table>
                </div>
            </FormPaper>
        </div>
    );
}
