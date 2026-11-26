<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Optional kebele-level weekly actuals (plan.kebele_weekly_entry). They are NOT counted in the
 * matrix directly; the woreda prefills its own weekly report from the sum of its kebeles.
 */
class KebeleWeeklyActual extends Model
{
    use Auditable;

    protected $fillable = ['kebele_id', 'user_id', 'fiscal_year', 'week_start', 'plan_activity_id', 'value'];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'value' => 'float'];
    }

    public function kebele(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'kebele_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(PlanActivity::class, 'plan_activity_id');
    }
}
