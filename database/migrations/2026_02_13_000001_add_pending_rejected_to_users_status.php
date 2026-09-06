<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL keeps the native ENUM; other drivers (sqlite, used by the test
        // suite) have no MODIFY COLUMN, so widen the column to a plain string
        // instead. Both end up accepting the same set of values.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('pending', 'active', 'inactive', 'suspended', 'rejected') DEFAULT 'active'");

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'");

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });
    }
};
