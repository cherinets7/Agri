<?php

namespace App\Models;

use App\Enums\Level;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'location_id',
        'phone',
        'is_active',
        'feedback_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ExtensionReport::class);
    }

    public function isKebele(): bool
    {
        return $this->role === Role::Kebele;
    }

    public function isWoreda(): bool
    {
        return $this->role === Role::Woreda;
    }

    public function isZone(): bool
    {
        return $this->role === Role::Zone;
    }

    /** Woreda that this user belongs to (null for zone users). */
    public function woredaId(): ?int
    {
        if ($this->isWoreda()) {
            return $this->location_id;
        }

        return $this->isKebele() ? $this->location?->parent_id : null;
    }

    /** Restrict a user query to the people this user may see. */
    public function scopeVisibleTo(Builder $query, self $viewer): Builder
    {
        return match ($viewer->role) {
            Role::Zone => $query,
            Role::Woreda => $query->whereIn('location_id', $viewer->location->children()->select('id'))
                ->where('role', Role::Kebele->value),
            Role::Kebele => $query->whereKey($viewer->id),
        };
    }

    /** Locations this user can pick when creating users or locations. */
    public function manageableLocations(Level $level)
    {
        $query = Location::query()->ofLevel($level)->orderBy('name_am');

        if ($this->isWoreda()) {
            return $level === Level::Kebele ? $query->where('parent_id', $this->location_id) : $query->whereRaw('1 = 0');
        }

        return $this->isZone() ? $query : $query->whereRaw('1 = 0');
    }

    public function ensureFeedbackToken(): string
    {
        if (! $this->feedback_token) {
            $this->forceFill(['feedback_token' => Str::random(24)])->save();
        }

        return $this->feedback_token;
    }
}
