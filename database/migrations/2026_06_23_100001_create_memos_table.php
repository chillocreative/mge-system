<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->enum('audience', ['all_users', 'selected_users', 'project_members']);
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('memo_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memo_id')->constrained('memos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'memo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memo_recipients');
        Schema::dropIfExists('memos');
    }
};
