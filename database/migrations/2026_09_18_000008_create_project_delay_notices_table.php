<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_delay_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('issue')->nullable();
            $table->foreignId('correspondence_id')->nullable()->constrained('project_correspondences')->nullOnDelete();
            $table->string('reg_number')->nullable();
            $table->date('submitted_date');
            $table->string('submitted_via', 50)->nullable();
            $table->date('reply_date')->nullable();
            $table->string('status', 10)->default('open');
            $table->text('impact')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_delay_notices');
    }
};
