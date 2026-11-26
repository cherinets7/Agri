<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\ExpertWeeklySummary;
use App\Models\ExpertWeeklySummaryAction;
use App\Models\ExtensionReport;
use App\Models\FarmerFeedback;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpertWeeklySummaryWorkflow
{
    /** Compute weekly extension + feedback stats for an expert's Mon-Sun week. */
    public function computeMetrics(User $expert, CarbonInterface $weekStart): array
    {
        $weekEnd = $weekStart->copy()->addDays(6);

        $ext = ExtensionReport::query()
            ->where('user_id', $expert->id)
            ->counted()
            ->between($weekStart, $weekEnd)
            ->selectRaw('COUNT(*) as days_reported, COALESCE(SUM(farmers_total),0) as farmers_total, COALESCE(SUM(female_farmers),0) as female_farmers, COALESCE(SUM(youth_farmers),0) as youth_farmers')
            ->first();

        $days = (int) ($ext->days_reported ?? 0);
        $farmers = (int) ($ext->farmers_total ?? 0);

        $fb = FarmerFeedback::query()
            ->where('expert_id', $expert->id)
            ->between($weekStart, $weekEnd)
            ->selectRaw('COUNT(*) as cnt, AVG(rating) as avg_rating')
            ->first();

        return [
            'days_reported' => $days,
            'farmers_total' => $farmers,
            'female_farmers' => (int) ($ext->female_farmers ?? 0),
            'youth_farmers' => (int) ($ext->youth_farmers ?? 0),
            'avg_per_day' => $days > 0 ? round($farmers / $days, 2) : 0.0,
            'feedback_count' => (int) ($fb->cnt ?? 0),
            'feedback_avg' => $fb->avg_rating !== null ? round((float) $fb->avg_rating, 2) : null,
        ];
    }

    public function record(ExpertWeeklySummary $summary, User $actor, string $action, ?ReportStatus $from, ReportStatus $to, ?string $note = null): void
    {
        ExpertWeeklySummaryAction::create([
            'expert_weekly_summary_id' => $summary->id,
            'user_id' => $actor->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    public function submit(ExpertWeeklySummary $summary, User $actor): ExpertWeeklySummary
    {
        return DB::transaction(function () use ($summary, $actor) {
            $from = $summary->status;
            $summary->fill([
                'status' => ReportStatus::Submitted,
                'declared_at' => now(),
                'submitted_at' => now(),
                'return_reason' => null,
                'returned_by' => null,
                'returned_at' => null,
            ])->save();
            $this->record($summary, $actor, 'submit', $from, ReportStatus::Submitted);

            return $summary;
        });
    }

    public function verify(ExpertWeeklySummary $summary, User $actor, ?string $note = null): ExpertWeeklySummary
    {
        return DB::transaction(function () use ($summary, $actor, $note) {
            $from = $summary->status;
            $summary->fill([
                'status' => ReportStatus::WoredaVerified,
                'woreda_verified_by' => $actor->id,
                'woreda_verified_at' => now(),
            ])->save();
            $this->record($summary, $actor, 'verify', $from, ReportStatus::WoredaVerified, $note);

            return $summary;
        });
    }

    public function approve(ExpertWeeklySummary $summary, User $actor, ?string $note = null): ExpertWeeklySummary
    {
        return DB::transaction(function () use ($summary, $actor, $note) {
            $from = $summary->status;
            $summary->fill([
                'status' => ReportStatus::ZoneApproved,
                'zone_approved_by' => $actor->id,
                'zone_approved_at' => now(),
            ])->save();
            $this->record($summary, $actor, 'approve', $from, ReportStatus::ZoneApproved, $note);

            return $summary;
        });
    }

    public function return(ExpertWeeklySummary $summary, User $actor, string $reason): ExpertWeeklySummary
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Return reason is required.');
        }

        return DB::transaction(function () use ($summary, $actor, $reason) {
            $from = $summary->status;
            $summary->fill([
                'status' => ReportStatus::Returned,
                'returned_by' => $actor->id,
                'returned_at' => now(),
                'return_reason' => $reason,
                'woreda_verified_by' => null,
                'woreda_verified_at' => null,
            ])->save();
            $this->record($summary, $actor, 'return', $from, ReportStatus::Returned, $reason);

            return $summary;
        });
    }
}
