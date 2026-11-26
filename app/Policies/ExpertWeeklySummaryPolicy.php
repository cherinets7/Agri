<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Models\ExpertWeeklySummary;
use App\Models\User;

class ExpertWeeklySummaryPolicy
{
    public function view(User $user, ExpertWeeklySummary $summary): bool
    {
        if ($user->isZone()) {
            return true;
        }

        if ($user->isWoreda()) {
            return $summary->location?->parent_id === $user->location_id;
        }

        return $summary->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isKebele() && $user->location_id !== null;
    }

    public function update(User $user, ExpertWeeklySummary $summary): bool
    {
        return $user->isKebele() && $summary->user_id === $user->id && $summary->isEditable();
    }

    public function delete(User $user, ExpertWeeklySummary $summary): bool
    {
        return $user->isKebele() && $summary->user_id === $user->id && $summary->status === ReportStatus::Draft;
    }

    public function submit(User $user, ExpertWeeklySummary $summary): bool
    {
        return $this->update($user, $summary);
    }

    public function verify(User $user, ExpertWeeklySummary $summary): bool
    {
        return $user->isWoreda() && $this->view($user, $summary) && $summary->status === ReportStatus::Submitted;
    }

    public function approve(User $user, ExpertWeeklySummary $summary): bool
    {
        return $user->isZone() && $summary->status === ReportStatus::WoredaVerified;
    }

    public function return(User $user, ExpertWeeklySummary $summary): bool
    {
        return $this->verify($user, $summary) || $this->approve($user, $summary);
    }
}
