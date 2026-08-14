<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Billing\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * The default plan catalogue.
 *
 * Idempotent: matched on slug, so re-running updates the plans in place rather
 * than duplicating them. Prices are integer minor units; limits use -1 for
 * unlimited and entitlements are keys from config/entitlements.php.
 */
class PlanSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    protected array $plans = [
        [
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'For trying things out.',
            'features' => ['Up to 3 members', '1 hotel', '30 rooms', '1 GB storage', 'Community support'],
            'entitlements' => ['chat', 'media', 'hotel_management', 'reservations'],
            'limits' => ['seats' => 3, 'storage_mb' => 1024, 'ai_credits' => 100, 'workspaces' => 1, 'hotels' => 1, 'rooms' => 30],
            'monthly_price' => 0,
            'yearly_price' => 0,
            'trial_days' => 0,
            'sort' => 10,
        ],
        [
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'For growing teams that need the whole toolkit.',
            'features' => ['Up to 25 members', '3 hotels', '150 rooms', '50 GB storage', 'Housekeeping & reports', 'Priority support'],
            'entitlements' => ['chat', 'media', 'ai', 'blog', 'cms', 'export', 'hotel_management', 'reservations', 'housekeeping', 'maintenance', 'hotel_reports'],
            'limits' => ['seats' => 25, 'storage_mb' => 51200, 'ai_credits' => 5000, 'workspaces' => 3, 'hotels' => 3, 'rooms' => 150],
            'monthly_price' => 2900,
            'yearly_price' => 29000,
            'trial_days' => 14,
            'sort' => 20,
        ],
        [
            'name' => 'Scale',
            'slug' => 'scale',
            'description' => 'Everything, unlimited seats, every module.',
            'features' => ['Unlimited members', 'Unlimited hotels & rooms', '500 GB storage', 'Every feature', 'Dedicated support'],
            'entitlements' => ['chat', 'media', 'ai', 'blog', 'cms', 'seo', 'api', 'audit_log', 'export', 'custom_roles', 'hotel_management', 'reservations', 'housekeeping', 'maintenance', 'hotel_reports', 'online_booking', 'hotel_pos'],
            'limits' => ['seats' => -1, 'storage_mb' => 512000, 'ai_credits' => 50000, 'workspaces' => -1, 'hotels' => -1, 'rooms' => -1],
            'monthly_price' => 9900,
            'yearly_price' => 99000,
            'trial_days' => 14,
            'sort' => 30,
        ],
    ];

    public function run(): void
    {
        foreach ($this->plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    ...$plan,
                    'currency' => config('saas.defaults.currency', 'USD'),
                    'is_active' => true,
                    'is_public' => true,
                    'gateway_prices' => null,
                ],
            );
        }

        $this->command?->info('Seeded '.count($this->plans).' plans.');
    }
}
