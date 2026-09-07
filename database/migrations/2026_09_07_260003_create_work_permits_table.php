<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permit To Work (PTW) — Ciri 25 Safety.
 *
 * A permit authorises a defined high-risk task for a bounded window. The state
 * machine is deliberately small: draft → pending → approved / rejected, and an
 * approved permit is closed when the work is done. Expiry is NOT a stored state
 * — a permit is expired the moment now() passes valid_to, computed on read, so
 * it can never be left "approved" past its window by a missed cron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('permit_no')->nullable()->unique();
            $table->string('title');
            $table->enum('type', ['hot_work', 'confined_space', 'working_at_height', 'electrical', 'excavation', 'lifting', 'general'])->default('general');
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->text('precautions')->nullable();
            $table->dateTime('valid_from');
            $table->dateTime('valid_to');
            // draft: still being written; pending: submitted for approval;
            // approved/rejected: decided; closed: work finished. "expired" is
            // derived (valid_to < now while approved), never stored here.
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'closed'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['status', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_permits');
    }
};
