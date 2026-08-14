<?php

declare(strict_types=1);

namespace App\Modules\CMS\Database\Factories;

use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Models\Menu;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(2, true),
            'location' => MenuLocation::Header,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }

    public function location(MenuLocation $location): static
    {
        return $this->state(fn (array $attributes): array => ['location' => $location]);
    }
}
