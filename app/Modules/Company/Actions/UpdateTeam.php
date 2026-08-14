<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Company\DTOs\TeamData;
use App\Modules\Company\Models\Team;
use Illuminate\Support\Facades\DB;

class UpdateTeam
{
    public function handle(Team $team, TeamData $data): Team
    {
        return DB::transaction(function () use ($team, $data): Team {
            $attributes = $data->toUpdateAttributes();

            if ($data->wasProvided('name') && $team->name !== $data->name) {
                $attributes['slug'] = CreateTeam::uniqueSlug($data->name, $team->id);
            }

            $team->fill($attributes)->save();

            if ($data->memberIds !== null) {
                $team->members()->sync($data->memberIds);
            }

            return $team;
        });
    }
}
