<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Company\Models\Company;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'company_id' => Company::factory(),
            'name' => $name,
            'content_hash' => hash('sha256', Str::random(32)),
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => fake()->numberBetween(10_000, 4_000_000),
            'width' => 1280,
            'height' => 720,
            'disk' => 'public',

            // Nullable columns are written explicitly: strict mode throws on
            // reading a column the insert never touched, and the resource reads
            // every one of these on render.
            'folder_id' => null,
            'uploaded_by' => null,
            'original_asset_id' => null,
            'title' => null,
            'alt' => null,
            'caption' => null,
            'tags' => null,
            'deleted_at' => null,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }

    public function inFolder(MediaFolder $folder): static
    {
        return $this->state(fn (array $attributes): array => [
            'company_id' => $folder->company_id,
            'folder_id' => $folder->id,
        ]);
    }

    public function uploadedBy(User $user): static
    {
        return $this->state(fn (array $attributes): array => ['uploaded_by' => $user->id]);
    }

    public function document(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'width' => null,
            'height' => null,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
        ]);
    }
}
