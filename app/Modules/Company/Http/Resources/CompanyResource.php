<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Resources;

use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full workspace record, used by the workspace profile screens.
 *
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

        if (Model::preventsLazyLoading() && ! $company->relationLoaded('media')) {
            $company->load('media');
        }

        return [
            'id' => $company->id,
            'uuid' => $company->uuid,
            'name' => $company->name,
            'slug' => $company->slug,
            'owner_id' => $company->owner_id,
            'email' => $company->email,
            'phone' => $company->phone,
            'website' => $company->website,
            'tax_id' => $company->tax_id,
            'address_line_1' => $company->address_line_1,
            'address_line_2' => $company->address_line_2,
            'city' => $company->city,
            'state' => $company->state,
            'postal_code' => $company->postal_code,
            'country_code' => $company->country_code,
            'timezone' => $company->timezone,
            'currency' => $company->currency,
            'locale' => $company->locale,
            'is_active' => $company->is_active,
            'logo' => $company->logoUrl(),
            'initials' => $company->initials(),
            'on_trial' => $company->onTrial(),
            'trial_ends_at' => $company->trial_ends_at?->toIso8601String(),
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }
}
