<?php

declare(strict_types=1);

namespace App\Modules\HotelOperations\Concerns;

use App\Modules\Audit\Concerns\Auditable;
use Illuminate\Support\Str;

/**
 * Standard activity-log defaults for hotel domain models.
 */
trait LogsHotelActivity
{
    use Auditable;

    protected function auditLogName(): string
    {
        return Str::snake(class_basename(static::class));
    }

    protected function auditDescription(string $eventName): string
    {
        $label = $this->activityLabel();

        return match ($eventName) {
            'created' => __(':label was created', ['label' => $label]),
            'updated' => __(':label was updated', ['label' => $label]),
            'deleted' => __(':label was deleted', ['label' => $label]),
            default => __(':label was :event', ['label' => $label, 'event' => $eventName]),
        };
    }

    protected function activityLabel(): string
    {
        if (isset($this->number) && is_string($this->number)) {
            return class_basename(static::class).' '.$this->number;
        }

        if (method_exists($this, 'fullName')) {
            return (string) $this->fullName();
        }

        if (isset($this->title) && is_string($this->title)) {
            return $this->title;
        }

        return class_basename(static::class).' #'.$this->getKey();
    }
}
