import { useState } from 'react';
import { HiOutlinePlus, HiOutlineTrash, HiOutlineChevronUp, HiOutlineChevronDown, HiOutlineX } from 'react-icons/hi';

const cellInput = 'w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-100';
const numInput = `${cellInput} text-right`;

function emptyRowFor(columns) {
    const row = {};
    columns.forEach((c) => {
        row[c.key] = c.type === 'number' ? null : c.type === 'contacts' ? [] : '';
    });
    return row;
}

// Rows need a stable identity for React keys across add/remove/reorder —
// indexes shift on reorder/removal and cause input focus/state to jump to
// the wrong row. `_cid` is a client-only id, assigned on seed/add and
// stripped before the row is sent to the server.
let cidSeq = 0;
function makeCid() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    cidSeq += 1;
    return `cid-${Date.now()}-${cidSeq}`;
}
function withCids(rows) {
    return (rows || []).map((r) => (r._cid ? r : { ...r, _cid: makeCid() }));
}
function stripCids(rows) {
    return (rows || []).map(({ _cid, ...rest }) => rest);
}

function ContactsEditor({ contacts, onChange, disabled }) {
    const list = contacts || [];
    const update = (idx, key, val) => onChange(list.map((c, i) => (i === idx ? { ...c, [key]: val } : c)));
    const add = () => onChange([...list, { name: '', designation: '', tel: '', email: '' }]);
    const remove = (idx) => onChange(list.filter((_, i) => i !== idx));
    return (
        <div className="space-y-1">
            {list.map((c, idx) => (
                <div key={idx} className="flex flex-wrap items-center gap-1">
                    <input value={c.name || ''} placeholder="Name" onChange={(e) => update(idx, 'name', e.target.value)} disabled={disabled} className={`${cellInput} w-24`} />
                    <input value={c.designation || ''} placeholder="Designation" onChange={(e) => update(idx, 'designation', e.target.value)} disabled={disabled} className={`${cellInput} w-24`} />
                    <input value={c.tel || ''} placeholder="Tel" onChange={(e) => update(idx, 'tel', e.target.value)} disabled={disabled} className={`${cellInput} w-20`} />
                    <input value={c.email || ''} placeholder="Email" onChange={(e) => update(idx, 'email', e.target.value)} disabled={disabled} className={`${cellInput} w-32`} />
                    {!disabled && (
                        <button type="button" onClick={() => remove(idx)} aria-label="Remove contact" className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600">
                            <HiOutlineX className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>
            ))}
            {!disabled && (
                <button type="button" onClick={add} className="text-xs font-medium text-primary-700 hover:underline">
                    + Add contact
                </button>
            )}
        </div>
    );
}

function Cell({ column, value, onChange, disabled }) {
    if (column.readOnly) {
        return <span className="whitespace-pre-wrap text-sm text-gray-600">{value === null || value === undefined || value === '' ? '-' : String(value)}</span>;
    }
    if (column.type === 'contacts') {
        return <ContactsEditor contacts={value} onChange={onChange} disabled={disabled} />;
    }
    if (column.type === 'number') {
        return (
            <input
                type="number"
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
                disabled={disabled}
                className={numInput}
            />
        );
    }
    if (column.multiline) {
        return <textarea value={value ?? ''} onChange={(e) => onChange(e.target.value)} disabled={disabled} rows={2} className={cellInput} />;
    }
    return <input value={value ?? ''} onChange={(e) => onChange(e.target.value)} disabled={disabled} className={cellInput} />;
}

// Generic editable rows grid — used for plain `table` sections and reused
// per-group inside `groups-table`. Rows are always saved/replaced in full
// (see SectionMerger's `_rows` override semantics).
function RowsTable({ columns, rows, onChange, disabled, fixedRows }) {
    const updateCell = (idx, key, val) => onChange(rows.map((r, i) => (i === idx ? { ...r, [key]: val } : r)));
    const addRow = () => onChange([...rows, { ...emptyRowFor(columns), _cid: makeCid() }]);
    const removeRow = (idx) => onChange(rows.filter((_, i) => i !== idx));
    const move = (idx, dir) => {
        const other = idx + dir;
        if (other < 0 || other >= rows.length) return;
        const next = [...rows];
        const [moved] = next.splice(idx, 1);
        next.splice(other, 0, moved);
        onChange(next);
    };
    const canRestructure = !disabled && !fixedRows;

    return (
        <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
            <table className="min-w-full divide-y divide-gray-200 text-sm">
                <thead className="bg-gray-50">
                    <tr>
                        {columns.map((c) => (
                            <th key={c.key} className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">{c.label}</th>
                        ))}
                        {canRestructure && <th className="px-3 py-2" />}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {rows.map((row, idx) => (
                        <tr key={row._cid ?? idx}>
                            {columns.map((c) => (
                                <td key={c.key} className="px-3 py-2 align-top">
                                    <Cell column={c} value={row[c.key]} onChange={(v) => updateCell(idx, c.key, v)} disabled={disabled} />
                                </td>
                            ))}
                            {canRestructure && (
                                <td className="whitespace-nowrap px-3 py-2 align-top">
                                    <div className="flex items-center gap-1">
                                        <button type="button" onClick={() => move(idx, -1)} disabled={idx === 0} aria-label="Move up" className="rounded p-1 text-gray-400 hover:bg-gray-100 disabled:opacity-30">
                                            <HiOutlineChevronUp className="h-3.5 w-3.5" />
                                        </button>
                                        <button type="button" onClick={() => move(idx, 1)} disabled={idx === rows.length - 1} aria-label="Move down" className="rounded p-1 text-gray-400 hover:bg-gray-100 disabled:opacity-30">
                                            <HiOutlineChevronDown className="h-3.5 w-3.5" />
                                        </button>
                                        <button type="button" onClick={() => removeRow(idx)} aria-label="Remove row" className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                            <HiOutlineTrash className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            )}
                        </tr>
                    ))}
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={columns.length + (canRestructure ? 1 : 0)} className="px-3 py-4 text-center text-sm text-gray-400">No rows.</td>
                        </tr>
                    )}
                </tbody>
            </table>
            {canRestructure && (
                <div className="border-t border-gray-100 p-2">
                    <button type="button" onClick={addRow} className="inline-flex items-center gap-1 text-xs font-medium text-primary-700 hover:underline">
                        <HiOutlinePlus className="h-3.5 w-3.5" /> Add row
                    </button>
                </div>
            )}
        </div>
    );
}

function PlainTable({ config, data, merged, canEdit, onOverridesChange }) {
    const [rows, setRows] = useState(() => withCids(merged?.rows || []));

    const handleChange = (next) => {
        setRows(next);
        onOverridesChange({ _rows: stripCids(next) });
    };

    const resetToSystem = () => {
        const original = withCids(data?.rows || []);
        setRows(original);
        onOverridesChange({});
    };

    return (
        <div>
            <RowsTable columns={config.columns} rows={rows} onChange={handleChange} disabled={!canEdit} fixedRows={config.fixedRows} />
            {canEdit && (
                <button type="button" onClick={resetToSystem} className="mt-2 text-xs text-gray-500 hover:text-gray-700 hover:underline">
                    Reset to system data
                </button>
            )}
        </div>
    );
}

function GroupsTable({ config, merged, canEdit, onOverridesChange }) {
    const [groups, setGroups] = useState(() => (merged?.groups || []).map((g) => ({ ...g, rows: withCids(g.rows) })));

    const updateGroupRows = (gIdx, rows) => {
        const next = groups.map((g, i) => (i === gIdx ? { ...g, rows } : g));
        setGroups(next);
        onOverridesChange({ groups: next.map((g) => ({ ...g, rows: stripCids(g.rows) })) });
    };

    return (
        <div className="space-y-6">
            {groups.map((g, gIdx) => (
                <div key={g.code ?? gIdx}>
                    <h3 className="mb-2 text-sm font-semibold text-gray-900">{g.label}</h3>
                    <RowsTable columns={config.columns} rows={g.rows || []} onChange={(rows) => updateGroupRows(gIdx, rows)} disabled={!canEdit} fixedRows={false} />
                </div>
            ))}
        </div>
    );
}

const NUMBER_FIELDS = ['issued', 'open', 'closed'];

function GroupsNumbers({ merged, canEdit, onOverridesChange }) {
    const [groups, setGroups] = useState(() => merged?.groups || []);

    const buckets = [
        ['accumulative', 'Accumulative'],
        ['previous', merged?.previous_label || 'Previous'],
        ['current', merged?.current_label || 'Current'],
    ];

    const setVal = (gIdx, bucket, field, raw) => {
        const val = raw === '' ? null : Number(raw);
        const next = groups.map((g, i) => (i === gIdx ? { ...g, [bucket]: { ...g[bucket], [field]: val } } : g));
        setGroups(next);
        onOverridesChange({ groups: next });
    };

    return (
        <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
            <table className="min-w-full divide-y divide-gray-200 text-sm">
                <thead className="bg-gray-50">
                    <tr>
                        <th rowSpan={2} className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500 align-bottom">Category</th>
                        {buckets.map(([key, label]) => (
                            <th key={key} colSpan={3} className="border-l border-gray-200 px-3 py-2 text-center text-xs font-semibold uppercase text-gray-500">{label}</th>
                        ))}
                    </tr>
                    <tr>
                        {buckets.map(([key]) =>
                            NUMBER_FIELDS.map((f) => (
                                <th key={key + f} className="border-l border-gray-100 px-2 py-1 text-center text-[10px] font-medium uppercase text-gray-400 first:border-l-gray-200">{f}</th>
                            ))
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {groups.map((g, gIdx) => (
                        <tr key={g.code ?? gIdx}>
                            <td className="px-3 py-2 font-medium text-gray-700">{g.label}</td>
                            {buckets.map(([bucket]) =>
                                NUMBER_FIELDS.map((f) => (
                                    <td key={bucket + f} className="border-l border-gray-100 px-2 py-2">
                                        <input
                                            type="number"
                                            value={g[bucket]?.[f] ?? ''}
                                            onChange={(e) => setVal(gIdx, bucket, f, e.target.value)}
                                            disabled={!canEdit}
                                            className={numInput}
                                        />
                                    </td>
                                ))
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function MatrixRows({ rows, days, onChange, canEdit }) {
    const updateCount = (rIdx, dIdx, raw) => {
        const val = raw === '' ? null : Number(raw);
        onChange(rows.map((r, i) => (i === rIdx ? { ...r, counts: (r.counts || []).map((c, ci) => (ci === dIdx ? val : c)) } : r)));
    };

    return (
        <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50">
                <tr>
                    <th className="sticky left-0 z-10 bg-gray-50 px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Description</th>
                    {days.map((d, i) => (
                        <th key={i} className={`px-1 py-2 text-center text-[10px] font-medium uppercase ${d.weekend ? 'text-red-400' : 'text-gray-400'}`}>{d.label || d.date?.slice(-2)}</th>
                    ))}
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
                {rows.map((r, rIdx) => (
                    <tr key={r.no ?? rIdx}>
                        <td className="sticky left-0 z-10 bg-white px-3 py-2 font-medium text-gray-700">{r.description}</td>
                        {(r.counts || []).map((c, dIdx) => (
                            <td key={dIdx} className="px-1 py-1">
                                <input
                                    type="number"
                                    value={c ?? ''}
                                    onChange={(e) => updateCount(rIdx, dIdx, e.target.value)}
                                    disabled={!canEdit}
                                    className={`${numInput} w-12`}
                                />
                            </td>
                        ))}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function MatrixGrouped({ merged, canEdit, onOverridesChange }) {
    const days = merged?.days || [];
    const [groups, setGroups] = useState(() => merged?.groups || []);

    const updateGroupRows = (gIdx, rows) => {
        const next = groups.map((g, i) => (i === gIdx ? { ...g, rows } : g));
        setGroups(next);
        onOverridesChange({ groups: next });
    };

    return (
        <div className="space-y-6 overflow-x-auto">
            {groups.map((g, gIdx) => (
                <div key={gIdx}>
                    <h3 className="mb-2 text-sm font-semibold text-gray-900">{g.label}</h3>
                    <MatrixRows rows={g.rows || []} days={days} canEdit={canEdit} onChange={(rows) => updateGroupRows(gIdx, rows)} />
                </div>
            ))}
        </div>
    );
}

function MatrixFlat({ merged, canEdit, onOverridesChange }) {
    const days = merged?.days || [];
    const [rows, setRows] = useState(() => merged?.rows || []);

    const handleChange = (next) => {
        setRows(next);
        onOverridesChange({ rows: next });
    };

    return (
        <div className="overflow-x-auto">
            <MatrixRows rows={rows} days={days} canEdit={canEdit} onChange={handleChange} />
        </div>
    );
}

function Matrix({ config, merged, canEdit, onOverridesChange }) {
    return config.grouped
        ? <MatrixGrouped merged={merged} canEdit={canEdit} onOverridesChange={onOverridesChange} />
        : <MatrixFlat merged={merged} canEdit={canEdit} onOverridesChange={onOverridesChange} />;
}

function ProgressTable({ title, block, onChange, canEdit }) {
    const rows = block?.rows || [];
    const updateCell = (idx, key, raw) => {
        const val = raw === '' ? null : Number(raw);
        onChange({ ...block, rows: rows.map((r, i) => (i === idx ? { ...r, [key]: val } : r)) });
    };

    return (
        <div>
            <h3 className="mb-2 text-sm font-semibold text-gray-900">{title}</h3>
            <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Item</th>
                            <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">{block?.prev_label || 'Previous'}</th>
                            <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">{block?.cur_label || 'Current'}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rows.map((r, idx) => (
                            <tr key={idx}>
                                <td className="px-3 py-2 font-medium text-gray-700">{r.label}</td>
                                <td className="px-2 py-2"><input type="number" value={r.prev ?? ''} onChange={(e) => updateCell(idx, 'prev', e.target.value)} disabled={!canEdit} className={`${numInput} w-20`} /></td>
                                <td className="px-2 py-2"><input type="number" value={r.cur ?? ''} onChange={(e) => updateCell(idx, 'cur', e.target.value)} disabled={!canEdit} className={`${numInput} w-20`} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function Progress({ merged, canEdit, onOverridesChange }) {
    const [physical, setPhysical] = useState(() => merged?.physical || { rows: [] });
    const [financial, setFinancial] = useState(() => merged?.financial || { rows: [] });

    const changePhysical = (next) => {
        setPhysical(next);
        onOverridesChange({ physical: next, financial });
    };
    const changeFinancial = (next) => {
        setFinancial(next);
        onOverridesChange({ physical, financial: next });
    };

    return (
        <div className="space-y-6">
            <ProgressTable title="Physical Progress" block={physical} onChange={changePhysical} canEdit={canEdit} />
            <ProgressTable title="Financial Progress" block={financial} onChange={changeFinancial} canEdit={canEdit} />
        </div>
    );
}

// Dispatches to the right table-family editor for the section's configured
// `type` (see sectionConfig.js). `onOverridesChange` always receives the
// FULL override object for the keys this editor owns — the caller replaces
// its stored draft with it rather than merging (matches how SectionMerger
// applies overrides server-side).
export default function TableSection({ config, data, merged, canEdit, onOverridesChange }) {
    switch (config.type) {
        case 'groups-table':
            return <GroupsTable config={config} merged={merged} canEdit={canEdit} onOverridesChange={onOverridesChange} />;
        case 'groups-numbers':
            return <GroupsNumbers merged={merged} canEdit={canEdit} onOverridesChange={onOverridesChange} />;
        case 'matrix':
            return <Matrix config={config} merged={merged} canEdit={canEdit} onOverridesChange={onOverridesChange} />;
        case 'progress':
            return <Progress merged={merged} canEdit={canEdit} onOverridesChange={onOverridesChange} />;
        default:
            return <PlainTable config={config} data={data} merged={merged} canEdit={canEdit} onOverridesChange={onOverridesChange} />;
    }
}
