# SPEC-008 — Olak machinery & vehicle import (production data correction)

## Objective

Deliver **one artisan command** that makes the Assets → Machinery/Vehicles list match
`SENARAI MESIN & KENDERAAN SITE OLAK.xlsx` (26 machinery + 12 cars + 9 motorcycles),
dry-run by default, idempotent, and retire the synthetic rows previously created by
`database/seeders/EquipmentMachineryImportSeeder.php` by marking them `inactive`.

## Context (read this first — it is why the rules are shaped this way)

- Target table already exists: `vehicles` (see
  `database/migrations/2026_06_19_100040_create_vehicles_table.php`, plus
  `2026_09_07_230001_add_serial_numbers_to_vehicles.php` and
  `2026_09_13_225341_add_custom_type_to_vehicles_table.php`). **No migration is needed and none is allowed.**
- Production currently holds placeholder data: `EquipmentMachineryImportSeeder` invented
  registration numbers like `MGE-excavator-001`…`-033`, `MGE-mining-truck-001`…`-075` and put the
  *category* into `make`. The real sheets have `PLATE` + `BRAND` + `MODEL`.
- `type` is a DB **enum**: `car, van, truck, lorry, machinery, other`. There is **no** `motorcycle`,
  `excavator`, `grader`, `tipper` or `forklift` value. The sheet's category word therefore goes into
  `custom_type`. `resources/js/pages/assets/Vehicles.jsx:34` already renders `custom_type` when
  `type === 'other'`, so the UI shows the real category.
- `registration_no` is `NOT NULL UNIQUE`, but **8 of the 26 machines have no plate** in the source
  (Forklift FL07/FL08, TBM 1/2, JG 1/2, DR 1/2). The rule for those is fixed below.
- `assigned_to` is an FK to `employees`, whose names are split across `first_name`/`last_name`.
  The sheet's `USER` column holds free-text full names ("MOHD SYAFIQ BIN MOHD SAUFI", "SITE STAFF",
  "JPS JOHOR", "CHINA TEAM"). **We do not fuzzy-match them** — a wrong FK on production is worse than
  a name in a note.
- Dry-run-by-default + `--commit` is the established shape in this repo: copy
  `app/Console/Commands/ImportOpeningLeaveBalances.php`.

## Files

- CREATE `app/Console/Commands/ImportOlakEquipment.php`
- CREATE `tests/Feature/Assets/OlakEquipmentImportTest.php`
- EDIT — nothing. **No route, model, migration, seeder, Blade or `resources/js/**` file is touched.**
- DELETE — nothing. `EquipmentMachineryImportSeeder.php` stays on disk untouched (history).

## Contract

### Command

```php
protected $signature = 'assets:import-olak {--commit : Write the changes. Without this nothing is saved}';
protected $description = 'Import the OLAK site machinery & vehicle list (dry run by default)';
```

- Handles `handle(): int`, returns `Command::SUCCESS` (0) on both dry-run and `--commit`.
- **Without `--commit` it must perform zero writes** (no insert, no update, no delete) — same
  guarantee and same tone as `ImportOpeningLeaveBalances`.
- All writes under `--commit` run inside one `DB::transaction(fn () => …)`.
- Console-only. **No HTTP route, no controller change, no `routes/api.php` edit.**

### Source data — copy these constants verbatim

These are extracted and normalized from the spreadsheet by the orchestrator. Excel serial dates are
already converted to `Y-m-d`. **Do not re-derive, re-format, re-order, "fix" or add rows.** The
`src` key carries values the source could not parse; it is appended to `notes` (see Notes format).

```php
private const SOURCE = 'SENARAI MESIN & KENDERAAN SITE OLAK.xlsx';

// Sheet 1 "MESIN", source rows 5-30 (26 items)
private const MACHINES = [
    ['no' => 1, 'reg' => 'BPJ 4284', 'serial' => 'EX215', 'type' => 'machinery', 'custom' => 'Excavator', 'make' => 'VOLVO', 'model' => 'EC210D', 'year' => 2017, 'value' => 429300, 'purchased' => '2017-05-12', 'site' => 'OLAK'],
    ['no' => 2, 'reg' => 'BPL 7657', 'serial' => 'EX219', 'type' => 'machinery', 'custom' => 'Excavator', 'make' => 'VOLVO', 'model' => 'EC210D', 'year' => 2018, 'value' => 429300, 'purchased' => '2018-02-04', 'site' => 'OLAK'],
    ['no' => 3, 'reg' => 'BPG 4534', 'serial' => 'EX303(2)', 'type' => 'machinery', 'custom' => 'Excavator', 'make' => 'VOLVO', 'model' => 'EC300DL', 'year' => 2017, 'value' => 667800, 'purchased' => '2017-08-21', 'site' => 'OLAK'],
    ['no' => 4, 'reg' => 'BPE 7705', 'serial' => 'EX306(2)', 'type' => 'machinery', 'custom' => 'Excavator', 'make' => 'VOLVO', 'model' => 'EC300DL', 'year' => 2017, 'value' => 667800, 'purchased' => '2017-08-06', 'site' => 'OLAK'],
    ['no' => 5, 'reg' => 'BPE 2857', 'serial' => 'EX309', 'type' => 'machinery', 'custom' => 'Excavator', 'make' => 'VOLVO', 'model' => 'EC300DL', 'year' => 2017, 'value' => 667800, 'purchased' => '2017-09-05', 'site' => 'OLAK'],
    ['no' => 6, 'reg' => 'BPK 6670', 'serial' => 'EX316', 'type' => 'machinery', 'custom' => 'Excavator', 'make' => 'VOLVO', 'model' => 'EC300DL', 'year' => 2017, 'value' => 662500, 'purchased' => '2018-01-29', 'site' => 'OLAK'],
    ['no' => 7, 'reg' => 'BPL 9538', 'serial' => 'EX322', 'type' => 'machinery', 'custom' => 'Excavator', 'make' => 'VOLVO', 'model' => 'EC300DL', 'year' => 2017, 'value' => 662500, 'purchased' => '2018-05-04', 'site' => 'OLAK'],
    ['no' => 8, 'reg' => 'JTJ 5383', 'serial' => 'RC16', 'type' => 'machinery', 'custom' => 'Compactor', 'make' => 'BOMAG', 'model' => 'BW211D-40', 'year' => 2018, 'value' => 285000, 'purchased' => '2019-03-29', 'site' => 'OLAK'],
    ['no' => 9, 'reg' => 'JTJ 5384', 'serial' => 'RC17', 'type' => 'machinery', 'custom' => 'Compactor', 'make' => 'BOMAG', 'model' => 'BW211D-40', 'year' => 2018, 'value' => 285000, 'purchased' => '2019-03-29', 'site' => 'OLAK'],
    ['no' => 10, 'reg' => 'BPB 1445', 'serial' => 'MR08', 'type' => 'truck', 'custom' => 'Tipper Lorry', 'make' => 'HINO', 'model' => 'FR3F', 'year' => 2016, 'value' => 201400, 'purchased' => '2016-10-12', 'site' => 'OLAK'],
    ['no' => 11, 'reg' => 'JRD6142', 'serial' => 'BH04', 'type' => 'machinery', 'custom' => 'Backhoe', 'make' => 'JCB', 'model' => '3DXS I', 'year' => 2015, 'value' => 210000, 'purchased' => '2015-03-11', 'site' => 'OLAK'],
    ['no' => 12, 'reg' => '', 'serial' => 'FL07', 'type' => 'other', 'custom' => 'Forklift', 'make' => 'CPC', 'model' => '', 'year' => 2021, 'value' => 39640, 'purchased' => '2021-09-11', 'site' => 'OLAK'],
    ['no' => 13, 'reg' => '', 'serial' => 'FL08', 'type' => 'other', 'custom' => 'Forklift', 'make' => 'CPC', 'model' => '', 'year' => 2021, 'value' => 39640, 'purchased' => '2021-09-11', 'site' => 'OLAK'],
    ['no' => 14, 'reg' => 'WBB 7445', 'serial' => 'BD01', 'type' => 'machinery', 'custom' => 'Bulldozer', 'make' => 'KOMATSU', 'model' => 'D60P-6', 'year' => 1978, 'value' => null, 'purchased' => '2012-06-25', 'site' => 'OLAK'],
    ['no' => 15, 'reg' => 'AJC 3911', 'serial' => 'MG01', 'type' => 'machinery', 'custom' => 'Motor Grader', 'make' => 'CATERPILLAR', 'model' => '12 G', 'year' => 2008, 'value' => 250000, 'purchased' => '2012-02-10', 'site' => 'OLAK'],
    ['no' => 16, 'reg' => 'MBM 1087', 'serial' => '', 'type' => 'lorry', 'custom' => 'Lori Rigid Hino', 'make' => 'HINO', 'model' => 'WU410R-HKMMS3', 'year' => 2007, 'value' => 65000, 'purchased' => '2012-10-10', 'site' => 'OLAK'],
    ['no' => 17, 'reg' => 'W 1685 F', 'serial' => '', 'type' => 'lorry', 'custom' => 'Lori Rigid Hino', 'make' => 'HINO', 'model' => 'WU710R-HKMML3 (UBS)', 'year' => 2013, 'value' => 105760, 'purchased' => null, 'site' => 'OLAK', 'src' => 'Delivery 2013'],
    ['no' => 18, 'reg' => 'VGS 8756', 'serial' => '', 'type' => 'lorry', 'custom' => 'Lori Rigid Hino', 'make' => 'HINO', 'model' => 'XZC730R-WKFRL3 UBS A', 'year' => 2021, 'value' => 139900, 'purchased' => '2021-09-07', 'site' => 'OLAK'],
    ['no' => 19, 'reg' => 'VGW 8105', 'serial' => '', 'type' => 'lorry', 'custom' => 'Lori Rigid Hino', 'make' => 'HINO', 'model' => 'XZC730R-WKFRL3', 'year' => 2021, 'value' => 139900, 'purchased' => '2025-05-08', 'site' => 'OLAK'],
    ['no' => 20, 'reg' => 'JPP 5135', 'serial' => '', 'type' => 'lorry', 'custom' => 'Lori Rigid Hino', 'make' => 'HINO', 'model' => 'WU710R-HKMML3 (UBS)', 'year' => 2012, 'value' => null, 'purchased' => null, 'site' => 'OLAK'],
    ['no' => 21, 'reg' => '', 'serial' => 'TBM 1', 'type' => 'machinery', 'custom' => 'Tunnel Boring Machine', 'make' => 'NPD SERIES POLYGON', 'model' => 'DN4050-G', 'year' => 2019, 'value' => null, 'purchased' => null, 'site' => 'OLAK'],
    ['no' => 22, 'reg' => '', 'serial' => 'TBM 2', 'type' => 'machinery', 'custom' => 'Tunnel Boring Machine', 'make' => 'NPD SERIES POLYGON', 'model' => 'DN4050-G', 'year' => 2019, 'value' => null, 'purchased' => null, 'site' => 'OLAK'],
    ['no' => 23, 'reg' => '', 'serial' => 'JG 1', 'type' => 'machinery', 'custom' => 'High Pressure Rotary Jet Grouting', 'make' => 'ANMAN', 'model' => 'SA-180A', 'year' => 2022, 'value' => null, 'purchased' => null, 'site' => 'OLAK'],
    ['no' => 24, 'reg' => '', 'serial' => 'JG 2', 'type' => 'machinery', 'custom' => 'High Pressure Rotary Jet Grouting', 'make' => 'ANMAN', 'model' => 'SA-180A', 'year' => 2022, 'value' => null, 'purchased' => null, 'site' => 'OLAK'],
    ['no' => 25, 'reg' => '', 'serial' => 'DR 1', 'type' => 'machinery', 'custom' => 'Water Well Drilling Rig', 'make' => 'HENGWANG', 'model' => 'XSL3/160', 'year' => 2022, 'value' => null, 'purchased' => null, 'site' => 'OLAK'],
    ['no' => 26, 'reg' => '', 'serial' => 'DR 2', 'type' => 'machinery', 'custom' => 'Water Well Drilling Rig', 'make' => 'HENGWANG', 'model' => 'XSL3/160', 'year' => 2022, 'value' => null, 'purchased' => null, 'site' => 'OLAK'],
];

// Sheet 2 "KENDERAAN": section KERETA rows 6-17 (12), section MOTORSIKAL rows 22-29 (8 stored;
// source BIL 6 is missing from the sheet — do NOT invent it).
private const VEHICLES = [
    ['no' => 1, 'reg' => 'WB160J', 'type' => 'car', 'custom' => null, 'make' => 'FORD', 'model' => 'RANGER', 'year' => 2015, 'value' => 123006.45, 'purchased' => '2015-03-25', 'roadtax' => '2027-03-16', 'insurance' => null, 'user' => 'MOHD SYAFIQ BIN MOHD SAUFI', 'site' => 'OLAK, MUAR', 'src' => 'Insurance 16/3/207'],
    ['no' => 2, 'reg' => 'VA5631', 'type' => 'car', 'custom' => null, 'make' => 'HONDA', 'model' => 'CRV 2.4 L', 'year' => 2016, 'value' => 172600, 'purchased' => '2016-10-01', 'roadtax' => '2026-12-23', 'insurance' => '2026-12-23', 'user' => 'ZHOU FENG', 'site' => 'OLAK, MUAR'],
    ['no' => 3, 'reg' => 'JNC7510', 'type' => 'car', 'custom' => null, 'make' => 'HONDA', 'model' => 'CRV 2-0L I-VTEC', 'year' => 2011, 'value' => 145948.1, 'purchased' => '2011-09-12', 'roadtax' => '2026-10-27', 'insurance' => '2026-10-27', 'user' => 'ALINA', 'site' => 'OLAK, MUAR'],
    ['no' => 4, 'reg' => 'WB6104K', 'type' => 'car', 'custom' => null, 'make' => 'HONDA', 'model' => 'HR-V 1.8L S_2', 'year' => 2015, 'value' => 95729.71, 'purchased' => '2015-04-17', 'roadtax' => '2027-04-23', 'insurance' => '2027-04-23', 'user' => 'IZZUL KHAIRI', 'site' => 'OLAK, MUAR'],
    ['no' => 5, 'reg' => 'JLC7108', 'type' => 'car', 'custom' => null, 'make' => 'MITSUBISHI', 'model' => 'TRITON', 'year' => 2008, 'value' => 84335.8, 'purchased' => '2008-07-30', 'roadtax' => '2026-11-06', 'insurance' => '2026-11-06', 'user' => 'CHINA TEAM', 'site' => 'OLAK, MUAR'],
    ['no' => 6, 'reg' => 'VDK9446', 'type' => 'car', 'custom' => null, 'make' => 'NISSAN', 'model' => 'NAVARA', 'year' => 2016, 'value' => 95000, 'purchased' => '2017-12-18', 'roadtax' => '2026-10-16', 'insurance' => '2026-10-16', 'user' => 'HAFIZ HUZAIRI', 'site' => 'OLAK, MUAR'],
    ['no' => 7, 'reg' => 'JNJ1395', 'type' => 'car', 'custom' => null, 'make' => 'TOYOTA', 'model' => 'HILUX 2.5 MT', 'year' => 2012, 'value' => 90823.8, 'purchased' => '2012-01-17', 'roadtax' => '2026-10-16', 'insurance' => '2026-10-16', 'user' => 'KHAIRIL', 'site' => 'OLAK, MUAR'],
    ['no' => 8, 'reg' => 'VBL1055', 'type' => 'car', 'custom' => null, 'make' => 'PERODUA', 'model' => 'BEZZA -1300 X', 'year' => 2018, 'value' => 45140.37, 'purchased' => '2018-04-09', 'roadtax' => '2027-04-23', 'insurance' => '2027-04-23', 'user' => 'NORAZLINDA BINTI SABARUDIN', 'site' => 'OLAK, MUAR'],
    ['no' => 9, 'reg' => 'QM8599F', 'type' => 'car', 'custom' => null, 'make' => 'TOYOTA', 'model' => 'HILUX 2.4G MT', 'year' => 2019, 'value' => 95000, 'purchased' => '2022-04-28', 'roadtax' => '2027-04-26', 'insurance' => '2027-04-26', 'user' => 'AHMAD NUR HAZIQ BIN ROSDI', 'site' => 'OLAK, MUAR'],
    ['no' => 10, 'reg' => 'VJA5812', 'type' => 'car', 'custom' => null, 'make' => 'VOLVO', 'model' => 'XC90 T8', 'year' => 2022, 'value' => 163024, 'purchased' => '2026-06-19', 'roadtax' => '2027-06-23', 'insurance' => '2027-06-23', 'user' => 'MOHD SYAFIQ BIN MOHD SAUFI', 'site' => 'OLAK, MUAR'],
    ['no' => 11, 'reg' => 'VQK9101', 'type' => 'car', 'custom' => null, 'make' => 'HONDA', 'model' => 'CR-V 1.5 V', 'year' => 2026, 'value' => 181000, 'purchased' => '2026-02-03', 'roadtax' => '2027-02-02', 'insurance' => '2027-02-02', 'user' => 'PENGARAH JPS JOHOR', 'site' => 'OLAK, MUAR'],
    ['no' => 12, 'reg' => 'VQK9108', 'type' => 'car', 'custom' => null, 'make' => 'HONDA', 'model' => 'CR-V 1.5 V', 'year' => 2026, 'value' => 181000, 'purchased' => '2026-02-03', 'roadtax' => '2027-02-02', 'insurance' => '2027-02-02', 'user' => 'JPS JOHOR', 'site' => 'OLAK, MUAR'],
    ['no' => 1, 'reg' => 'JNM7630', 'type' => 'other', 'custom' => 'Motorcycle', 'make' => 'SUZUKI', 'model' => 'SMASH V115', 'year' => 2011, 'value' => 4650, 'purchased' => '2012-07-04', 'roadtax' => '2024-04-23', 'insurance' => '2024-04-23', 'user' => 'IKMAL ALIFF', 'site' => 'OLAK, MUAR'],
    ['no' => 2, 'reg' => 'JVC4948', 'type' => 'other', 'custom' => 'Motorcycle', 'make' => 'MODENAS', 'model' => 'KRISS MJ110', 'year' => 2021, 'value' => 3300, 'purchased' => '2022-03-28', 'roadtax' => '2024-03-28', 'insurance' => '2024-03-28', 'user' => 'SITE STAFF', 'site' => 'OLAK, MUAR'],
    ['no' => 3, 'reg' => 'JSQ942', 'type' => 'other', 'custom' => 'Motorcycle', 'make' => 'MODENAS', 'model' => 'KRISS MJ110', 'year' => 2021, 'value' => 3500, 'purchased' => '2022-06-13', 'roadtax' => '2027-01-15', 'insurance' => '2027-01-15', 'user' => 'KHAIROL', 'site' => 'OLAK, MUAR'],
    ['no' => 4, 'reg' => 'WFM4095', 'type' => 'other', 'custom' => 'Motorcycle', 'make' => 'MODENAS', 'model' => 'KRISS MJ110', 'year' => 2022, 'value' => 3500, 'purchased' => '2022-08-18', 'roadtax' => '2027-02-09', 'insurance' => '2027-02-09', 'user' => 'AFIQ SYAHMI BIN MUHAMAD ZAKI', 'site' => 'OLAK, MUAR'],
    ['no' => 5, 'reg' => 'NDB8805', 'type' => 'other', 'custom' => 'Motorcycle', 'make' => 'HONDA', 'model' => 'AFS 110MCSH', 'year' => 2017, 'value' => 4500, 'purchased' => '2018-06-04', 'roadtax' => '2024-09-17', 'insurance' => '2024-09-17', 'user' => 'HAKIM', 'site' => 'OLAK, MUAR'],
    ['no' => 7, 'reg' => 'VRF9501', 'type' => 'other', 'custom' => 'Motorcycle', 'make' => 'YAMAHA', 'model' => 'Y15ZR', 'year' => 2026, 'value' => null, 'purchased' => null, 'roadtax' => null, 'insurance' => null, 'user' => 'JPS JOHOR', 'site' => 'OLAK, MUAR'],
    ['no' => 8, 'reg' => 'VRF9485', 'type' => 'other', 'custom' => 'Motorcycle', 'make' => 'YAMAHA', 'model' => 'Y15ZR', 'year' => 2026, 'value' => null, 'purchased' => null, 'roadtax' => null, 'insurance' => null, 'user' => 'JPS JOHOR', 'site' => 'OLAK, MUAR'],
    ['no' => 9, 'reg' => 'VAT5021', 'type' => 'other', 'custom' => 'Motorcycle', 'make' => 'YAMAHA', 'model' => 'Y15ZR', 'year' => 2016, 'value' => null, 'purchased' => null, 'roadtax' => null, 'insurance' => null, 'user' => 'SITE STAFF', 'site' => 'OLAK, MUAR'],
];
```

`MACHINES` has 26 rows, `VEHICLES` has 20 rows (12 car + 8 motorcycle) → **46 vehicle records**.
(The count is 46, not 47: the source's own motorcycle numbering skips BIL 6.)

### Field mapping (both tables)

| Constant key | `vehicles` column | Rule |
|---|---|---|
| `reg` | `registration_no` | `trim($reg)`; **if empty**, use `strtoupper(str_replace(' ', '-', $serial))` → yields `FL07`, `FL08`, `TBM-1`, `TBM-2`, `JG-1`, `JG-2`, `DR-1`, `DR-2`. If `reg` is empty **and** `serial` is empty, `ASK` (return without writing that row and print `SKIP <no>: no plate and no serial`). |
| `serial` | `serial_no` | copy as-is; empty string → `null` |
| `type` | `type` | copy as-is (already enum-safe) |
| `custom` | `custom_type` | copy as-is; `null` stays `null` |
| `make` | `make` | copy as-is — this is the **brand** |
| `model` | `model` | copy as-is; empty string → `null` |
| `year` | `year` | copy as-is; `null` stays `null` |
| `value` | `current_value` | copy as-is; `null` stays `null` |
| `purchased` | `purchase_date` | copy as-is; `null` stays `null` |
| — | `status` | `'active'` on create; on update set `'active'` too (see "Never clobber", exception below) |
| — | `assigned_to` | **never written** |
| — | `chassis_no`, `engine_no` | **never written** — must not appear in the attribute array at all, so an existing value survives an update |
| — | `created_by` | `User::role('Admin & HR')->first()?->id` — **nullable, no `?? 1` fallback** (the column is nullable; `1` may not exist and would break the FK) |

Upsert on the key `registration_no` via `Vehicle::updateOrCreate(['registration_no' => $no], $attrs)`.
`Vehicle` uses `SoftDeletes`, so `updateOrCreate` will not see trashed rows. **Do not change that** —
if a plate collides with a trashed row the unique index will throw; that is a desirable loud failure.

### Notes format (exact, so re-runs are stable)

`notes` = `'Imported from ' . self::SOURCE . ' | Sheet ' . $sheet . ' # ' . $no . ' | Site: ' . $site`
then, only when the part applies, append in this order:
1. `' | User: ' . $user` (VEHICLES rows with a non-empty `user`)
2. `' | No plate in source — key is the serial number'` (when `reg` was empty)
3. `' | ' . $src` (when the row has a `src` key)

Example for `MACHINES` #12 →
`Imported from SENARAI MESIN & KENDERAAN SITE OLAK.xlsx | Sheet MESIN # 12 | Site: OLAK | No plate in source — key is the serial number`

### Legacy placeholder rows

Identify them **only** by the exact `notes` string the old seeder wrote
(`database/seeders/EquipmentMachineryImportSeeder.php:24`): `'Imported from Equipment & Machinery list'`.
Set `status = 'inactive'` and **nothing else** — `notes` must stay byte-identical, otherwise a second
run can no longer find them.

- MUST NOT match on `registration_no LIKE 'MGE-%'`: `MGE` is a real Selangor plate series and would
  deactivate genuine machines.
- MUST NOT soft-delete, restore, or edit any other column of those rows.
- MUST NOT touch rows that are already `inactive`, and MUST NOT touch trashed rows.
- Print the registration numbers it is retiring (one per line) before the summary.

### Project assignment (SITE column)

Resolve the OLAK project at runtime — the primary key is unknown from this machine:

```php
$projects = Project::where('name', 'like', '%OLAK%')->orWhere('code', 'like', '%OLAK%')->get();
```

- Exactly **1** match → for every vehicle this command creates/updates, ensure an open assignment
  exists: if the vehicle already has a row with `released_at = null`, leave it alone (and if that open
  row points at a *different* project, print `CONFLICT <reg>: open assignment is project <id>` and do
  not touch it). Otherwise create
  `['vehicle_id', 'project_id', 'assigned_at' => $purchase_date ?: now()->toDateString(), 'released_at' => null, 'created_by' => $creatorId, 'notes' => 'Imported from ' . self::SOURCE]`.
- **0 or >1** matches → create nothing, print `ASSIGN skipped: <n> projects match "OLAK"`.
- MUST NOT create, rename or update any `Project` row.

### Road tax / insurance documents (VEHICLES rows only)

For each of `roadtax` and `insurance` when non-null, on the vehicle just upserted:

- If a `VehicleDocument` with that `vehicle_id` and `doc_type` (`'road_tax'` / `'insurance'`)
  **already exists in any form** → print `DOC skip <reg>: <doc_type> already present` and create nothing.
- Else create `['vehicle_id', 'doc_type', 'expiry_date' => <the date>, 'notes' => 'Imported from ' . self::SOURCE]`.
  `start_date`, `amount`, `provider`, `policy_or_ref_no`, `file_path`, `file_name` stay unset (the sheet
  gives no such value; the `PURCHASED PRICE` column is the vehicle price, not the document premium).
- `doc_type` is a DB enum: only `road_tax`, `insurance`, `permit`, `other` are legal. Motorcycles and
  cars with `roadtax`/`insurance` = `null` (the three last rows) get no document.
- MUST NOT update or delete an existing document. Never fabricate `amount`.

Note: several road-tax expiries in the source are already in the past (`2024-04-23` etc.). Store them
as-is — the Documents tab is meant to show them as overdue. Do not "correct" them.

### Console output (both modes must print this shape)

```
Machinery: 26 | Vehicles: 20
CREATE  BPJ 4284   VOLVO EC210D        [machinery/Excavator]
UPDATE  WB160J     FORD RANGER         [car]        was: notes="…"   (truncate old notes to 40 chars)
DOC     WB160J     road_tax expiry 2027-03-16
ASSIGN  WB160J     -> project OLAK-PRJ
RETIRE  MGE-excavator-001 … MGE-excavator-033  (33 rows)
---
Creates 46 | Updates 0 | Docs 34 | Assigns 46 | Retired 177
Dry run — nothing written. Re-run with --commit.
```

- With `--commit`, the last line becomes `Committed.` followed by the live counts
  `Vehicle::count()` and `Vehicle::where('status','inactive')->count()`.
- When `--commit` is passed, print the same per-row lines, prefixed identically, so the operator can
  diff the two runs.
- Rows where a plate already exists **and its `notes` does not contain `self::SOURCE`** must be
  printed as `UPDATE … (hand-entered row — overwriting)`. The command still overwrites them: the
  spreadsheet is the source of truth for this import. This line exists so the operator sees it.

## Constraints

- **MUST** follow the structure and tone of `app/Console/Commands/ImportOpeningLeaveBalances.php`
  (dry-run default, `--commit` gate, docblock explaining why it exists).
- **MUST** be idempotent: running `--commit` twice leaves identical row counts and identical `notes`.
- **MUST** keep every column not named in the mapping table untouched on update.
- **MUST** put all 46 data rows in the two class constants exactly as given, verbatim, in the given order.
- **MUST NOT** write anything without `--commit`. **MUST NOT** delete or soft-delete any row.
  **MUST NOT** create a migration, model change, route, controller change or frontend change.
  **MUST NOT** run `migrate`, `db:seed`, `migrate:fresh`, or touch `storage/`.
  **MUST NOT** read `.env` or print credentials.
  **MUST NOT** modify `database/seeders/EquipmentMachineryImportSeeder.php`.
  **MUST NOT** set `assigned_to` by matching employee names.
  **MUST NOT** "improve" any date, plate, spelling or brand in the constants (e.g. `16/3/207`,
  `BEZZA -1300 X`, `CRV 2-0L I-VTEC`, `12 G`, `W 1685 F` are intentional — they are what the sheet says).
- **PREFER** Eloquent (`Vehicle::updateOrCreate`, `VehicleDocument::create`) over raw `DB::table`,
  matching how `EquipmentMachineryImportSeeder` and `VehicleController` already write these rows.
- **PREFER** one private method per step (`retireLegacy()`, `importMachines()`, `importVehicles()`,
  `resolveProject()`, `summary()`), each taking a mutable counter array or returning counts.
- **ASK** (return `BLOCKED` in the run report, do not guess) if: a `type` value in the constants is
  rejected by the SQLite/MySQL enum in a test; any mapping above looks unsatisfiable against the real
  schema; or you feel a source value is a typo worth correcting.

## Verification

Run from the repo root. **`timeout` does not exist on this machine (macOS) — do not use it.**
**`vendor/bin/pint` has no exec bit here — always invoke it as `php vendor/bin/pint`.**

- [ ] `php artisan test tests/Feature/Assets` passes (2 existing test files must stay green + yours)
- [ ] `php vendor/bin/pint --dirty` reports no style changes needed
- [ ] **Do not run the whole suite.** On this machine `php artisan test` (default 128M) dies with
      `Allowed memory size of 134217728 bytes exhausted … routes/api.php:710` **before** your change —
      a pre-existing condition of the uncommitted environment module, not your defect. The
      orchestrator runs `php -d memory_limit=768M artisan test` at review time and owns that comparison.
- [ ] `php artisan assets:import-olak` on a scratch DB writes nothing: capture
      `php artisan tinker --execute="echo \App\Models\Vehicle::count();"` before and after → identical
- [ ] `php artisan assets:import-olak --commit` then again `--commit` → the second run prints
      `Creates 0 | Updates 46` and `Vehicle::count()` is unchanged
- [ ] `php artisan assets:import-olak --commit | head -5` shows a `Machinery: 26 | Vehicles: 20` first line

### Test file requirements (`tests/Feature/Assets/OlakEquipmentImportTest.php`)

Follow the local conventions in `tests/Feature/Assets/VehicleProjectSyncTest.php`
(`RefreshDatabase`, private helpers, `Project::create(['name','code','status' => 'in_progress'])` —
there is **no** `ProjectFactory`, do not add one). Use `$this->artisan('assets:import-olak')` and
`->assertExitCode(0)`. Cover at least:

1. dry run writes nothing (`Vehicle::count() === 0` after it)
2. `--commit` creates exactly 46 vehicles: 26 machinery-sheet rows + 20 kenderaan rows
3. a spot check of the machinery row `BPJ 4284` → `make VOLVO`, `model EC210D`, `serial_no EX215`,
   `year 2017`, `type machinery`, `custom_type Excavator`, `current_value 429300.00`,
   `purchase_date 2017-05-12`, `status active`
4. the 8 unplate'd machines get `FL07`, `FL08`, `TBM-1`, `TBM-2`, `JG-1`, `JG-2`, `DR-1`, `DR-2`
   and their notes contain `No plate in source`
5. all motorcycles are `type other` + `custom_type Motorcycle`, all cars are `type car` and their
   `make` is the brand (`HONDA`, not `HONDA CRV 2.4 L`)
6. running `--commit` twice leaves `Vehicle::count() === 46` and one `VehicleDocument` per expected doc
7. legacy retirement: create 2 rows with `notes = 'Imported from Equipment & Machinery list'`,
   run `--commit`, assert they are `inactive` and their `notes` unchanged; create a row with
   `registration_no = 'MGE 1234'` and a different note and assert its `status` is untouched
8. `assigned_to` is null on every imported row
9. documents: a vehicle that already has a `road_tax` document does not get a second one
10. project: one project whose `name` contains `OLAK` → 46 open assignments; two matching projects →
    zero assignments and exit code 0; no matching project → zero assignments and exit code 0

## Loop control

- Retry budget: 3 REJECT rounds on this SPEC.
- Turn budget: 60 (`maxTurns` caps it too).
- On block: write the exact question into `.qwen/runs/RUN-008.md` and return `BLOCKED: <question>`.
- Do **not** run the command against production and do **not** commit anything — the orchestrator commits.

## Security notes

- **Attack surface:** none new. Console-only; no route, no request input, no file upload, no Blade
  change. `php artisan assets:import-olak` is available to anyone with shell/cPanel-terminal access —
  that is the same trust level as `db:seed`, already true in this repo.
- **Authz:** the command writes the same rows that `assets.manage` guards in
  `routes/api.php` (`VehicleController`). It deliberately bypasses that gate because it runs as an
  operator, like every other seeder/command here. Do not add a `--user=` impersonation flag.
- **Data exposure:** vehicle plates, purchase prices and driver names are company-internal asset data.
  They are printed to the console in the dry run by design (the operator must review before
  `--commit`). Do not log them anywhere else, and never `dd()` a full model.
- **Mass assignment:** everything goes through `$fillable` on `App\Models\Vehicle` /
  `VehicleDocument` — both already list every column used here. Do **not** add to `$fillable`,
  do not use `forceFill`, `unguard` or `->fill(...)->save()` with raw keys.
- **Integrity risk that must not be "fixed":** `retireLegacy()` bulk-updates up to ~177 rows. It is
  scoped by an exact `notes` string; the `LIKE 'MGE-%'` shortcut is forbidden above because it would
  hit real Selangor plates. `site_log_machinery.vehicle_id` keeps its FK valid — we only flip `status`.
- **Multi-tenancy / project scoping:** this is a global asset master list, deliberately not scoped per
  project; the OLAK link is recorded through `vehicle_project_assignments`, not by filtering writes.

---

## Round 1 (orchestrator directives — read `.qwen/reviews/REVIEW-008-olak-machinery-import.md` too)

Round 0 hit `MAX_TURNS` and left the code non-functional. **Both files already exist on disk. Do not
re-implement them from scratch and do not touch the two data constants** — I machine-diffed all 46 rows
against this SPEC and they are byte-identical. Your job is a surgical fix of the 5 Blocking items:

1. `:90-96` **dry run must write nothing.** Pass `bool $commit` into `importAll()`, `retireLegacy()`
   and `assignProjects()`; when false, print the plan and skip every `updateOrCreate`/`create`/`update`.
   `DB::transaction()` wraps only the writing path. `test_dry_run_writes_nothing` proves it.
2. `:228` delete `'serial_no' => $row['serial'] ?: null` from the **vehicle** attribute array. The
   KENDERAAN sheet has no SERIAL column; `VEHICLES` rows have no `serial` key. Do not add the key to
   the data and do not paper over it with `?? null` — `serial_no` is simply not written for vehicles,
   same as `chassis_no`/`engine_no`. (`MACHINES` keeps writing `serial_no` — that one is real.)
3. `:358` `Vehicle::all()` → iterate **only** the registrations this command upserted
   (`Vehicle::whereIn('registration_no', $touched)`). Assigning every vehicle in the database to OLAK
   would corrupt other sites' plant on production.
4. `:190`/`:250` replace the literal `was: notes="..."` with the previous notes truncated to 40 chars,
   and add the `(hand-entered row — overwriting)` label when existing notes lack `self::SOURCE`.
5. `tests/…:292` the "no matching project" fixture is named `'No OLAK Here'`, which *contains* `OLAK`
   and therefore matches. Rename it (e.g. `'Bukit Talam'`).

Then add the two missing tests named in the review's `### Tests` section (unrelated pre-existing vehicle
must not receive an assignment; second `--commit` reports `Creates 0`), and clear the 6 Non-blocking
items only if they cost you nothing — they do not justify extra turns.

Budget discipline: you have 60 turns and last time ran out. Read only these three files before editing —
`app/Console/Commands/ImportOlakEquipment.php`,
`tests/Feature/Assets/OlakEquipmentImportTest.php`, and this Round 1 section. Do not re-read the
spreadsheet, the migrations, `ImportOpeningLeaveBalances.php` or the model files; the facts you need are
all written above. Verify with exactly two commands, nothing more:
`php artisan test tests/Feature/Assets` and `php vendor/bin/pint --dirty`.

---

## Round 2 (final retry — 3 Blocking items, all small; do not redesign anything)

Round 1 fixed the crash (`serial_no` gone from the vehicle path — verified: one `serial_no` write left,
at `:188`, in the machine path), retired `Vehicle::all()` in favour of `$touchedRegs`, added the
`was: notes="…"` truncation and `(hand-entered row — overwriting)` label (`:210-211`, `:274-275`), and
renamed the OLAK fixture. All 22 Assets tests pass. I verified that myself. Two things went wrong:

1. **Blocking — the dry run no longer prints the plan.** `importAll()` at `:142-157` has an `else`
   branch that re-implements the plate fallback and calls *neither* `upsertMachine()` nor
   `upsertVehicle()`. So `php artisan assets:import-olak` outputs only
   `Machinery: 26 | Vehicles: 20` and then `Creates 0 | Updates 0 | Docs 0 | Assigns 46` —
   self-contradicting, and the operator has nothing to review before `--commit`. The per-row output
   contract in `### Console output` is the entire point of the dry run.
   **Directive:** delete that `else` block. Keep ONE code path — call `upsertMachine()` and
   `upsertVehicle()` for every row in both modes, exactly as the `$commit` branch does, and let the
   existing `if ($commit)` guards at `:217`, `:281`, `:338`, `:364`, `:409` be the only difference.
   Do not wrap the dry path in `DB::transaction` (there is nothing to write).
   Both branches must end up returning the same `$createCount`/`$updateCount`/`$docCount` for a given
   database state, and the dry run must print 46 `CREATE` lines on an empty database.
2. **Blocking — that single code path makes `upsertDocument()` crash on new rows.** It currently does
   `Vehicle::where('registration_no', $reg)->sole()` (`:323`), which throws `NoRecordsFound` in a dry
   run for every row that does not exist yet — i.e. all 46 on an empty database. Round 1 avoided this
   only by skipping the whole plan, which is item 1.
   **Directive:** have `upsertMachine()`/`upsertVehicle()` pass the model they already hold in memory
   into `upsertDocument(Vehicle $vehicle, …)` instead of looking it up by plate. `updateOrCreate()`
   returns the model; in dry-run mode use the instance you fetched/built. No new query, no `sole()`.
3. **Blocking — the two tests this SPEC required in Round 1 were never added** (still 12 test methods;
   `grep` finds no `expectsOutput`, no unrelated-vehicle fixture). Item 1 and the `$touchedRegs` fix in
   Round 1 are each invisible to the current suite — that is exactly why they both shipped broken.
   **Directive:** add
   - `test_dry_run_prints_the_full_plan`: on an empty DB, run `assets:import-olak` and assert the
     captured output contains `Machinery: 26 | Vehicles: 20`, at least one `CREATE  BPJ 4284` line, a
     `CREATE` line for all 46 rows (count them), a `DOC     WB160J     road_tax expiry 2027-03-16`
     line, and that the summary says `Creates 46`; then assert `Vehicle::count() === 0` — the same
     test must prove it prints the plan *and* writes nothing. Use
     `Artisan::queue('assets:import-olak'); Artisan::flush(); $out = Artisan::output();` or
     `expectsOutput` — whichever you can get to assert reliably.
   - `test_unrelated_vehicle_is_not_assigned`: create `Vehicle` `ABC 111` with `notes` not containing
     `self::SOURCE` and one OLAK project, run `--commit`, then assert
     `VehicleProjectAssignment::count() === 46` and that `ABC 111` has zero assignment rows.

Non-blocking, only if free: the `ASSIGN` line should name the project code, and the two branches of the
plate fallback should share one private helper (`$this->registrationFor(array $row): ?string`) so the
rule cannot drift. Do not otherwise restructure the file.

Budget: this is the last round. Read only `app/Console/Commands/ImportOlakEquipment.php` and
`tests/Feature/Assets/OlakEquipmentImportTest.php`; do not re-read the SPEC body, the review, the
spreadsheet, models or migrations — everything above is complete. Verify with
`php artisan test tests/Feature/Assets` and `php vendor/bin/pint --dirty` only. If the two new tests
fight the harness for more than a few turns, get the code right, note the harness problem verbatim in
the report, and return PARTIAL rather than exhausting your turns.

---

## Round 3 (one test method. Do not touch the command class.)

Round 2 is accepted on the code: I read it myself. `importAll()` now calls `upsertMachine()` /
`upsertVehicle()` / `handleDocuments()` in **both** modes and only the writes are behind
`if ($commit)`; `upsertDocument()` takes a `Vehicle` instance (no `sole()` left in the file); dry-run
builds `new Vehicle($attrs)` unsaved, purely so the plan can print. `$touchedRegs` is collected in both
modes. The 46 rows are still byte-identical to the SPEC. `php artisan test tests/Feature/Assets` →
`Tests: 24 passed (160 assertions)`, and `test_unrelated_vehicle_is_not_assigned` is a real test
(46 assignments, `ABC 111` has zero).

The one defect left: `test_dry_run_prints_the_full_plan` asserts only
`expectsOutput('Machinery: 26 | Vehicles: 20')` and the closing comment line, then
`Vehicle::count() === 0`. It does not look at the per-row plan at all, so the Round 1 bug — a dry run
that prints nothing — would still pass it. Its name promises something the body does not check.

**Directive** — rewrite only that method, and add one sibling:

1. `test_dry_run_prints_the_full_plan`: on an empty database, capture the command's real output and
   assert all of:
   - it contains exactly 46 lines starting with `CREATE` (count them — this is the assertion that
     matters), and one of them is `CREATE  BPJ 4284`;
   - it contains the literal `DOC     WB160J     road_tax expiry 2027-03-16`;
   - the summary line contains `Creates 46`;
   - `Vehicle::count() === 0` afterwards (it prints the plan *and* writes nothing).
2. `test_dry_run_after_a_commit_reports_updates`: run `--commit`, then run a plain dry run, and assert
   its output contains `Creates 0` and 46 lines starting with `UPDATE`. This is the pair that proves
   the plan reflects real database state instead of echoing constants.

Capture output however works in this harness — `Artisan::queue('assets:import-olak');
Artisan::flush(); $out = Artisan::output();` is the usual way; `expectOutputString`/`obeysOutput` are
alternatives. Do not weaken an assertion to make it pass, and do not change the command to satisfy a
test — the command is already correct.

Constraints unchanged: no edit to `app/Console/Commands/ImportOlakEquipment.php`, no edit to the data
constants, no migration/route/model/frontend change, no git write operations, do not run
`php artisan migrate` in any form, do not run the full suite. Verify with
`php artisan test tests/Feature/Assets` then `php vendor/bin/pint --dirty`. Budget: a handful of turns —
if `Artisan::output()` genuinely cannot capture the `line()` writes in this harness, stop, write the
verbatim failure into the report and return BLOCKED rather than inventing a weaker assertion.



