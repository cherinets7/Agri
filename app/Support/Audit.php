<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Audit
{
    private static ?bool $tableExists = null;

    public static function enabled(): bool
    {
        if (! config('audit.enabled')) {
            return false;
        }

        return self::$tableExists ??= Schema::hasTable('audit_logs');
    }

    public static function reset(): void
    {
        self::$tableExists = null;
    }

    public static function record(string $event, ?Model $subject = null, ?string $summary = null, ?array $changes = null, ?User $user = null): ?AuditLog
    {
        if (! self::enabled()) {
            return null;
        }

        $user ??= Auth::user();

        return AuditLog::create([
            'user_id' => $user?->getKey(),
            'event' => $event,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'summary' => $summary !== null ? mb_substr($summary, 0, 250) : null,
            'changes' => $changes ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $values */
    public static function sanitize(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if (in_array($key, config('audit.ignore_attributes'), true)) {
                continue;
            }
            if (in_array($key, config('audit.redact_attributes'), true)) {
                $out[$key] = '[…]';

                continue;
            }
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $out[$key] = is_string($value) ? mb_substr($value, 0, 200) : $value;
        }

        return $out;
    }
}
