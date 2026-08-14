<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\AI\Support\HotelPromptPack;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CompanyScope;

/**
 * Installs (or refreshes missing) hotel prompt templates for a company.
 */
class EnsureHotelPromptPack
{
    public function handle(Company $company): void
    {
        foreach (HotelPromptPack::definitions() as $definition) {
            $exists = AiPromptTemplate::query()
                ->withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $company->id)
                ->where('slug', $definition['slug'])
                ->exists();

            if ($exists) {
                continue;
            }

            AiPromptTemplate::query()->create([
                'company_id' => $company->id,
                'user_id' => null,
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'description' => $definition['description'],
                'category' => HotelPromptPack::CATEGORY,
                'prompt' => $definition['prompt'],
                'variables' => [
                    [
                        'name' => 'context',
                        'label' => 'Context',
                        'type' => 'textarea',
                        'required' => true,
                    ],
                ],
                'provider' => null,
                'model' => null,
                'is_shared' => true,
            ]);
        }
    }
}
