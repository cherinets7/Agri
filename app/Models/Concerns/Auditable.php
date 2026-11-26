<?php

namespace App\Models\Concerns;

use App\Support\Audit;

/**
 * Writes create / update / delete rows to the audit log for the signed-in user.
 * Console and seeder writes (no signed-in user) are not logged.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            self::auditEvent($model, 'created', Audit::sanitize($model->getAttributes()));
        });

        static::updated(function ($model) {
            $changes = [];
            foreach ($model->getChanges() as $key => $new) {
                $changes[$key] = ['old' => $model->getOriginal($key), 'new' => $new];
            }
            $clean = [];
            foreach (Audit::sanitize(array_map(fn ($c) => $c['new'], $changes)) as $key => $new) {
                $clean[$key] = ['old' => Audit::sanitize([$key => $changes[$key]['old']])[$key] ?? null, 'new' => $new];
            }
            if ($clean) {
                self::auditEvent($model, 'updated', $clean);
            }
        });

        static::deleted(function ($model) {
            self::auditEvent($model, 'deleted', Audit::sanitize($model->getAttributes()));
        });
    }

    private static function auditEvent($model, string $event, array $changes): void
    {
        if (! auth()->check() || ! Audit::enabled()) {
            return;
        }

        Audit::record($event, $model, method_exists($model, 'auditSummary') ? $model->auditSummary() : null, $changes);
    }
}
