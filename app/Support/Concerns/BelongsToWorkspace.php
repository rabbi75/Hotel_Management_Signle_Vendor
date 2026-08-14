<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Modules\Workspace\Models\Workspace;
use App\Support\Tenancy\CurrentWorkspace;
use App\Support\Tenancy\WorkspaceScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by an operational workspace inside a tenant.
 *
 * @property int|null $workspace_id
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function (self $model): void {
            if ($model->getAttribute('workspace_id') === null) {
                $model->workspace_id = app(CurrentWorkspace::class)->id();
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
