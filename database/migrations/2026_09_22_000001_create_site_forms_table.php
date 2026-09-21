<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_forms', function (Blueprint $table) {
            $table->id();
            $table->string('form_type', 40)->index();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('ref_no');
            $table->date('form_date')->nullable();
            $table->string('title')->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['form_type', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_forms');
    }
};
