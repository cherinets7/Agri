<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('feedback_token', 32)->nullable()->unique()->after('is_active');
        });

        Schema::create('farmer_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expert_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('extension_report_id')->nullable()->constrained('extension_reports')->nullOnDelete();
            $table->date('feedback_date');
            $table->unsignedTinyInteger('rating');
            $table->string('service_type', 40)->nullable();
            $table->string('farmer_name', 120)->nullable();
            $table->string('farmer_phone', 30)->nullable();
            $table->text('comment')->nullable();
            $table->mediumText('signature_data')->nullable();
            $table->string('channel', 20)->default('qr');
            $table->string('submitted_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['expert_id', 'feedback_date']);
            $table->index(['location_id', 'feedback_date']);
        });

        Schema::create('expert_weekly_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('week_start');
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('status', 20)->default('draft');

            $table->unsignedTinyInteger('days_reported')->default(0);
            $table->unsignedInteger('farmers_total')->default(0);
            $table->unsignedInteger('female_farmers')->default(0);
            $table->unsignedInteger('youth_farmers')->default(0);
            $table->decimal('avg_per_day', 6, 2)->default(0);
            $table->unsignedInteger('feedback_count')->default(0);
            $table->decimal('feedback_avg', 3, 2)->nullable();

            $table->text('highlights');
            $table->text('challenges')->nullable();
            $table->text('next_week_plan');

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

            $table->unique(['user_id', 'week_start']);
        });

        Schema::create('expert_weekly_summary_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expert_weekly_summary_id')->constrained('expert_weekly_summaries')->cascadeOnDelete();
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
        Schema::dropIfExists('expert_weekly_summary_actions');
        Schema::dropIfExists('expert_weekly_summaries');
        Schema::dropIfExists('farmer_feedbacks');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('feedback_token');
        });
    }
};
