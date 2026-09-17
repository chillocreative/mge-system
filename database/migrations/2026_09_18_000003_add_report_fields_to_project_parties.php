<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_parties', function (Blueprint $table) {
            $table->string('report_role', 40)->nullable()->after('type');
            $table->string('role_label')->nullable()->after('report_role');
            $table->text('address')->nullable()->after('phone');
            $table->string('logo_path')->nullable()->after('address');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('logo_path');
        });

        Schema::create('project_party_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_party_id')->constrained('project_parties')->cascadeOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_party_contacts');
        Schema::table('project_parties', function (Blueprint $table) {
            $table->dropColumn(['report_role', 'role_label', 'address', 'logo_path', 'sort_order']);
        });
    }
};
