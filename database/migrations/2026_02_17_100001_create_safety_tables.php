<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Incident Reports ──
        Schema::create('safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users');
            $table->string('title');
            $table->text('description');
            $table->date('incident_date');
            $table->time('incident_time')->nullable();
            $table->string('location')->nullable();
            $table->enum('severity', ['minor', 'moderate', 'serious', 'critical'])->default('minor');
            $table->enum('type', ['injury', 'near_miss', 'property_damage', 'environmental', 'fire', 'other'])->default('other');
            $table->string('injured_person')->nullable();
            $table->text('injury_description')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_action')->nullable();
            $table->enum('status', ['open', 'investigating', 'resolved', 'closed'])->default('open');
            $table->foreignId('investigated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['severity']);
        });

        // ── Hazard Reports ──
        Schema::create('hazard_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users');
            $table->string('title');
            $table->text('description');
            $table->string('location')->nullable();
            $table->enum('hazard_type', ['fall', 'electrical', 'chemical', 'structural', 'equipment', 'fire', 'ergonomic', 'other'])->default('other');
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('recommended_action')->nullable();
            $table->text('corrective_action')->nullable();
            $table->enum('status', ['open', 'mitigated', 'resolved', 'closed'])->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['risk_level']);
        });

        // ── Toolbox Meeting Logs ──
        Schema::create('toolbox_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('conducted_by')->constrained('users');
            $table->string('title');
            $table->text('topics');
            $table->string('location')->nullable();
            $table->date('meeting_date');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->text('action_items')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'meeting_date']);
        });

        Schema::create('toolbox_meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toolbox_meeting_id')->constrained('toolbox_meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable(); // for external attendees
            $table->timestamps();
        });

        // ── Compliance Checklists ──
        Schema::create('compliance_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('inspector_id')->constrained('users');
            $table->string('title');
            $table->enum('type', ['osha', 'fire_safety', 'ppe', 'scaffolding', 'electrical', 'excavation', 'general', 'custom'])->default('general');
            $table->date('checklist_date');
            $table->enum('overall_status', ['compliant', 'non_compliant', 'partial'])->default('partial');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'checklist_date']);
        });

        Schema::create('compliance_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('compliance_checklists')->cascadeOnDelete();
            $table->string('item_text');
            $table->enum('status', ['pass', 'fail', 'na'])->default('na');
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_checklist_items');
        Schema::dropIfExists('compliance_checklists');
        Schema::dropIfExists('toolbox_meeting_attendees');
        Schema::dropIfExists('toolbox_meetings');
        Schema::dropIfExists('hazard_reports');
        Schema::dropIfExists('safety_incidents');
    }
};
