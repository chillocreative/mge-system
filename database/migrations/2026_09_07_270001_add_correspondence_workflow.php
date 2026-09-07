<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correspondence workflow (Batch 7 — Ciri 1/16/17/19).
 *
 * Purely additive: existing project_correspondences rows keep every value they
 * have. New capabilities are new columns (all nullable) and two new tables.
 *
 * - project_parties: the parties on a project (client, consultant, contractor,
 *   authority, …) modelled as data, not a fixed set of three columns, so a
 *   project can have as many as it needs.
 * - correspondence_events: an append-only history of what happened to a
 *   correspondence — raised, handed to a party, noted, closed. This is the
 *   record MGE wanted: who had it, when, and why it moved.
 * - the new columns on project_correspondences track where a correspondence is
 *   now (current_party_id) and the two dates a close has: the date it was
 *   agreed/expected to close and the date it actually closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('other'); // client, consultant, main_contractor, subcontractor, supplier, authority, other
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['project_id', 'type']);
        });

        Schema::create('correspondence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_correspondence_id')->constrained('project_correspondences')->cascadeOnDelete();
            $table->string('event_type'); // raised, handed_over, noted, status_changed, closed, reopened
            $table->text('note')->nullable();
            $table->foreignId('from_party_id')->nullable()->constrained('project_parties')->nullOnDelete();
            $table->foreignId('to_party_id')->nullable()->constrained('project_parties')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_correspondence_id', 'created_at']);
        });

        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->foreignId('current_party_id')->nullable()->after('status')->constrained('project_parties')->nullOnDelete();
            $table->date('expected_close_date')->nullable()->after('due_date');
            $table->date('actual_close_date')->nullable()->after('expected_close_date');
            $table->string('closing_reference')->nullable()->after('actual_close_date');
            $table->foreignId('closed_by')->nullable()->after('closing_reference')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_party_id');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['expected_close_date', 'actual_close_date', 'closing_reference']);
        });
        Schema::dropIfExists('correspondence_events');
        Schema::dropIfExists('project_parties');
    }
};
