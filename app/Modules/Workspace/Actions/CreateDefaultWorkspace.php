<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Actions;

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Enums\WorkspaceMemberRole;
use App\Modules\Workspace\Enums\WorkspaceMemberStatus;
use App\Modules\Workspace\Enums\WorkspaceStatus;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDefaultWorkspace
{
    public function handle(Company $company, ?User $owner = null): Workspace
    {
        return DB::transaction(function () use ($company, $owner): Workspace {
            $existing = Workspace::query()
                ->withoutCompanyScope()
                ->where('company_id', $company->id)
                ->where('is_default', true)
                ->first();

            if ($existing instanceof Workspace) {
                $this->ensureMember($existing, $owner);

                return $existing;
            }

            // Reuse an existing workspace as the default when the tenant already
            // has operational workspaces but none flagged as default.
            $promote = Workspace::query()
                ->withoutCompanyScope()
                ->where('company_id', $company->id)
                ->orderBy('id')
                ->first();

            if ($promote instanceof Workspace) {
                $promote->forceFill(['is_default' => true])->save();
                $this->ensureMember($promote, $owner);

                return $promote;
            }

            $workspace = new Workspace([
                'name' => (string) config('saas.operations.default_name', 'Default Workspace'),
                'slug' => 'default',
                'status' => WorkspaceStatus::Active,
                'is_default' => true,
                'timezone' => $company->timezone,
                'currency' => $company->currency,
            ]);
            $workspace->company_id = $company->id;
            $workspace->save();

            $members = $company->members()->get();

            foreach ($members as $member) {
                $role = $owner !== null && $member->is($owner)
                    ? WorkspaceMemberRole::Admin
                    : WorkspaceMemberRole::Member;

                $workspace->members()->syncWithoutDetaching([
                    $member->id => [
                        'role' => $role->value,
                        'status' => WorkspaceMemberStatus::Active->value,
                        'joined_at' => now(),
                    ],
                ]);
            }

            if ($owner instanceof User && ! $members->contains('id', $owner->id)) {
                $workspace->members()->syncWithoutDetaching([
                    $owner->id => [
                        'role' => WorkspaceMemberRole::Admin->value,
                        'status' => WorkspaceMemberStatus::Active->value,
                        'joined_at' => now(),
                    ],
                ]);
            }

            return $workspace;
        });
    }

    protected function ensureMember(Workspace $workspace, ?User $owner): void
    {
        if (! $owner instanceof User) {
            return;
        }

        if ($workspace->members()->whereKey($owner->id)->exists()) {
            return;
        }

        $workspace->members()->attach($owner->id, [
            'role' => WorkspaceMemberRole::Admin->value,
            'status' => WorkspaceMemberStatus::Active->value,
            'joined_at' => now(),
        ]);
    }

    public static function uniqueSlug(Company $company, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 1;

        while (static::slugTaken($company, $slug, $ignoreId)) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }

    protected static function slugTaken(Company $company, string $slug, ?int $ignoreId): bool
    {
        $query = Workspace::query()->withTrashed()
            ->withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
