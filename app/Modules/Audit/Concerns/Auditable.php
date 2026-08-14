<?php

declare(strict_types=1);

namespace App\Modules\Audit\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Opt a model into the activity log with this application's defaults.
 *
 * A model that uses this trait records create/update/delete with only the
 * attributes that actually changed, never records credentials, and stamps the
 * workspace the change happened in so the audit screen can be tenant scoped.
 *
 * @mixin Model
 */
trait Auditable
{
    use LogsActivity;

    /**
     * Attributes that must never reach the activity log for any model.
     *
     * @var list<string>
     */
    protected static array $auditNeverLog = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept($this->auditExcept())
            ->logOnlyDirty()
            // A touch that only moved the timestamps is not a business event.
            ->dontLogIfAttributesChangedOnly(['created_at', 'updated_at', 'deleted_at'])
            ->dontSubmitEmptyLogs()
            ->useLogName($this->auditLogName())
            ->setDescriptionForEvent(fn (string $eventName): string => $this->auditDescription($eventName));
    }

    /**
     * Attach the tenant to the activity so audit screens can scope by workspace
     * without joining through every possible subject table.
     */
    public function tapActivity(ActivityContract $activity, string $eventName): void
    {
        /** @var Collection<string, mixed> $properties */
        $properties = $activity->properties ?? collect();

        // Read straight from the attribute bag: not every auditable model has a
        // company_id column, and strict mode turns a missing one into an error.
        $companyId = $this->attributes['company_id'] ?? current_company_id();

        $activity->properties = $properties->merge([
            'company_id' => $companyId,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return array_values(array_unique(array_merge(
            static::$auditNeverLog,
            $this->auditIgnore(),
        )));
    }

    /**
     * Per-model additions to the never-log list.
     *
     * @return list<string>
     */
    protected function auditIgnore(): array
    {
        return [];
    }

    protected function auditLogName(): string
    {
        return (string) config('activitylog.default_log_name', 'default');
    }

    protected function auditDescription(string $eventName): string
    {
        return sprintf('%s was %s', class_basename($this), $eventName);
    }
}
