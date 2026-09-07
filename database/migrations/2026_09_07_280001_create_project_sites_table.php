<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-site per project (Ciri 25 foundation).
 *
 * A project can run across several physical sites/zones. Until now every module
 * captured only a free-text `location`; this gives sites first-class identity
 * so records can be grouped and filtered by the actual site.
 *
 * The rollout is deliberately additive: this table is new, and the site_id
 * columns added to operational tables are all nullable. Existing rows keep
 * their free-text location untouched — a site is an optional structured
 * refinement, not a replacement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['project_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_sites');
    }
};
