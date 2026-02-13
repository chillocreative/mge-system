<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Waste Tracking ──
        Schema::create('waste_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users');
            $table->enum('waste_type', ['general', 'hazardous', 'recyclable', 'construction_debris', 'chemical', 'organic', 'electronic', 'other'])->default('general');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->default('kg');
            $table->string('disposal_method')->nullable();
            $table->date('disposal_date')->nullable();
            $table->string('hauler')->nullable();
            $table->string('manifest_number')->nullable();
            $table->string('destination')->nullable();
            $table->enum('status', ['pending', 'collected', 'disposed', 'verified'])->default('pending');
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['waste_type']);
        });

        // ── Site Inspection Reports ──
        Schema::create('site_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('inspector_id')->constrained('users');
            $table->string('title');
            $table->date('inspection_date');
            $table->enum('type', ['routine', 'follow_up', 'complaint', 'regulatory', 'pre_construction', 'other'])->default('routine');
            $table->text('findings')->nullable();
            $table->text('recommendations')->nullable();
            $table->enum('overall_status', ['satisfactory', 'needs_improvement', 'unsatisfactory'])->default('satisfactory');
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'inspection_date']);
        });

        // ── Environmental Audit Records ──
        Schema::create('environmental_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('auditor_id')->constrained('users');
            $table->string('title');
            $table->date('audit_date');
            $table->enum('type', ['internal', 'external', 'regulatory'])->default('internal');
            $table->text('scope')->nullable();
            $table->text('findings')->nullable();
            $table->text('non_conformities')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'closed'])->default('scheduled');
            $table->date('next_audit_date')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'audit_date']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environmental_audits');
        Schema::dropIfExists('site_inspections');
        Schema::dropIfExists('waste_records');
    }
};
