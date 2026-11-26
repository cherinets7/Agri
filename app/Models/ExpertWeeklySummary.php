<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpertWeeklySummary extends Model
{
    protected $fillable = [
        'user_id', 'location_id', 'week_start', 'fiscal_year', 'status',
        'days_reported', 'farmers_total', 'female_farmers', 'youth_farmers',
        'avg_per_day', 'feedback_count', 'feedback_avg',
        'highlights', 'challenges', 'next_week_plan',
        'declared_at', 'submitted_at',
        'woreda_verified_by', 'woreda_verified_at',
        'zone_approved_by', 'zone_approved_at',
        'returned_by', 'returned_at', 'return_reason',
        'signature_data', 'verified_signature_data', 'approved_signature_data',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date:Y-m-d',
            'status' => ReportStatus::class,
            'avg_per_day' => 'float',
            'feedback_avg' => 'float',
            'declared_at' => 'datetime',
            'submitted_at' => 'datetime',
            'woreda_verified_at' => 'datetime',
            'zone_approved_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ExpertWeeklySummaryAction::class)->orderBy('created_at')->orderBy('id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [ReportStatus::Draft, ReportStatus::Returned], true);
    }

    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isZone()) {
            return $query;
        }

        if ($viewer->isWoreda()) {
            return $query->whereIn('location_id', Location::query()->where('parent_id', $viewer->location_id)->select('id'));
        }

        return $query->where('user_id', $viewer->id);
    }
}
