<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('marital_status', ['married', 'single', 'divorced'])->nullable()->after('gender');
            $table->string('spouse_name')->nullable()->after('marital_status');
            $table->string('spouse_ic_no')->nullable()->after('spouse_name');
            $table->unsignedInteger('number_of_children')->default(0)->after('spouse_ic_no');
            $table->string('emergency_contact_name')->nullable()->after('number_of_children');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'marital_status',
                'spouse_name',
                'spouse_ic_no',
                'number_of_children',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
            ]);
        });
    }
};
