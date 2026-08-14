<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Resources;

use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\CompanyMembership;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact workspace shape shared into every Inertia response.
 *
 * Mirrors the `CompanySummary` interface in resources/js/types/index.d.ts; the
 * two must be changed together.
 *
 * @mixin Company
 */
class CompanySummaryResource extends JsonResource
{
    /**
     * @return array{id: int, uuid: string, name: string, slug: string, logo: string|null, initials: string, role: string|null}
     */
    public function toArray(Request $request): array
    {
        /** @var Company $company */
        $company = $this->resource;

        $user = $request->user();

        return [
            'id' => $company->id,
            'uuid' => $company->uuid,
            'name' => $company->name,
            'slug' => $company->slug,
            'logo' => $this->logo($company),
            'initials' => $company->initials(),
            'role' => $user instanceof User ? $this->role($company, $user) : null,
        ];
    }

    /**
     * The media relation is loaded explicitly rather than lazily so this shape
     * stays renderable under Model::preventLazyLoading().
     */
    protected function logo(Company $company): ?string
    {
        if (Model::preventsLazyLoading() && ! $company->relationLoaded('media')) {
            $company->load('media');
        }

        return $company->logoUrl();
    }

    /**
     * The signed-in user's standing inside this workspace.
     *
     * When the company was hydrated through the `companies` relation the pivot
     * is already present; otherwise a single scalar lookup resolves it.
     */
    protected function role(Company $company, User $user): ?string
    {
        if ($company->relationLoaded('pivot')) {
            $pivot = $company->getRelation('pivot');

            if ($pivot instanceof CompanyMembership) {
                return $pivot->role->value;
            }
        }

        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        return $membership?->role->value;
    }
}
