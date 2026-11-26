<?php

namespace App\Policies;

use App\Enums\WeeklyPlanStatus;
use App\Models\User;
use App\Models\WeeklyPlanReport;

class WeeklyPlanReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isZone() || $user->isWoreda();
    }

    public function view(User $user, WeeklyPlanReport $report): bool
    {
        return $user->isZone() || ($user->isWoreda() && $report->woreda_id === $user->location_id);
    }

    public function create(User $user): bool
    {
        return $user->isWoreda() && $user->location_id !== null;
    }

    public function update(User $user, WeeklyPlanReport $report): bool
    {
        return $user->isWoreda() && $report->woreda_id === $user->location_id && $report->isEditable();
    }

    public function delete(User $user, WeeklyPlanReport $report): bool
    {
        return $user->isWoreda() && $report->woreda_id === $user->location_id && $report->status === WeeklyPlanStatus::Draft;
    }

    public function submit(User $user, WeeklyPlanReport $report): bool
    {
        return $this->update($user, $report);
    }

    public function approve(User $user, WeeklyPlanReport $report): bool
    {
        return $user->isZone() && $report->status === WeeklyPlanStatus::Submitted;
    }

    public function return(User $user, WeeklyPlanReport $report): bool
    {
        return $this->approve($user, $report);
    }
}
