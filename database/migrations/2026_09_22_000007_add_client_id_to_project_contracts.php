<?php

use App\Models\Project;
use App\Models\ProjectContract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_contracts', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('project_id')->constrained('clients')->nullOnDelete();
        });

        // Backfill: any existing contract without a client_id inherits its
        // project's client_id. Done via the query builder (not a raw JOIN
        // UPDATE) so it works identically on MySQL and SQLite (used in tests).
        ProjectContract::whereNull('client_id')->select('id', 'project_id')->chunkById(200, function ($contracts) {
            $projectIds = $contracts->pluck('project_id')->filter()->unique()->all();
            if (empty($projectIds)) {
                return;
            }

            $clientIdsByProject = Project::whereIn('id', $projectIds)->pluck('client_id', 'id');

            foreach ($contracts as $contract) {
                $clientId = $clientIdsByProject[$contract->project_id] ?? null;
                if ($clientId) {
                    ProjectContract::where('id', $contract->id)->update(['client_id' => $clientId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
