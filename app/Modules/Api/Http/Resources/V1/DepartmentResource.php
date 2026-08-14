<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Resources\V1;

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
            'id' => (string) $department->id,
            'type' => 'department',
            'name' => $department->name,
            'slug' => $department->slug,
            'description' => $department->description,
            'parent_id' => $department->parent_id === null ? null : (string) $department->parent_id,
            'created_at' => $department->created_at?->toIso8601String(),
        ];
    }
}
