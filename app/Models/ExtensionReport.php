<?php

namespace App\Models;

use App\Enums\PerformanceBand;
use App\Enums\ReportStatus;
use App\Models\Concerns\Auditable;
use App\Services\ExtensionPerformance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtensionReport extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id', 'location_id', 'report_date', 'status',
        'farmers_total', 'new_farmers', 'repeat_farmers', 'female_farmers', 'youth_farmers',
        'service_types', 'techniques', 'duration_minutes', 'notes',
        'declared_at', 'submitted_at',
        'woreda_verified_by', 'woreda_verified_at', 'zone_approved_by', 'zone_approved_at',
        'returned_by', 'returned_at', 'return_reason',
        'signature_data', 'verified_signature_data', 'approved_signature_data',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date:Y-m-d',
            'status' => ReportStatus::class,
            'service_types' => 'array',
            'techniques' => 'array',
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
        return $this->hasMany(ReportAction::class)->orderBy('created_at')->orderBy('id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'woreda_verified_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'zone_approved_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [ReportStatus::Draft, ReportStatus::Returned], true);
    }

    public function band(): PerformanceBand
    {
        return ExtensionPerformance::band($this->farmers_total);
    }

    /** Reports a given user is allowed to see. */
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

    public function scopeCounted(Builder $query): Builder
    {
        return $query->whereIn('status', ReportStatus::counted());
    }

    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereDate('report_date', '>=', $from)->whereDate('report_date', '<=', $to);
    }
}
