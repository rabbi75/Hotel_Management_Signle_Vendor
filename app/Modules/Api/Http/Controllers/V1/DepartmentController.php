<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers\V1;

use App\Modules\Api\Http\Resources\V1\DepartmentResource;
use App\Modules\Company\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only department endpoints. Registered only when the Company module is
 * present in the build.
 */
class DepartmentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'read');
        Gate::authorize('viewAny', Department::class);

        return $this->collection($request, Department::query()->orderBy('id'), DepartmentResource::class);
    }

    public function show(Request $request, Department $department): JsonResponse
    {
        $this->requireAbility($request, 'read');
        Gate::authorize('view', $department);

        return $this->item($request, $department, DepartmentResource::class);
    }
}
