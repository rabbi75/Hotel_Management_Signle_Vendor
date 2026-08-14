<?php

declare(strict_types=1);

namespace App\Modules\Settings\Database\Seeders;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Support\SettingsSchema;
use App\Support\Settings\SettingsRepository;
use Illuminate\Database\Seeder;

/**
 * Plants the system-scope defaults declared by {@see SettingsSchema}.
 *
 * Idempotent: a key that already exists is left alone, so re-running the
 * seeder after a deploy adds newly declared settings without reverting values
 * an administrator has since changed.
 */
class SettingsSeeder extends Seeder
{
    public function __construct(protected SettingsRepository $settings) {}

    public function run(): void
    {
        $existing = Setting::query()
            ->withoutGlobalScopes()
            ->where('scope', SettingsRepository::SCOPE_SYSTEM)
            ->whereNull('scope_id')
            ->pluck('key')
            ->all();

        $missing = array_diff_key(SettingsSchema::defaults(), array_flip($existing));

        // Secrets have no sensible default; seeding them as an empty encrypted
        // blob would only make "is this configured?" harder to answer.
        $missing = array_filter(
            $missing,
            static fn (mixed $value, string $key): bool => ! SettingsSchema::isEncrypted($key),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($missing === []) {
            return;
        }

        $this->settings->setMany($missing, SettingsRepository::SCOPE_SYSTEM);
    }
}
