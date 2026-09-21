<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_form_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_form_id')->constrained('site_forms')->cascadeOnDelete();
            $table->string('slot', 40)->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->index(['site_form_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_form_attachments');
    }
};
