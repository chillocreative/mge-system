<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('doc_type', ['contract', 'tender', 'sst', 'policy', 'procedure', 'other'])->default('other');
            $table->string('reference_no')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->integer('version')->default(1);
            $table->enum('status', ['draft', 'published', 'archived'])->default('published');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('doc_type');
            $table->index('reference_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_documents');
    }
};
