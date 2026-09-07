<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_event_id')->constrained('company_events')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_event_id', 'employee_id'], 'company_event_attendee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_event_attendees');
    }
};
