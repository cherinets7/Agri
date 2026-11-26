<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use Auditable;

    protected $fillable = ['role', 'permission', 'allowed', 'updated_by'];

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }
}
