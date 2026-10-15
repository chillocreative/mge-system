<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Approval received outside the system (email / WhatsApp / signed form), recorded by the filer.
            $table->boolean('approved_offline')->default(false);
            $table->string('offline_approver_name')->nullable();
            $table->string('offline_approval_note')->nullable();
            $table->string('approval_proof_path')->nullable();

            // Days beyond the balance at the time of filing.
            $table->decimal('shortfall_days', 5, 1)->default(0);

            // HR's recording step: approved leave is "recorded" once HR has settled the balance.
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();

            // Set when HR declared (part of) the request unpaid.
            $table->foreignId('original_leave_type_id')->nullable()->constrained('leave_types')->nullOnDelete();
            $table->foreignId('split_from_request_id')->nullable()->constrained('leave_requests')->nullOnDelete();

            $table->index(['status', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'recorded_at']);
            $table->dropConstrainedForeignId('split_from_request_id');
            $table->dropConstrainedForeignId('original_leave_type_id');
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn([
                'approved_offline', 'offline_approver_name', 'offline_approval_note', 'approval_proof_path',
                'shortfall_days', 'recorded_at',
            ]);
        });
    }
};
