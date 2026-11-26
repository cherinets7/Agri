<?php

namespace App\Models;

use App\Enums\PeriodGranularity;
use App\Enums\ReportStatus;
use App\Enums\SectorType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectorReport extends Model
{
    protected $fillable = [
        'sector', 'period_type', 'report_date', 'fiscal_year',
        'user_id', 'location_id', 'status', 'payload', 'notes',
        'declared_at', 'submitted_at',
        'woreda_verified_by', 'woreda_verified_at',
        'zone_approved_by', 'zone_approved_at',
        'returned_by', 'returned_at', 'return_reason',
    ];

    protected function casts(): array
    {
        return [
            'sector' => SectorType::class,
            'period_type' => PeriodGranularity::class,
            'report_date' => 'date:Y-m-d',
            'status' => ReportStatus::class,
            'payload' => 'array',
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

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'woreda_verified_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'zone_approved_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SectorReportAction::class)->orderBy('created_at')->orderBy('id');
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

    public function scopeCounted(Builder $query, bool $approvedOnly = false): Builder
    {
        return $query->whereIn('status', $approvedOnly ? [ReportStatus::ZoneApproved->value] : ReportStatus::counted());
    }

    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereDate('report_date', '>=', $from)->whereDate('report_date', '<=', $to);
    }
}
