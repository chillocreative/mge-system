<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleProjectAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import the OLAK site machinery & vehicle list from the official Excel register.
 *
 * The production vehicles table currently holds synthetic placeholder rows created by
 * EquipmentMachineryImportSeeder (registration numbers like MGE-excavator-001, MGE-mining-truck-001)
 * with the category stuffed into `make`. This command replaces those with the real list
 * from SENARAI MESIN & KENDERAAN SITE OLAK.xlsx (26 machinery + 12 cars + 8 motorcycles = 46 records).
 *
 * Dry run by default — prints what it would do and writes nothing. Pass --commit to apply.
 * Idempotent: running --commit again will report Updates only, leaving row counts identical.
 */
class ImportOlakEquipment extends Command
{
    protected $signature = 'assets:import-olak
        {--commit : Write the changes. Without this nothing is saved}';

    protected $description = 'Import the OLAK site machinery & vehicle list (dry run by default)';

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
        ['no' => 1, 'reg' => 'WB160J', 'type' => 'car', 'custom' => null, 'make' => 'FORD', 'model' => 'RANGER', 'year' => 2015, 'value' => 123006.45, 'purchased' => '2015-03-25', 'roadtax' => '2027-03-16', 'insurance' => '2027-03-16', 'user' => 'MOHD SYAFIQ BIN MOHD SAUFI', 'site' => 'OLAK, MUAR', 'src' => 'Insurance reads 16/3/207 in the source register — a missing digit; confirmed as 2027-03-16 by the owner on 2026-09-22'],
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

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $userWithRole = User::whereHas('roles', fn ($q) => $q->where('name', 'Admin & HR'))->first();
        $creatorId = $userWithRole ? $userWithRole->id : null;

        [$createCount, $updateCount, $docCount, $touchedRegs, $plannedVehicles] = $this->importAll($creatorId, $commit);
        $retiredCount = $this->retireLegacy($commit);
        $assignResult = $this->assignProjects($commit, $plannedVehicles);

        $this->summary([
            'creates' => $createCount,
            'updates' => $updateCount,
            'docs' => $docCount,
            'assigns' => $assignResult['count'],
            'conflicts' => $assignResult['conflicts'],
            'already_assigned' => $assignResult['already_assigned'],
            'skipped_assign' => $assignResult['skipped'],
            'retired' => $retiredCount,
        ], $commit);

        return self::SUCCESS;
    }

    private function importAll(?int $creatorId, bool $commit): array
    {
        $createCount = 0;
        $updateCount = 0;
        $docCount = 0;
        $touchedRegs = [];
        $plannedVehicles = [];

        $this->line('Machinery: '.count(self::MACHINES).' | Vehicles: '.count(self::VEHICLES));

        if ($commit) {
            DB::transaction(function () use ($creatorId, &$createCount, &$updateCount, &$docCount, &$touchedRegs, &$plannedVehicles) {
                foreach (self::MACHINES as $row) {
                    [$created, $updated, $vehicle] = $this->upsertMachine($row, $creatorId, true, $touchedRegs, $plannedVehicles);
                    if ($created) {
                        $createCount++;
                    }
                    if ($updated) {
                        $updateCount++;
                    }
                    if ($vehicle) {
                        $docs = $this->handleDocuments($vehicle, $row, true);
                        $docCount += $docs;
                    }
                }

                foreach (self::VEHICLES as $row) {
                    [$created, $updated, $vehicle] = $this->upsertVehicle($row, $creatorId, true, $touchedRegs, $plannedVehicles);
                    if ($created) {
                        $createCount++;
                    }
                    if ($updated) {
                        $updateCount++;
                    }
                    if ($vehicle) {
                        $docs = $this->handleDocuments($vehicle, $row, true);
                        $docCount += $docs;
                    }
                }
            });
        } else {
            // Dry run: same logic as commit, but no writes
            foreach (self::MACHINES as $row) {
                [$created, $updated, $vehicle] = $this->upsertMachine($row, $creatorId, false, $touchedRegs, $plannedVehicles);
                if ($created) {
                    $createCount++;
                }
                if ($updated) {
                    $updateCount++;
                }
                if ($vehicle) {
                    $docs = $this->handleDocuments($vehicle, $row, false);
                    $docCount += $docs;
                }
            }

            foreach (self::VEHICLES as $row) {
                [$created, $updated, $vehicle] = $this->upsertVehicle($row, $creatorId, false, $touchedRegs, $plannedVehicles);
                if ($created) {
                    $createCount++;
                }
                if ($updated) {
                    $updateCount++;
                }
                if ($vehicle) {
                    $docs = $this->handleDocuments($vehicle, $row, false);
                    $docCount += $docs;
                }
            }
        }

        return [$createCount, $updateCount, $docCount, $touchedRegs, $plannedVehicles];
    }

    private function upsertMachine(array $row, ?int $creatorId, bool $commit, array &$touchedRegs, array &$plannedVehicles): array
    {
        $no = $row['no'];
        $reg = trim($row['reg']);
        $serial = $row['serial'] ?: null;
        $site = $row['site'];

        if ($reg === '') {
            if ($serial === null || $serial === '') {
                $this->line("SKIP {$no}: no plate and no serial");

                return [false, false, null];
            }
            $reg = strtoupper(str_replace(' ', '-', $serial));
        }

        $model = $row['model'] === '' ? null : $row['model'];
        $purchased = $row['purchased'] ?: null;

        $notes = $this->buildNotes($row, 'MESIN');

        $attrs = [
            'registration_no' => $reg,
            'serial_no' => $serial,
            'type' => $row['type'],
            'category' => 'machine',
            'custom_type' => $row['custom'],
            'make' => $row['make'],
            'model' => $model,
            'year' => $row['year'],
            'current_value' => $row['value'],
            'purchase_date' => $purchased,
            'status' => 'active',
            'notes' => $notes,
            'created_by' => $creatorId,
        ];

        $exists = Vehicle::where('registration_no', $reg)->withoutTrashed()->first();
        $isNew = ! $exists;

        $noteText = $exists ? $exists->notes : '';
        if ($exists && strlen($noteText) > 0 && str_contains($noteText, self::SOURCE)) {
            $displayModel = $row['model'] ?: '';
            $this->line("UPDATE  {$reg}   {$row['make']} {$displayModel}        [{$row['type']}/{$row['custom']}]");
        } elseif ($exists) {
            $displayModel = $row['model'] ?: '';
            $shortNote = substr($noteText, 0, 40);
            $this->line("UPDATE  {$reg}   {$row['make']} {$displayModel}        [{$row['type']}/{$row['custom']}] was: notes=\"{$shortNote}\" (hand-entered row — overwriting)");
        } else {
            $displayModel = $row['model'] ?: '';
            $this->line("CREATE  {$reg}   {$row['make']} {$displayModel}        [{$row['type']}/{$row['custom']}]");
        }

        if ($commit) {
            $vehicle = Vehicle::updateOrCreate(
                ['registration_no' => $reg],
                $attrs
            );
        } else {
            // Dry run: reuse the saved row when there is one, so assignProjects() can read its
            // open assignment. Only a genuinely new row is represented by an unsaved instance.
            $vehicle = $exists ?: new Vehicle($attrs);
        }

        $touchedRegs[] = $reg;
        $plannedVehicles[] = $vehicle;

        return [$isNew, ! $isNew, $vehicle];
    }

    private function upsertVehicle(array $row, ?int $creatorId, bool $commit, array &$touchedRegs, array &$plannedVehicles): array
    {
        $no = $row['no'];
        $reg = trim($row['reg']);
        $site = $row['site'] ?? '';
        $user = $row['user'] ?? null;
        $purchased = $row['purchased'] ?: null;

        if ($reg === '' && ($row['serial'] ?? '') === '') {
            $this->line("SKIP {$no}: no plate and no serial");

            return [false, false, null];
        }

        if ($reg === '') {
            $reg = strtoupper(str_replace(' ', '-', $row['serial']));
        }

        $model = $row['model'] === '' ? null : $row['model'];

        $notes = $this->buildNotes($row, 'KENDERAAN');

        $attrs = [
            'registration_no' => $reg,
            'type' => $row['type'],
            'category' => 'vehicle',
            'custom_type' => $row['custom'],
            'make' => $row['make'],
            'model' => $model,
            'year' => $row['year'],
            'current_value' => $row['value'],
            'purchase_date' => $purchased,
            'status' => 'active',
            'notes' => $notes,
            'created_by' => $creatorId,
        ];

        $exists = Vehicle::where('registration_no', $reg)->withoutTrashed()->first();
        $isNew = ! $exists;

        $noteText = $exists ? $exists->notes : '';
        if ($exists && strlen($noteText) > 0 && str_contains($noteText, self::SOURCE)) {
            $displayModel = $row['model'] ?: '';
            $this->line("UPDATE  {$reg}   {$row['make']} {$displayModel}        [{$row['type']}]");
        } elseif ($exists) {
            $displayModel = $row['model'] ?: '';
            $shortNote = substr($noteText, 0, 40);
            $this->line("UPDATE  {$reg}   {$row['make']} {$displayModel}        [{$row['type']}] was: notes=\"{$shortNote}\" (hand-entered row — overwriting)");
        } else {
            $displayModel = $row['model'] ?: '';
            $this->line("CREATE  {$reg}   {$row['make']} {$displayModel}        [{$row['type']}]");
        }

        if ($commit) {
            $vehicle = Vehicle::updateOrCreate(
                ['registration_no' => $reg],
                $attrs
            );
        } else {
            // Dry run: reuse the saved row when there is one, so assignProjects() can read its
            // open assignment. Only a genuinely new row is represented by an unsaved instance.
            $vehicle = $exists ?: new Vehicle($attrs);
        }

        $touchedRegs[] = $reg;
        $plannedVehicles[] = $vehicle;

        return [$isNew, ! $isNew, $vehicle];
    }

    private function buildNotes(array $row, string $sheet): string
    {
        $site = $row['site'];
        $no = $row['no'];
        $base = 'Imported from '.self::SOURCE." | Sheet {$sheet} # {$no} | Site: {$site}";

        if (! empty($row['user'])) {
            $base .= ' | User: '.$row['user'];
        }

        if ($row['reg'] === '' || trim($row['reg']) === '') {
            $base .= ' | No plate in source — key is the serial number';
        }

        if (! empty($row['src'])) {
            $base .= ' | '.$row['src'];
        }

        return $base;
    }

    private function handleDocuments(Vehicle $vehicle, array $row, bool $commit): int
    {
        $docCount = 0;
        if (($row['roadtax'] ?? null) !== null) {
            $docCount += $this->upsertDocument($vehicle, $row['roadtax'], 'road_tax', $commit);
        }
        if (($row['insurance'] ?? null) !== null) {
            $docCount += $this->upsertDocument($vehicle, $row['insurance'], 'insurance', $commit);
        }

        return $docCount;
    }

    private function upsertDocument(Vehicle $vehicle, string $expiryDate, string $docType, bool $commit): int
    {
        $existing = VehicleDocument::where('vehicle_id', $vehicle->id)
            ->where('doc_type', $docType)
            ->first();

        if ($existing) {
            $this->line("DOC skip {$vehicle->registration_no}: {$docType} already present");

            return 0;
        }

        $this->line("DOC     {$vehicle->registration_no}     {$docType} expiry {$expiryDate}");

        if ($commit) {
            VehicleDocument::create([
                'vehicle_id' => $vehicle->id,
                'doc_type' => $docType,
                'expiry_date' => $expiryDate,
                'notes' => 'Imported from '.self::SOURCE,
            ]);
        }

        return 1;
    }

    private function retireLegacy(bool $commit): int
    {
        $legacyNote = 'Imported from Equipment & Machinery list';

        $rows = Vehicle::where('notes', $legacyNote)
            ->where('status', '!=', 'inactive')
            ->withoutTrashed()
            ->get(['registration_no']);

        $regs = $rows->pluck('registration_no')->toArray();

        if (! empty($regs)) {
            $this->line('RETIRE  '.implode(', ', $regs).' ('.count($regs).' rows)');

            if ($commit) {
                Vehicle::whereIn('registration_no', $regs)
                    ->where('status', '!=', 'inactive')
                    ->withoutTrashed()
                    ->update(['status' => 'inactive']);
            }
        }

        return count($regs);
    }

    private function assignProjects(bool $commit, array $plannedVehicles): array
    {
        $projects = Project::where('name', 'like', '%OLAK%')
            ->orWhere('code', 'like', '%OLAK%')
            ->get(['id', 'name', 'code']);

        if (count($projects) !== 1) {
            $this->line('ASSIGN skipped: '.count($projects).' projects match "OLAK"');

            return ['count' => 0, 'conflicts' => 0, 'already_assigned' => 0, 'skipped' => true];
        }

        $project = $projects->first();
        $count = 0;
        $conflicts = 0;
        $alreadyAssigned = 0;

        foreach ($plannedVehicles as $vehicle) {
            // A row this dry run has not created yet cannot carry a prior assignment, so there is
            // nothing to read. Never rebuild this list with a query — SPEC-008 round 1 did that and
            // assigned every vehicle in the database to OLAK.
            $open = $vehicle->exists
                ? $vehicle->projectAssignments()->whereNull('released_at')->first()
                : null;

            if ($open && $open->project_id !== $project->id) {
                $this->line("CONFLICT {$vehicle->registration_no}: open assignment is project {$open->project_id}");
                $conflicts++;

                continue;
            }

            if ($open && $open->project_id === $project->id) {
                $alreadyAssigned++;

                continue;
            }

            // Print ASSIGN line for both dry-run and commit modes
            $this->line("ASSIGN  {$vehicle->registration_no} -> {$project->code}");
            $count++;

            // Only create the assignment row when committing
            if ($commit) {
                $purchaseDate = $vehicle->purchase_date ? $vehicle->purchase_date->toDateString() : now()->toDateString();

                VehicleProjectAssignment::create([
                    'vehicle_id' => $vehicle->id,
                    'project_id' => $project->id,
                    'assigned_at' => $purchaseDate,
                    'released_at' => null,
                    'created_by' => $vehicle->created_by,
                    'notes' => 'Imported from '.self::SOURCE,
                ]);
            }
        }

        return [
            'count' => $count,
            'conflicts' => $conflicts,
            'already_assigned' => $alreadyAssigned,
            'skipped' => false,
        ];
    }

    private function summary(array $counts, bool $commit): void
    {
        $this->line('---');

        // "Assigns" counts the rows this run would attach; the parentheses account for every
        // other planned vehicle, so the three numbers always sum to the vehicles considered.
        if ($counts['skipped_assign']) {
            $assignString = 'Assigns 0 (assignment skipped)';
        } else {
            $assignString = "Assigns {$counts['assigns']} ({$counts['assigns']} new, {$counts['already_assigned']} already assigned";
            if ($counts['conflicts'] > 0) {
                $assignString .= ", {$counts['conflicts']} conflict".($counts['conflicts'] === 1 ? '' : 's');
            }
            $assignString .= ')';
        }

        $this->line("Creates {$counts['creates']} | Updates {$counts['updates']} | Docs {$counts['docs']} | {$assignString} | Retired {$counts['retired']}");

        if ($commit) {
            $this->line('Committed.');
            $this->line('Vehicle::count() = '.Vehicle::count());
            $this->line('Inactive: '.Vehicle::where('status', 'inactive')->count());
        } else {
            $this->line('Dry run — nothing written. Re-run with --commit.');
        }
    }
}
