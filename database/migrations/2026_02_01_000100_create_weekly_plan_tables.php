<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategic_goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number');
            $table->string('name_am');
            $table->string('name_en')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategic_goal_id')->constrained('strategic_goals')->restrictOnDelete();
            $table->string('name_am');
            $table->string('name_en')->nullable();
            $table->string('unit', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_activity_id')->constrained('plan_activities')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('target', 16, 3);
            $table->timestamps();

            $table->unique(['plan_activity_id', 'location_id', 'fiscal_year']);
            $table->index(['fiscal_year', 'location_id']);
        });

        Schema::create('weekly_plan_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('woreda_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->date('week_start');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();

            $table->timestamp('declared_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->timestamps();

            $table->unique(['woreda_id', 'week_start']);
            $table->index(['fiscal_year', 'status']);
        });

        Schema::create('weekly_actuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_plan_report_id')->constrained('weekly_plan_reports')->cascadeOnDelete();
            $table->foreignId('plan_activity_id')->constrained('plan_activities')->restrictOnDelete();
            $table->decimal('value', 16, 3);
            $table->timestamps();

            $table->unique(['weekly_plan_report_id', 'plan_activity_id']);
        });

        Schema::create('weekly_plan_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_plan_report_id')->constrained('weekly_plan_reports')->cascadeOnDelete();
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
        Schema::dropIfExists('weekly_plan_actions');
        Schema::dropIfExists('weekly_actuals');
        Schema::dropIfExists('weekly_plan_reports');
        Schema::dropIfExists('plan_targets');
        Schema::dropIfExists('plan_activities');
        Schema::dropIfExists('strategic_goals');
    }
};
