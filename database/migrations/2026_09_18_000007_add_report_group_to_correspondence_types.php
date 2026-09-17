<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondence_types', function (Blueprint $table) {
            $table->string('report_group', 20)->nullable()->after('color');
        });

        DB::table('correspondence_types')->where('code', 'rfi')->update(['report_group' => 'rfi']);
        DB::table('correspondence_types')->where('code', 'ncr')->update(['report_group' => 'ncr']);

        $now = now();
        foreach ([
            ['code' => 'adm', 'name' => 'ADM', 'full_name' => 'Main Issues (Administration)', 'color' => 'slate', 'report_group' => 'adm', 'sort_order' => 10],
            ['code' => 'ma', 'name' => 'MA', 'full_name' => 'Material Approval', 'color' => 'emerald', 'report_group' => 'ma', 'sort_order' => 11],
            ['code' => 'mos', 'name' => 'MOS', 'full_name' => 'Method of Statement', 'color' => 'indigo', 'report_group' => 'mos', 'sort_order' => 12],
        ] as $row) {
            if (! DB::table('correspondence_types')->where('code', $row['code'])->exists()) {
                DB::table('correspondence_types')->insert($row + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        DB::table('correspondence_types')->whereIn('code', ['adm', 'ma', 'mos'])->delete();
        Schema::table('correspondence_types', fn (Blueprint $table) => $table->dropColumn('report_group'));
    }
};
