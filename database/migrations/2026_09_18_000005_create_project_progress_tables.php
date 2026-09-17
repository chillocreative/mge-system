<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_schedule_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->decimal('scheduled_physical_pct', 5, 2)->default(0);
            $table->decimal('scheduled_financial_amount', 15, 2)->nullable();
            $table->decimal('scheduled_financial_pct', 5, 2)->nullable();
            $table->string('source', 20)->default('manual');
            $table->timestamps();
            $table->unique(['project_id', 'month']);
        });

        Schema::create('project_progress_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_no');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('planning_days_completion')->nullable();
            $table->decimal('physical_scheduled_pct', 5, 2)->default(0);
            $table->decimal('physical_actual_pct', 5, 2)->default(0);
            $table->decimal('financial_scheduled_pct', 5, 2)->default(0);
            $table->decimal('financial_actual_pct', 5, 2)->default(0);
            $table->decimal('financial_actual_amount', 15, 2)->nullable();
            $table->integer('ahead_delay_days')->nullable();
            $table->string('physical_status', 20)->nullable();
            $table->string('financial_status', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'period_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_progress_periods');
        Schema::dropIfExists('project_schedule_baselines');
    }
};
