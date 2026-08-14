<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Modules\Company\Models\Company;
use Closure;

/**
 * Holds the workspace the current request (or job) is scoped to.
 *
 * Registered as a container singleton and read by {@see BelongsToCompany}'s
 * global scope. Nothing else in the application should reach for the session
 * directly to determine the active tenant.
 */
class CurrentCompany
{
    protected ?Company $company = null;

    /**
     * When true, tenant scoping is suspended for the duration of a callback.
     * Only cross-tenant administration should ever set this, and only via
     * {@see self::bypass()} so the flag is always restored.
     */
    protected bool $bypassed = false;

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function has(): bool
    {
        return $this->company instanceof Company;
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * Run a callback with tenant scoping suspended.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function bypass(Closure $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }

    /**
     * Run a callback scoped to a specific workspace, restoring the previous one.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function scopeTo(?Company $company, Closure $callback): mixed
    {
        $previous = $this->company;
        $this->company = $company;

        try {
            return $callback();
        } finally {
            $this->company = $previous;
        }
    }

    public function forget(): void
    {
        $this->company = null;
    }
}
