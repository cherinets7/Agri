<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        foreach (['extension_reports', 'periodic_reports', 'sector_reports', 'expert_weekly_summaries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->mediumText('signature_data')->nullable();
                $table->mediumText('verified_signature_data')->nullable();
                $table->mediumText('approved_signature_data')->nullable();
            });
        }

        Schema::table('weekly_plan_reports', function (Blueprint $table) {
            $table->mediumText('signature_data')->nullable();
            $table->mediumText('approved_signature_data')->nullable();
        });

        foreach ([
            'report_actions',
            'periodic_report_actions',
            'sector_report_actions',
            'weekly_plan_actions',
            'expert_weekly_summary_actions',
        ] as $actionTable) {
            Schema::table($actionTable, function (Blueprint $table) {
                $table->mediumText('signature_data')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'report_actions',
            'periodic_report_actions',
            'sector_report_actions',
            'weekly_plan_actions',
            'expert_weekly_summary_actions',
        ] as $actionTable) {
            Schema::table($actionTable, function (Blueprint $table) {
                $table->dropColumn('signature_data');
            });
        }

        Schema::table('weekly_plan_reports', function (Blueprint $table) {
            $table->dropColumn(['signature_data', 'approved_signature_data']);
        });

        foreach (['extension_reports', 'periodic_reports', 'sector_reports', 'expert_weekly_summaries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['signature_data', 'verified_signature_data', 'approved_signature_data']);
            });
        }

        Schema::dropIfExists('notifications');
    }
};
