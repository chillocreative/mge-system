<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->string('document_subtype', 60)->nullable()->after('type');
            $table->boolean('reference_no_is_manual')->default(false)->after('reference_no');
        });

        Schema::create('correspondence_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_correspondence_id')->unique()->constrained('project_correspondences')->cascadeOnDelete();
            $table->string('category', 100)->nullable();
            $table->string('discipline', 100)->nullable();
            $table->string('document_reference')->nullable();
            $table->string('request_kind', 100)->nullable();
            $table->string('work_scope')->nullable();
            $table->string('work_category', 100)->nullable();
            $table->string('inspection_type', 100)->nullable();
            $table->date('inspection_date')->nullable();
            $table->string('location')->nullable();
            $table->string('criticality', 60)->nullable();
            $table->foreignId('subcontractor_party_id')->nullable()->constrained('project_parties')->nullOnDelete();
            $table->date('compliance_due_date')->nullable();
            $table->date('complied_date')->nullable();
            $table->text('action_required')->nullable();
            $table->string('memo_nature', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('correspondence_party_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_correspondence_id')->constrained('project_correspondences')->cascadeOnDelete();
            $table->string('party_role', 30); // jpriz, jps, client, subcontractor
            $table->foreignId('project_party_id')->nullable()->constrained('project_parties')->nullOnDelete();
            $table->string('status_raw', 100)->nullable();
            $table->string('status_normalized', 30)->nullable();
            $table->date('decision_date')->nullable();
            $table->date('closed_date')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->unique(['project_correspondence_id', 'party_role']);
            $table->index(['party_role', 'status_normalized']);
        });

        Schema::create('correspondence_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_correspondence_id')->constrained('project_correspondences')->cascadeOnDelete();
            $table->foreignId('target_correspondence_id')->constrained('project_correspondences')->cascadeOnDelete();
            $table->string('relation_type', 30); // response_to, resubmission_of, supersedes, closes, related
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_correspondence_id', 'target_correspondence_id', 'relation_type'], 'correspondence_links_unique');
        });

        Schema::create('correspondence_number_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('correspondence_type', 50);
            $table->string('document_subtype', 60)->default('');
            $table->string('pattern')->default('MGE/{project}/{type}/{yy}-{sequence}');
            $table->unsignedSmallInteger('padding')->default(3);
            $table->unsignedInteger('next_number')->default(1);
            $table->boolean('reset_annually')->default(true);
            $table->unsignedSmallInteger('sequence_year')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'correspondence_type', 'document_subtype'], 'correspondence_number_rules_scope_unique');
        });

        $now = now();
        foreach ([
            ['code' => 'ei', 'name' => 'EI', 'full_name' => 'Engineer Instruction', 'color' => 'orange', 'sort_order' => 13],
            ['code' => 'site_memo', 'name' => 'SM', 'full_name' => 'Site Memo', 'color' => 'amber', 'sort_order' => 14],
            ['code' => 'ptw', 'name' => 'PTW', 'full_name' => 'Permit to Work', 'color' => 'teal', 'sort_order' => 15],
            ['code' => 'incoming', 'name' => 'IN', 'full_name' => 'Incoming Correspondence', 'color' => 'blue', 'sort_order' => 16],
            ['code' => 'outgoing', 'name' => 'OUT', 'full_name' => 'Outgoing Correspondence', 'color' => 'indigo', 'sort_order' => 17],
            ['code' => 'report', 'name' => 'REPORT', 'full_name' => 'Report Submission', 'color' => 'green', 'sort_order' => 18],
            ['code' => 'drawing', 'name' => 'DWG', 'full_name' => 'Drawing Submission', 'color' => 'purple', 'sort_order' => 19],
            ['code' => 'subcon', 'name' => 'SUBCON', 'full_name' => 'Subcontractor Correspondence', 'color' => 'gray', 'sort_order' => 20],
        ] as $row) {
            DB::table('correspondence_types')->updateOrInsert(
                ['code' => $row['code']],
                $row + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        DB::table('correspondence_types')->whereIn('code', [
            'ei', 'site_memo', 'ptw', 'incoming', 'outgoing', 'report', 'drawing', 'subcon',
        ])->whereNotExists(function ($query) {
            $query->selectRaw('1')
                ->from('project_correspondences')
                ->whereColumn('project_correspondences.type', 'correspondence_types.code');
        })->delete();

        Schema::dropIfExists('correspondence_number_rules');
        Schema::dropIfExists('correspondence_links');
        Schema::dropIfExists('correspondence_party_reviews');
        Schema::dropIfExists('correspondence_details');

        Schema::table('project_correspondences', function (Blueprint $table) {
            $table->dropColumn(['document_subtype', 'reference_no_is_manual']);
        });
    }
};
