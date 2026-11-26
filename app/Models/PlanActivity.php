<?php

namespace App\Models;

use App\Enums\PlanUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanActivity extends Model
{
    protected $fillable = ['strategic_goal_id', 'name_am', 'name_en', 'unit', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['unit' => PlanUnit::class, 'is_active' => 'boolean'];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(StrategicGoal::class, 'strategic_goal_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PlanTarget::class);
    }

    public function actuals(): HasMany
    {
        return $this->hasMany(WeeklyActual::class);
    }
}
