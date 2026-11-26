<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanTarget extends Model
{
    use Auditable;

    protected $fillable = ['plan_activity_id', 'location_id', 'fiscal_year', 'target'];

    protected function casts(): array
    {
        return ['target' => 'float'];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(PlanActivity::class, 'plan_activity_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
