<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Company\DTOs\DepartmentData;
use App\Modules\Company\Models\Department;
use Illuminate\Support\Str;

class CreateDepartment
{
    public function handle(DepartmentData $data): Department
    {
        $department = new Department($data->toAttributes());
        $department->slug = static::uniqueSlug($data->name);
        $department->save();

        return $department;
    }

    /**
     * Slugs are unique per workspace; the tenant scope already narrows the
     * probe to the active workspace.
     */
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
        $query = Department::query()->withTrashed()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
