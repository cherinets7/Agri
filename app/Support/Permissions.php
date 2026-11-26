<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Role capability lookup: config/permissions.php defaults overridden by rows in role_permissions.
 */
class Permissions
{
    /** @var array<string, bool>|null */
    private static ?array $overrides = null;

    public static function allows(User $user, string $capability): bool
    {
        return self::roleAllows($user->role, $capability);
    }

    public static function roleAllows(Role $role, string $capability): bool
    {
        $definition = (config('permissions.capabilities')[$capability] ?? null);
        if (! $definition) {
            return false;
        }

        $default = (bool) ($definition['defaults'][$role->value] ?? false);
        if ($definition['locked'] ?? false) {
            return $default;
        }

        return self::overrides()["{$role->value}:{$capability}"] ?? $default;
    }

    /** @return array<string, array{label:string, locked:bool}> */
    public static function definitions(): array
    {
        return collect(config('permissions.capabilities'))
            ->map(fn ($d) => ['label' => $d['label'], 'locked' => (bool) ($d['locked'] ?? false)])
            ->all();
    }

    public static function set(Role $role, string $capability, bool $allowed, ?User $by = null): void
    {
        $definition = (config('permissions.capabilities')[$capability] ?? null);
        if (! $definition || ($definition['locked'] ?? false)) {
            return;
        }

        $default = (bool) ($definition['defaults'][$role->value] ?? false);

        if ($allowed === $default) {
            RolePermission::query()->where(['role' => $role->value, 'permission' => $capability])->delete();
        } else {
            RolePermission::query()->updateOrCreate(
                ['role' => $role->value, 'permission' => $capability],
                ['allowed' => $allowed, 'updated_by' => $by?->id],
            );
        }

        self::flush();
    }

    public static function flush(): void
    {
        self::$overrides = null;
    }

    /** @return array<string, bool> */
    private static function overrides(): array
    {
        if (self::$overrides === null) {
            self::$overrides = Schema::hasTable('role_permissions')
                ? RolePermission::query()->get()->mapWithKeys(fn ($p) => ["{$p->role}:{$p->permission}" => (bool) $p->allowed])->all()
                : [];
        }

        return self::$overrides;
    }
}
