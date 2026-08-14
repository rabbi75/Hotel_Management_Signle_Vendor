<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Modules\Workspace\Models\Workspace;
use Closure;

/**
 * Holds the operational workspace the current request is scoped to.
 *
 * Distinct from {@see CurrentCompany}: the company is the tenant (billing),
 * the workspace is the operational boundary for hotel data.
 */
class CurrentWorkspace
{
    protected ?Workspace $workspace = null;

    protected bool $bypassed = false;

    public function get(): ?Workspace
    {
        return $this->workspace;
    }

    public function id(): ?int
    {
        return $this->workspace?->id;
    }

    public function set(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function has(): bool
    {
        return $this->workspace instanceof Workspace;
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
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
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function scopeTo(?Workspace $workspace, Closure $callback): mixed
    {
        $previous = $this->workspace;
        $this->workspace = $workspace;

        try {
            return $callback();
        } finally {
            $this->workspace = $previous;
        }
    }

    public function forget(): void
    {
        $this->workspace = null;
    }
}
