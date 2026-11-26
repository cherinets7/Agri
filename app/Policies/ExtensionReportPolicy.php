<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Models\ExtensionReport;
use App\Models\User;

class ExtensionReportPolicy
{
    public function view(User $user, ExtensionReport $report): bool
    {
        if ($user->isZone()) {
            return true;
        }

        if ($user->isWoreda()) {
            return $report->location?->parent_id === $user->location_id;
        }

        return $report->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isKebele() && $user->location_id !== null;
    }

    public function update(User $user, ExtensionReport $report): bool
    {
        return $user->isKebele() && $report->user_id === $user->id && $report->isEditable();
    }

    public function delete(User $user, ExtensionReport $report): bool
    {
        return $user->isKebele() && $report->user_id === $user->id && $report->status === ReportStatus::Draft;
    }

    public function submit(User $user, ExtensionReport $report): bool
    {
        return $this->update($user, $report);
    }

    public function verify(User $user, ExtensionReport $report): bool
    {
        return $user->isWoreda() && $this->view($user, $report) && $report->status === ReportStatus::Submitted;
    }

    public function approve(User $user, ExtensionReport $report): bool
    {
        return $user->isZone() && $report->status === ReportStatus::WoredaVerified;
    }

    public function return(User $user, ExtensionReport $report): bool
    {
        return $this->verify($user, $report) || $this->approve($user, $report);
    }
}
