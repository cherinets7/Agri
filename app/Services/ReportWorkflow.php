<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\ExtensionReport;
use App\Models\ReportAction;
use App\Models\User;
use App\Notifications\ReportStatusNotification;
use App\Support\EthiopianDate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReportWorkflow
{
    public function record(ExtensionReport $report, User $actor, string $action, ?ReportStatus $from, ReportStatus $to, ?string $note = null, ?string $signatureData = null): void
    {
        ReportAction::create([
            'extension_report_id' => $report->id,
            'user_id' => $actor->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'signature_data' => $signatureData,
            'created_at' => now(),
        ]);
    }

    public function submit(ExtensionReport $report, User $actor, ?string $signatureData = null): ExtensionReport
    {
        return DB::transaction(function () use ($report, $actor, $signatureData) {
            $from = $report->status;
            $sig = $signatureData ?: $report->signature_data;
            $report->fill([
                'status' => ReportStatus::Submitted,
                'declared_at' => now(),
                'submitted_at' => now(),
                'signature_data' => $sig,
                'return_reason' => null,
                'returned_by' => null,
                'returned_at' => null,
            ])->save();
            $this->record($report, $actor, 'submit', $from, ReportStatus::Submitted, null, $sig);

            return $report;
        });
    }

    public function verify(ExtensionReport $report, User $actor, ?string $note = null, ?string $signatureData = null): ExtensionReport
    {
        return DB::transaction(function () use ($report, $actor, $note, $signatureData) {
            $from = $report->status;
            $report->fill([
                'status' => ReportStatus::WoredaVerified,
                'woreda_verified_by' => $actor->id,
                'woreda_verified_at' => now(),
                'verified_signature_data' => $signatureData,
            ])->save();
            $this->record($report, $actor, 'verify', $from, ReportStatus::WoredaVerified, $note, $signatureData);

            return $report;
        });
    }

    public function approve(ExtensionReport $report, User $actor, ?string $note = null, ?string $signatureData = null): ExtensionReport
    {
        return DB::transaction(function () use ($report, $actor, $note, $signatureData) {
            $from = $report->status;
            $report->fill([
                'status' => ReportStatus::ZoneApproved,
                'zone_approved_by' => $actor->id,
                'zone_approved_at' => now(),
                'approved_signature_data' => $signatureData,
            ])->save();
            $this->record($report, $actor, 'approve', $from, ReportStatus::ZoneApproved, $note, $signatureData);

            if ($report->user && $report->user_id !== $actor->id) {
                $et = EthiopianDate::format($report->report_date);
                $report->user->notify(new ReportStatusNotification(
                    title: "ዕለታዊ ሪፖርት ጸድቋል ({$et})",
                    message: "የ{$et} ዕለታዊ ሪፖርትዎ በ{$actor->name} ጸድቋል።",
                    url: route('reports.show', $report),
                    kind: 'approved',
                ));
            }

            return $report;
        });
    }

    public function return(ExtensionReport $report, User $actor, string $reason): ExtensionReport
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Return reason is required.');
        }

        return DB::transaction(function () use ($report, $actor, $reason) {
            $from = $report->status;
            $report->fill([
                'status' => ReportStatus::Returned,
                'returned_by' => $actor->id,
                'returned_at' => now(),
                'return_reason' => $reason,
                'woreda_verified_by' => null,
                'woreda_verified_at' => null,
            ])->save();
            $this->record($report, $actor, 'return', $from, ReportStatus::Returned, $reason);

            if ($report->user && $report->user_id !== $actor->id) {
                $et = EthiopianDate::format($report->report_date);
                $report->user->notify(new ReportStatusNotification(
                    title: "ዕለታዊ ሪፖርት ተመልሷል ({$et})",
                    message: "ምክንያት፦ {$reason}",
                    url: route('reports.edit', $report),
                    kind: 'returned',
                ));
            }

            return $report;
        });
    }
}
