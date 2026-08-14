<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Company\DTOs\DepartmentData;
use App\Modules\Company\Models\Department;
use Illuminate\Validation\ValidationException;

/**
 * Renames / re-parents a department.
 *
 * Re-parenting is the only interesting case: a department may not be moved
 * underneath itself or one of its own descendants, which would detach the
 * whole branch from the tree.
 */
class UpdateDepartment
{
    /**
     * @throws ValidationException
     */
    public function handle(Department $department, DepartmentData $data): Department
    {
        if ($data->wasProvided('parent_id')) {
            $this->guardAgainstCycle($department, $data->parentId);
        }

        $attributes = $data->toUpdateAttributes();

        if ($data->wasProvided('name') && $department->name !== $data->name) {
            $attributes['slug'] = CreateDepartment::uniqueSlug($data->name, $department->id);
        }

        $department->fill($attributes)->save();

        return $department;
    }

    /**
     * @throws ValidationException
     */
    protected function guardAgainstCycle(Department $department, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $department->id) {
            throw ValidationException::withMessages([
                'parent_id' => __('A department cannot be its own parent.'),
            ]);
        }

        $seen = [];
        $cursor = $parentId;

        while ($cursor !== null && ! in_array($cursor, $seen, true)) {
            if ($cursor === $department->id) {
                throw ValidationException::withMessages([
                    'parent_id' => __('A department cannot be moved beneath one of its own sub-departments.'),
                ]);
            }

            $seen[] = $cursor;

            $parent = Department::query()->find($cursor);

            if (! $parent instanceof Department) {
                throw ValidationException::withMessages([
                    'parent_id' => __('The selected parent department does not exist in this workspace.'),
                ]);
            }

            $cursor = $parent->parent_id;
        }
    }
}
