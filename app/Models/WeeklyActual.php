<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyActual extends Model
{
    protected $fillable = ['weekly_plan_report_id', 'plan_activity_id', 'value'];

    protected function casts(): array
    {
        return ['value' => 'float'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(WeeklyPlanReport::class, 'weekly_plan_report_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(PlanActivity::class, 'plan_activity_id');
    }
}
