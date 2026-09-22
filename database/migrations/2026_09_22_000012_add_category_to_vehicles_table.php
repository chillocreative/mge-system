<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Registration numbers of the 26 rows that live in the MESIN sheet of the OLAK
    // register. Split is by SHEET TAB, not by the `type` enum — six lorries live in
    // MESIN but carry type=truck/lorry. Embedded literally so this migration stays
    // self-contained (does not depend on ImportOlakEquipment::MACHINES).
    //
    // The six unplated machines are keyed by their serial number, and the importer
    // stores that key as strtoupper(str_replace(' ', '-', $serial)) — so the row for
    // "TBM 1" is "TBM-1". Both spellings are listed: the dash form is what the
    // importer actually writes, and the space form costs nothing and covers a row
    // entered by hand before this ran.
    private const MACHINE_REGISTRATION_NOS = [
        'BPJ 4284', 'BPL 7657', 'BPG 4534', 'BPE 7705', 'BPE 2857', 'BPK 6670',
        'BPL 9538', 'JTJ 5383', 'JTJ 5384', 'BPB 1445', 'JRD6142', 'FL07', 'FL08',
        'WBB 7445', 'AJC 3911', 'MBM 1087', 'W 1685 F', 'VGS 8756', 'VGW 8105',
        'JPP 5135',
        'TBM-1', 'TBM-2', 'JG-1', 'JG-2', 'DR-1', 'DR-2',
        'TBM 1', 'TBM 2', 'JG 1', 'JG 2', 'DR 1', 'DR 2',
    ];

    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->enum('category', ['vehicle', 'machine'])->default('vehicle')->after('type')->index();
        });

        DB::table('vehicles')
            ->whereIn('registration_no', self::MACHINE_REGISTRATION_NOS)
            ->update(['category' => 'machine']);
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
