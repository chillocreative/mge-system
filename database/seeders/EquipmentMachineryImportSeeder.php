<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EquipmentMachineryImportSeeder extends Seeder
{
    /**
     * SOURCE: Equipment & Machinery List (extracted from PDF)
     * COMMAND: php artisan db:seed --class=EquipmentMachineryImportSeeder
     *
     * This seeder imports heavy equipment and machinery into the Vehicle table.
     * It is fully idempotent: running it multiple times will not crash or duplicate records.
     * Existing vehicles matching the generated registration_no will be skipped or updated safely.
     */
    public function run(): void
    {
        // Find first user with the Admin & HR role, fallback to ID 1 if none exist
        $creatorId = User::role('Admin & HR')->first()?->id ?? 1;
        $baseNotes = 'Imported from Equipment & Machinery list';

        // Defined explicitly to guarantee slug consistency and exact type mapping
        $equipmentList = [
            ['name' => 'Excavator',                  'qty' => 33, 'type' => 'machinery', 'slug' => 'excavator'],
            ['name' => 'Mini Excavator',             'qty' => 2,  'type' => 'machinery', 'slug' => 'mini-excavator'],
            ['name' => 'Compactor',                  'qty' => 8,  'type' => 'machinery', 'slug' => 'compactor'],
            ['name' => 'Tractor',                    'qty' => 4,  'type' => 'machinery', 'slug' => 'tractor'],
            ['name' => 'Dump Truck',                 'qty' => 5,  'type' => 'truck',     'slug' => 'dump-truck'],
            ['name' => 'Mining Truck',               'qty' => 75, 'type' => 'truck',     'slug' => 'mining-truck'],
            ['name' => 'Tipper Lorry',               'qty' => 14, 'type' => 'truck',     'slug' => 'tipper-lorry'],
            ['name' => 'Rigid Lorry Crane',          'qty' => 1,  'type' => 'lorry',     'slug' => 'rigid-lorry-crane'],
            ['name' => 'Semi-Trailer',               'qty' => 1,  'type' => 'lorry',     'slug' => 'semi-trailer'],
            ['name' => 'Prime Mover',                'qty' => 1,  'type' => 'lorry',     'slug' => 'prime-mover'],
            ['name' => 'Backhoe',                    'qty' => 3,  'type' => 'machinery', 'slug' => 'backhoe'],
            ['name' => 'Forklift',                   'qty' => 6,  'type' => 'other',     'slug' => 'forklift'],
            ['name' => 'Bulldozer',                  'qty' => 7,  'type' => 'machinery', 'slug' => 'bulldozer'],
            ['name' => 'Motor Grader',               'qty' => 4,  'type' => 'machinery', 'slug' => 'motor-grader'],
            ['name' => 'Lorry Rigid Hino',           'qty' => 9,  'type' => 'lorry',     'slug' => 'lorry-rigid-hino'],
            ['name' => 'Tunnel Boring Machine',      'qty' => 2,  'type' => 'machinery', 'slug' => 'tunnel-boring-machine'],
            ['name' => 'High Pressure Rotary Jet Grouting', 'qty' => 3, 'type' => 'machinery', 'slug' => 'high-pressure-rotary-jet-grouting'],
            ['name' => 'Water Well Drilling Rig',    'qty' => 2,  'type' => 'machinery', 'slug' => 'water-well-drilling-rig'],
        ];

        DB::transaction(function () use ($equipmentList, $creatorId, $baseNotes) {
            foreach ($equipmentList as $item) {
                for ($i = 1; $i <= $item['qty']; $i++) {
                    $seq = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                    $regNo = "MGE-{$item['slug']}-{$seq}";

                    Vehicle::updateOrCreate(
                        ['registration_no' => $regNo],
                        [
                            'make' => $item['name'],
                            'model' => null,
                            'type' => $item['type'],
                            'status' => 'active',
                            'year' => null,
                            'notes' => $baseNotes,
                            'created_by' => $creatorId,
                        ]
                    );
                }
            }
        });
    }
}
