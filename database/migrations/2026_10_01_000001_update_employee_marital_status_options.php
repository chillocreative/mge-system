<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('marital_status', ['single', 'married', 'divorced', 'duda', 'janda', 'balu'])
                ->nullable()
                ->change();
        });

        // Keep existing records meaningful when the old generic "divorced"
        // value is replaced by the gender-specific options.
        DB::table('employees')
            ->where('marital_status', 'divorced')
            ->where('gender', 'male')
            ->update(['marital_status' => 'duda']);

        DB::table('employees')
            ->where('marital_status', 'divorced')
            ->where(function ($query) {
                $query->where('gender', 'female')->orWhereNull('gender');
            })
            ->update(['marital_status' => 'janda']);

        Schema::table('employees', function (Blueprint $table) {
            $table->enum('marital_status', ['single', 'married', 'duda', 'janda', 'balu'])
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('marital_status', ['single', 'married', 'divorced', 'duda', 'janda', 'balu'])
                ->nullable()
                ->change();
        });

        DB::table('employees')
            ->whereIn('marital_status', ['duda', 'janda', 'balu'])
            ->update(['marital_status' => 'divorced']);

        Schema::table('employees', function (Blueprint $table) {
            $table->enum('marital_status', ['married', 'single', 'divorced'])
                ->nullable()
                ->change();
        });
    }
};
