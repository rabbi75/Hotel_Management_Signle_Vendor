<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Resources;

use App\Modules\Company\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Department $department */
        $department = $this->resource;

        return [
            'id' => $department->id,
            'name' => $department->name,
            'slug' => $department->slug,
            'description' => $department->description,
            'parent_id' => $department->parent_id,
            'parent' => $department->relationLoaded('parent') ? $department->parent?->name : null,
            'manager_id' => $department->manager_id,
            'manager' => $department->relationLoaded('manager') ? $department->manager?->name : null,
            'teams_count' => $this->counter($department, 'teams_count'),
            'children_count' => $this->counter($department, 'children_count'),
            'children' => $department->relationLoaded('children')
                ? self::collection($department->children)->resolve($request)
                : [],
            'created_at' => $department->created_at?->toIso8601String(),
        ];
    }

    protected function counter(Department $department, string $key): ?int
    {
        $value = $department->getAttributes()[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
