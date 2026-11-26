<?php

namespace App\Services;

use App\Enums\WeeklyPlanStatus;
use App\Models\User;
use App\Models\WeeklyPlanAction;
use App\Models\WeeklyPlanReport;
use App\Notifications\ReportStatusNotification;
use App\Support\EthiopianDate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** woreda: ረቂቅ → ተልኳል · zone: አጽድቅ / መልስ (ምክንያት ግዴታ) · woreda fixes returned reports and resubmits. */
class WeeklyPlanWorkflow
{
    public function record(WeeklyPlanReport $report, User $actor, string $action, ?WeeklyPlanStatus $from, WeeklyPlanStatus $to, ?string $note = null, ?string $signatureData = null): void
    {
        WeeklyPlanAction::create([
            'weekly_plan_report_id' => $report->id,
            'user_id' => $actor->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'signature_data' => $signatureData,
            'created_at' => now(),
        ]);
    }

    public function submit(WeeklyPlanReport $report, User $actor, ?string $signatureData = null): WeeklyPlanReport
    {
        if (! $report->actuals()->exists()) {
            throw new InvalidArgumentException('At least one actual value is required before submitting.');
        }

        return DB::transaction(function () use ($report, $actor, $signatureData) {
            $from = $report->status;
            $sig = $signatureData ?: $report->signature_data;
            $report->fill([
                'status' => WeeklyPlanStatus::Submitted,
                'declared_at' => now(),
                'submitted_at' => now(),
                'signature_data' => $sig,
                'return_reason' => null,
                'returned_by' => null,
                'returned_at' => null,
            ])->save();
            $this->record($report, $actor, 'submit', $from, WeeklyPlanStatus::Submitted, null, $sig);

            return $report;
        });
    }

    public function approve(WeeklyPlanReport $report, User $actor, ?string $note = null, ?string $signatureData = null): WeeklyPlanReport
    {
        return DB::transaction(function () use ($report, $actor, $note, $signatureData) {
            $from = $report->status;
            $report->fill([
                'status' => WeeklyPlanStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approved_signature_data' => $signatureData,
            ])->save();
            $this->record($report, $actor, 'approve', $from, WeeklyPlanStatus::Approved, $note, $signatureData);

            if ($report->user && $report->user_id !== $actor->id) {
                $et = EthiopianDate::format($report->week_start);
                $report->user->notify(new ReportStatusNotification(
                    title: "ሳምንታዊ ክንውን ጸድቋል ({$et})",
                    message: "የ{$report->woreda?->name_am} ሳምንታዊ ክንውን በ{$actor->name} ጸድቋል።",
                    url: route('weekly.show', $report),
                    kind: 'approved',
                ));
            }

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

            if ($report->user && $report->user_id !== $actor->id) {
                $et = EthiopianDate::format($report->week_start);
                $report->user->notify(new ReportStatusNotification(
                    title: "ሳምንታዊ ክንውን ተመልሷል ({$et})",
                    message: "ምክንያት፦ {$reason}",
                    url: route('weekly.edit', $report),
                    kind: 'returned',
                ));
            }

            return $report;
        });
    }
}
