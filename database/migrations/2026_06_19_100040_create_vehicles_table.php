<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration_no')->unique();
            $table->string('make');
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->enum('type', ['car', 'van', 'truck', 'lorry', 'machinery', 'other'])->default('car');
            $table->date('purchase_date')->nullable();
            $table->decimal('current_value', 12, 2)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['active', 'inactive', 'disposed'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
