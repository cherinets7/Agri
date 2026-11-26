<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_reports', function (Blueprint $table) {
            $table->id();
            $table->string('sector', 40);
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

            $table->index(['sector', 'location_id', 'report_date']);
            $table->index(['sector', 'status', 'report_date']);
        });

        Schema::create('sector_report_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_report_id')->constrained('sector_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_report_actions');
        Schema::dropIfExists('sector_reports');
    }
};
