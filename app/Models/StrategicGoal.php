<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategicGoal extends Model
{
    protected $fillable = ['number', 'name_am', 'name_en', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PlanActivity::class)->orderBy('sort_order')->orderBy('id');
    }
}
