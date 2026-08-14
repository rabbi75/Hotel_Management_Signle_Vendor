<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Company\Models\Company;
use App\Modules\Media\Models\MediaFolder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaFolder>
 */
class MediaFolderFactory extends Factory
{
    protected $model = MediaFolder::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));
        $slug = Str::slug($name);

        return [
            'company_id' => Company::factory(),
            'parent_id' => null,
            'name' => $name,
            'slug' => $slug,
            'path' => $slug,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }

    public function childOf(MediaFolder $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'company_id' => $parent->company_id,
            'parent_id' => $parent->id,
            'path' => $parent->path.'/'.$attributes['slug'],
        ]);
    }
}
