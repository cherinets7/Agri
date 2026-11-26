<?php

namespace App\Models;

use App\Enums\Level;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = ['parent_id', 'level', 'name_am', 'name_en', 'code'];

    protected function casts(): array
    {
        return ['level' => Level::class];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function woreda(): ?self
    {
        return match ($this->level) {
            Level::Kebele => $this->parent,
            Level::Woreda => $this,
            default => null,
        };
    }

    public function zone(): ?self
    {
        return match ($this->level) {
            Level::Kebele => $this->parent?->parent,
            Level::Woreda => $this->parent,
            Level::Zone => $this,
        };
    }

    /** "ቀበሌ ... / ወረዳ ... / ዞን ..." */
    public function fullName(): string
    {
        $parts = [$this->name_am];
        $node = $this->parent;
        while ($node) {
            $parts[] = $node->name_am;
            $node = $node->parent;
        }

        return implode(' ‹ ', $parts);
    }

    public function scopeOfLevel($query, Level $level)
    {
        return $query->where('level', $level->value);
    }
}
