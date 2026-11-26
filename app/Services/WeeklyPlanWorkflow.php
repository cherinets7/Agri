<?php

namespace App\Services;

use App\Enums\WeeklyPlanStatus;
use App\Models\User;
use App\Models\WeeklyPlanAction;
use App\Models\WeeklyPlanReport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** woreda: ረቂቅ → ተልኳል · zone: አጽድቅ / መልስ (ምክንያት ግዴታ) · woreda fixes returned reports and resubmits. */
class WeeklyPlanWorkflow
{
    public function record(WeeklyPlanReport $report, User $actor, string $action, ?WeeklyPlanStatus $from, WeeklyPlanStatus $to, ?string $note = null): void
    {
        WeeklyPlanAction::create([
            'weekly_plan_report_id' => $report->id,
            'user_id' => $actor->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    public function submit(WeeklyPlanReport $report, User $actor): WeeklyPlanReport
    {
        if (! $report->actuals()->exists()) {
            throw new InvalidArgumentException('At least one actual value is required before submitting.');
        }

        return DB::transaction(function () use ($report, $actor) {
            $from = $report->status;
            $report->fill([
                'status' => WeeklyPlanStatus::Submitted,
                'declared_at' => now(),
                'submitted_at' => now(),
                'return_reason' => null,
                'returned_by' => null,
                'returned_at' => null,
            ])->save();
            $this->record($report, $actor, 'submit', $from, WeeklyPlanStatus::Submitted);

            return $report;
        });
    }

    public function approve(WeeklyPlanReport $report, User $actor, ?string $note = null): WeeklyPlanReport
    {
        return DB::transaction(function () use ($report, $actor, $note) {
            $from = $report->status;
            $report->fill([
                'status' => WeeklyPlanStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();
            $this->record($report, $actor, 'approve', $from, WeeklyPlanStatus::Approved, $note);

            return $report;
        });
    }

    public function return(WeeklyPlanReport $report, User $actor, string $reason): WeeklyPlanReport
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Return reason is required.');
        }

        return DB::transaction(function () use ($report, $actor, $reason) {
            $from = $report->status;
            $report->fill([
                'status' => WeeklyPlanStatus::Returned,
                'returned_by' => $actor->id,
                'returned_at' => now(),
                'return_reason' => $reason,
            ])->save();
            $this->record($report, $actor, 'return', $from, WeeklyPlanStatus::Returned, $reason);

            return $report;
        });
    }
}
