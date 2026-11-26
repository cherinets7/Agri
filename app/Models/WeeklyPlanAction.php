<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyPlanAction extends Model
{
    public $timestamps = false;

    protected $fillable = ['weekly_plan_report_id', 'user_id', 'action', 'from_status', 'to_status', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'create' => 'ረቂቅ ተፈጥሯል',
            'submit' => 'ለዞን ተልኳል',
            'approve' => 'በዞን ጸድቋል',
            'return' => 'ተመልሷል',
            default => $this->action,
        };
    }
}
