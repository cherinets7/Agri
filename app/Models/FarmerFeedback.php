<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmerFeedback extends Model
{
    protected $table = 'farmer_feedbacks';

    protected $fillable = [
        'expert_id', 'location_id', 'extension_report_id',
        'feedback_date', 'rating', 'service_type',
        'farmer_name', 'farmer_phone', 'comment',
        'signature_data', 'channel', 'submitted_ip',
    ];

    protected function casts(): array
    {
        return [
            'feedback_date' => 'date:Y-m-d',
            'rating' => 'integer',
        ];
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expert_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function serviceLabel(): string
    {
        if (! $this->service_type) {
            return '—';
        }

        return config("extension.service_types.{$this->service_type}", $this->service_type);
    }

    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isZone()) {
            return $query;
        }

        if ($viewer->isWoreda()) {
            return $query->whereIn('location_id', Location::query()->where('parent_id', $viewer->location_id)->select('id'));
        }

        return $query->where('expert_id', $viewer->id);
    }

    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereDate('feedback_date', '>=', $from)->whereDate('feedback_date', '<=', $to);
    }
}
