<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id')->nullable(); // groups replies, first email's id
            $table->unsignedBigInteger('parent_id')->nullable();  // direct parent for reply chain
            $table->foreignId('from_user_id')->constrained('users');
            $table->string('subject');
            $table->longText('body');
            $table->boolean('is_draft')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('thread_id')->references('id')->on('internal_emails')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('internal_emails')->nullOnDelete();
            $table->index(['thread_id', 'created_at']);
            $table->index(['from_user_id', 'sent_at']);
        });

        Schema::create('email_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained('internal_emails')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', ['to', 'cc', 'bcc'])->default('to');
            $table->timestamp('read_at')->nullable();
            $table->boolean('starred')->default(false);
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'trashed_at']);
            $table->index(['email_id', 'user_id']);
        });

        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained('internal_emails')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedInteger('file_size')->default(0);
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
        Schema::dropIfExists('email_recipients');
        Schema::dropIfExists('internal_emails');
    }
};
