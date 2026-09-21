import FormPaper from '../FormPaper';
import { Table, Cell, Section, Field, SignBlock, PhotoSlot, useFormData } from '../fields';

const manpowerCategories = [
    'Engineer', 'Supervisor', 'Foreman', 'Crane Operator', 'Operator', 'Driver',
    'Rigger', 'Steel Fixer', 'Carpenter', 'Mason', 'Welder', 'Fitter', 'Labour', 'Security',
];

const equipmentItems = [
    'Crane', 'Excavator', 'Driller', 'Backhoe', 'Lori (Truck)',
    'Set Kimpalan (Welding Set)', 'Genset', 'Pengisar Simen (Cement Mixer)',
];

const materialItems = ['Konkrit (Concrete)', 'Besi Tetulang (Rebar)', 'Rasuk (Beams)', 'L-Unit', 'Batu Perisai (Armour Rock)'];

const num = (v) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
};

function ManpowerTable() {
    const { get } = useFormData();
    const rowCount = manpowerCategories.length + 2;

    const totals = { bumi: 0, non_bumi: 0, jumlah: 0, hours: 0 };
    for (let i = 0; i < rowCount; i++) {
        const bumi = num(get(`manpower.${i}.bumi`));
        const nonBumi = num(get(`manpower.${i}.non_bumi`));
        totals.bumi += bumi;
        totals.non_bumi += nonBumi;
        totals.jumlah += bumi + nonBumi;
        totals.hours += num(get(`manpower.${i}.hours`));
    }

    return (
        <Table>
            <tr>
                <Cell head>Kategori Pekerja / (Manpower)</Cell>
                <Cell head>Bumi / (Local)</Cell>
                <Cell head>Non-Bumi / (Foreigner)</Cell>
                <Cell head>Jumlah / (Total)</Cell>
                <Cell head>Jam Bekerja / (Hours Worked)</Cell>
                <Cell head>Catatan / (Remarks)</Cell>
            </tr>
            {manpowerCategories.map((label, i) => {
                const bumi = num(get(`manpower.${i}.bumi`));
                const nonBumi = num(get(`manpower.${i}.non_bumi`));
                return (
                    <tr key={label}>
                        <Cell>{label}</Cell>
                        <Cell><Field path={`manpower.${i}.bumi`} align="right" /></Cell>
                        <Cell><Field path={`manpower.${i}.non_bumi`} align="right" /></Cell>
                        <Cell className="bg-gray-50 text-right font-medium">{bumi + nonBumi || ''}</Cell>
                        <Cell><Field path={`manpower.${i}.hours`} align="right" /></Cell>
                        <Cell><Field path={`manpower.${i}.remarks`} /></Cell>
                    </tr>
                );
            })}
            {[manpowerCategories.length, manpowerCategories.length + 1].map((i) => {
                const bumi = num(get(`manpower.${i}.bumi`));
                const nonBumi = num(get(`manpower.${i}.non_bumi`));
                return (
                    <tr key={i}>
                        <Cell><Field path={`manpower.${i}.category`} /></Cell>
                        <Cell><Field path={`manpower.${i}.bumi`} align="right" /></Cell>
                        <Cell><Field path={`manpower.${i}.non_bumi`} align="right" /></Cell>
                        <Cell className="bg-gray-50 text-right font-medium">{bumi + nonBumi || ''}</Cell>
                        <Cell><Field path={`manpower.${i}.hours`} align="right" /></Cell>
                        <Cell><Field path={`manpower.${i}.remarks`} /></Cell>
                    </tr>
                );
            })}
            <tr>
                <Cell head>JUMLAH BESAR (TOTAL)</Cell>
                <Cell head className="text-right">{totals.bumi || ''}</Cell>
                <Cell head className="text-right">{totals.non_bumi || ''}</Cell>
                <Cell head className="text-right">{totals.jumlah || ''}</Cell>
                <Cell head className="text-right">{totals.hours || ''}</Cell>
                <Cell head></Cell>
            </tr>
        </Table>
    );
}

function EquipmentTable() {
    return (
        <Table>
            <tr>
                <Cell head>Penerangan (Description)</Cell>
                <Cell head>Kuantiti (Qty)</Cell>
                <Cell head>Jam (Hours)</Cell>
                <Cell head>Catatan (Remark)</Cell>
            </tr>
            {equipmentItems.map((label, i) => (
                <tr key={label}>
                    <Cell>{label}</Cell>
                    <Cell><Field path={`equipment.${i}.qty`} align="right" /></Cell>
                    <Cell><Field path={`equipment.${i}.hours`} align="right" /></Cell>
                    <Cell><Field path={`equipment.${i}.remarks`} /></Cell>
                </tr>
            ))}
            {[equipmentItems.length, equipmentItems.length + 1].map((i) => (
                <tr key={i}>
                    <Cell><Field path={`equipment.${i}.description`} /></Cell>
                    <Cell><Field path={`equipment.${i}.qty`} align="right" /></Cell>
                    <Cell><Field path={`equipment.${i}.hours`} align="right" /></Cell>
                    <Cell><Field path={`equipment.${i}.remarks`} /></Cell>
                </tr>
            ))}
        </Table>
    );
}

function MaterialsTable() {
    return (
        <Table>
            <tr>
                <Cell head>Penerangan (Description)</Cell>
                <Cell head>Kuantiti (Qty)</Cell>
                <Cell head>Catatan (Remark)</Cell>
            </tr>
            {materialItems.map((label, i) => (
                <tr key={label}>
                    <Cell>{label}</Cell>
                    <Cell><Field path={`materials.${i}.qty`} align="right" /></Cell>
                    <Cell><Field path={`materials.${i}.remarks`} /></Cell>
                </tr>
            ))}
            {[materialItems.length, materialItems.length + 1].map((i) => (
                <tr key={i}>
                    <Cell><Field path={`materials.${i}.description`} /></Cell>
                    <Cell><Field path={`materials.${i}.qty`} align="right" /></Cell>
                    <Cell><Field path={`materials.${i}.remarks`} /></Cell>
                </tr>
            ))}
        </Table>
    );
}

function LinedRows({ pathPrefix, count }) {
    return (
        <Table>
            {Array.from({ length: count }, (_, i) => (
                <tr key={i}>
                    <Cell><Field path={`${pathPrefix}.${i}`} /></Cell>
                </tr>
            ))}
        </Table>
    );
}

export default function DailySiteDiary({ meta, record, onReload }) {
    return (
        <div className="space-y-6">
            <FormPaper meta={meta} page={1} totalPages={meta.pages}>
                <div className="space-y-2">
                    <Table>
                        <tr>
                            <Cell head className="w-40">Hari (Day)</Cell>
                            <Cell><Field path="day" /></Cell>
                            <Cell head className="w-40">Cuaca (Weather)</Cell>
                            <Cell><Field path="weather" /></Cell>
                        </tr>
                        <tr>
                            <Cell head>Tarikh (Date)</Cell>
                            <Cell><Field path="$.form_date" type="date" /></Cell>
                            <Cell head>Jam Hujan / (Rain Hours)</Cell>
                            <Cell><Field path="rain_hours" /></Cell>
                        </tr>
                        <tr>
                            <Cell head>Masa (Time)</Cell>
                            <Cell><Field path="time" /></Cell>
                            <Cell></Cell>
                            <Cell></Cell>
                        </tr>
                    </Table>

                    <p className="font-bold">A. TENAGA KERJA (MANPOWER)</p>
                    <ManpowerTable />

                    <p className="font-bold">B. PERALATAN (EQUIPMENT)</p>
                    <EquipmentTable />
                </div>
            </FormPaper>

            <FormPaper meta={meta} page={2} totalPages={meta.pages}>
                <div className="space-y-2">
                    <p className="font-bold">C. BAHAN (MATERIAL)</p>
                    <MaterialsTable />

                    <p className="font-bold">D. PERIHAL AKTIVITI HARIAN (DESCRIPTION OF DAILY ACTIVITIES)</p>
                    <LinedRows pathPrefix="daily_activities" count={5} />

                    <p className="font-bold">E. MASA TERBENGKALAI / GANGGUAN KERJA (DOWNTIME)</p>
                    <LinedRows pathPrefix="downtime" count={4} />

                    <p className="font-bold">F. CATATAN KESELAMATAN / HSE (JIKA ADA)</p>
                    <LinedRows pathPrefix="hse_notes" count={4} />

                    <Table>
                        <tr>
                            <Cell head>DISEDIAKAN OLEH (PREPARED BY)</Cell>
                            <Cell head>DISEMAK OLEH (VERIFIED BY)</Cell>
                            <Cell head>DIAKUI OLEH (APPROVED BY)</Cell>
                        </tr>
                        <tr>
                            <Cell className="align-top">
                                <p className="mb-1 text-[10.5px] italic">Prepared by &mdash; Penolong Teknikal / Penyelia Tapak, MGE</p>
                                <SignBlock base="sign.prepared" line={false} />
                            </Cell>
                            <Cell className="align-top">
                                <p className="mb-1 text-[10.5px] italic">Checked by &mdash; Jurutera Tapak / QAQC, MGE</p>
                                <SignBlock base="sign.checked" line={false} />
                            </Cell>
                            <Cell className="align-top">
                                <p className="mb-1 text-[10.5px] italic">Acknowledged by &mdash; Wakil Perunding / S.O. Rep.</p>
                                <SignBlock base="sign.approved" line={false} />
                            </Cell>
                        </tr>
                    </Table>
                </div>
            </FormPaper>

            <FormPaper meta={meta} page={3} totalPages={meta.pages}>
                <div className="space-y-2">
                    <Section>GAMBAR AKTIVITI TAPAK (SITE ACTIVITY PHOTOS)</Section>
                    <div className="grid grid-cols-2 gap-3">
                        {Array.from({ length: 8 }, (_, i) => i + 1).map((n) => (
                            <div key={n} className="space-y-1 rounded border border-black p-2">
                                <PhotoSlot n={n} record={record} onUploaded={onReload} />
                                <div className="flex items-baseline gap-1 text-[10.5px]">
                                    <span className="whitespace-nowrap font-medium">Gambar {n} &mdash; Perihal (Caption) :</span>
                                    <Field path={`photos.${n}.caption`} />
                                </div>
                                <div className="flex items-baseline gap-1 text-[10.5px]">
                                    <span className="whitespace-nowrap font-medium">Lokasi (Location) :</span>
                                    <Field path={`photos.${n}.location`} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </FormPaper>
        </div>
    );
}
