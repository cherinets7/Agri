<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('report_date');
            $table->string('status', 20)->default('draft');

            $table->unsignedInteger('farmers_total')->default(0);
            $table->unsignedInteger('new_farmers')->default(0);
            $table->unsignedInteger('repeat_farmers')->default(0);
            $table->unsignedInteger('female_farmers')->default(0);
            $table->unsignedInteger('youth_farmers')->default(0);
            $table->json('service_types')->nullable();
            $table->json('techniques')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
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

            $table->unique(['user_id', 'report_date']);
            $table->index(['location_id', 'report_date']);
            $table->index(['status', 'report_date']);
        });

        Schema::create('report_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extension_report_id')->constrained('extension_reports')->cascadeOnDelete();
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
        Schema::dropIfExists('report_actions');
        Schema::dropIfExists('extension_reports');
    }
};
