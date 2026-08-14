<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Resources;

use App\Modules\Company\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A workspace as the operator sees it: identity, owner, plan and standing.
 *
 * Deliberately not CompanyResource — that one answers "what may a member see
 * about their own workspace", which is a different question and a different
 * set of fields.
 *
 * @mixin Company
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subscription = $this->relationLoaded('activeSubscription') ? $this->activeSubscription : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logoUrl(),
            'initials' => $this->initials(),
            'is_active' => $this->is_active,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'country_code' => $this->country_code,
            'city' => $this->city,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'owner' => $this->whenLoaded('owner', fn (): array => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),

            // members_count is set by the controller from a single grouped query,
            // so a list of fifty workspaces stays one query rather than fifty-one.
            'members_count' => $this->members_count ?? null,

            'plan' => $subscription?->plan?->name,
            'plan_slug' => $subscription?->plan?->slug,
            'subscription_status' => $subscription?->status->value,
            'subscription_interval' => $subscription?->interval->value,
            'renews_at' => $subscription?->current_period_end?->toIso8601String(),
        ];
    }
}
