<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers\V1;

use App\Modules\Api\Http\Resources\V1\CompanyResource;
use App\Modules\Company\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The workspace a token is bound to. A token can only ever see its own.
 */
class CompanyController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'read');

        return $this->collection($request, Company::query()->whereKey(current_company_id())->orderBy('id'), CompanyResource::class);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $this->requireAbility($request, 'read');

        $company = Company::query()->whereKey(current_company_id())->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('view', $company);

        return $this->item($request, $company, CompanyResource::class);
    }
}
