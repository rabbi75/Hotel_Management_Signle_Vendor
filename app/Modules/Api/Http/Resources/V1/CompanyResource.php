<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Resources\V1;

use App\Modules\Company\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Company $company */
        $company = $this->resource;

        return [
            'id' => $company->uuid,
            'type' => 'workspace',
            'name' => $company->name,
            'slug' => $company->slug,
            'email' => $company->email,
            'website' => $company->website,
            'timezone' => $company->timezone,
            'currency' => $company->currency,
            'locale' => $company->locale,
            'is_active' => $company->is_active,
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }
}
