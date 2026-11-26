<?php

namespace App\Models;

use App\Enums\WeeklyPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyPlanReport extends Model
{
    protected $fillable = [
        'woreda_id', 'user_id', 'fiscal_year', 'week_start', 'status', 'notes',
        'declared_at', 'submitted_at', 'approved_by', 'approved_at',
        'returned_by', 'returned_at', 'return_reason',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date:Y-m-d',
            'status' => WeeklyPlanStatus::class,
            'declared_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function woreda(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'woreda_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function actuals(): HasMany
    {
        return $this->hasMany(WeeklyActual::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WeeklyPlanAction::class)->orderBy('created_at')->orderBy('id');
    }

    public function weekEnd()
    {
        return $this->week_start->copy()->addDays(6);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [WeeklyPlanStatus::Draft, WeeklyPlanStatus::Returned], true);
    }

    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isZone()) {
            return $query;
        }

        if ($viewer->isWoreda()) {
            return $query->where('woreda_id', $viewer->location_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeCounted(Builder $query, bool $approvedOnly = false): Builder
    {
        return $query->whereIn('status', $approvedOnly ? [WeeklyPlanStatus::Approved->value] : WeeklyPlanStatus::counted());
    }
}
