<?php

namespace App\Models;

use App\Enums\PeriodicReportType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InputDispatch extends Model
{
    use Auditable;

    protected $fillable = [
        'from_location_id', 'to_location_id', 'user_id',
        'dispatch_date', 'fiscal_year', 'input_type', 'unit', 'quantity', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'dispatch_date' => 'date:Y-m-d',
            'quantity' => 'float',
        ];
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inputTypeLabel(): string
    {
        return PeriodicReportType::inputTypes()[$this->input_type] ?? $this->input_type;
    }

    public function unitLabel(): string
    {
        return match ($this->unit) {
            'quintal' => 'ኩ/ል',
            'liter' => 'ሊትር',
            'count' => 'ቁጥር',
            default => $this->unit,
        };
    }

    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isZone()) {
            return $query;
        }

        if ($viewer->isWoreda()) {
            $kebeleIds = Location::query()->where('parent_id', $viewer->location_id)->select('id');

            return $query->where(fn (Builder $q) => $q
                ->where('to_location_id', $viewer->location_id)
                ->orWhere(fn (Builder $q2) => $q2
                    ->where('from_location_id', $viewer->location_id)
                    ->whereIn('to_location_id', $kebeleIds)));
        }

        return $query->where('to_location_id', $viewer->location_id);
    }
}
