<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_import_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_identity', 64)->unique();
            $table->string('workbook_hash', 64)->index();
            $table->string('source_file');
            $table->string('source_sheet', 100);
            $table->unsignedInteger('source_row');
            $table->string('source_reference')->nullable();
            $table->string('status', 20); // imported, duplicate, error, skipped
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('project_correspondence_id')->nullable()->constrained('project_correspondences')->nullOnDelete();
            $table->json('raw_payload')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_sheet', 'source_row']);
            $table->index(['project_id', 'source_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_import_sources');
    }
};
