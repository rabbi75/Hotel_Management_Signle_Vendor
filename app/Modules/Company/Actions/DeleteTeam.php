<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Company\Models\Team;
use Illuminate\Support\Facades\DB;

class DeleteTeam
{
    public function handle(Team $team): void
    {
        DB::transaction(function () use ($team): void {
            $team->members()->detach();
            $team->delete();
        });
    }
}
