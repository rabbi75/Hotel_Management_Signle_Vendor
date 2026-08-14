<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Models;

use App\Modules\Workspace\Enums\WorkspaceMemberRole;
use App\Modules\Workspace\Enums\WorkspaceMemberStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property WorkspaceMemberRole $role
 * @property WorkspaceMemberStatus $status
 */
class WorkspaceMembership extends Pivot
{
    protected $table = 'workspace_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceMemberRole::class,
            'status' => WorkspaceMemberStatus::class,
            'joined_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
