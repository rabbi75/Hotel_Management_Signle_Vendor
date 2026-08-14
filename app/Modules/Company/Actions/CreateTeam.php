<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Company\DTOs\TeamData;
use App\Modules\Company\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTeam
{
    public function handle(TeamData $data): Team
    {
        return DB::transaction(function () use ($data): Team {
            $team = new Team($data->toAttributes());
            $team->slug = static::uniqueSlug($data->name);
            $team->save();

            if ($data->memberIds !== null) {
                $team->members()->sync($data->memberIds);
            }

            return $team;
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 1;

        while (static::slugTaken($slug, $ignoreId)) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }

    protected static function slugTaken(string $slug, ?int $ignoreId): bool
    {
        $query = Team::query()->withTrashed()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
