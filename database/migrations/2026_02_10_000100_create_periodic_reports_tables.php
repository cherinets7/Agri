<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodic_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 40);
            $table->string('period_type', 20)->default('weekly');
            $table->date('report_date');
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->json('payload');
            $table->text('notes')->nullable();

            $table->timestamp('declared_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('woreda_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('woreda_verified_at')->nullable();
            $table->foreignId('zone_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('zone_approved_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->timestamps();

            $table->index(['report_type', 'location_id', 'report_date']);
            $table->index(['report_type', 'status', 'report_date']);
        });

        Schema::create('periodic_report_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodic_report_id')->constrained('periodic_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('input_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('dispatch_date');
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('input_type', 40);
            $table->string('unit', 20);
            $table->decimal('quantity', 16, 3);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['from_location_id', 'to_location_id', 'dispatch_date'], 'idx_dispatch_loc_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('input_dispatches');
        Schema::dropIfExists('periodic_report_actions');
        Schema::dropIfExists('periodic_reports');
    }
};
